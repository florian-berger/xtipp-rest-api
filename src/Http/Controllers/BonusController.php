<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Helper\SyncLock;
use App\Models\Bonus\Bonus;
use App\Models\Bonus\BonusTip;
use App\Models\League;
use App\Models\Player;
use App\Models\Season\Season;
use App\Models\Team;
use FlorianBerger\RestApi\Services\BonusChoices;
use FlorianBerger\RestApi\Services\BonusUpdater;
use FlorianBerger\RestApi\Services\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Bonus questions of a season and the own answers
 */
class BonusController extends ApiController {
    /**
     * @param BonusChoices $choices
     * @param BonusUpdater $updater
     * @param Presenter    $presenter
     */
    public function __construct(
        private readonly BonusChoices $choices,
        private readonly BonusUpdater $updater,
        private readonly Presenter $presenter,
    ) {
    }

    /**
     * All bonus questions of a season with the own answers, sorted by question like on the website
     *
     * @param int $season
     *
     * @return JsonResponse
     */
    public function index(int $season): JsonResponse {
        $seasonModel = $this->season($season);
        $leagueType  = League::whereKey($seasonModel->league_id)->value('type');

        $questions = Bonus::with('bonusPoints')->where('season_id', $seasonModel->id)->orderBy('question')->get();
        $answers   = BonusTip::where('user_id', Auth::id())
            ->whereIn('bonus_id', $questions->modelKeys())
            ->orderBy('id')
            ->get()
            ->groupBy('bonus_id');

        return $this->data($questions->map(
            fn (Bonus $bonus) => $this->presenter->bonus($bonus, $answers->get($bonus->id, collect()), $leagueType)
        )->all());
    }

    /**
     * One bonus question with the own answers
     *
     * @param int $bonus
     *
     * @return JsonResponse
     */
    public function show(int $bonus): JsonResponse {
        return $this->data($this->present($this->bonus($bonus)));
    }

    /**
     * Clubs/countries which can be chosen for a question (for a club question as answer, for a player question to find
     * the players with the next call)
     *
     * @param Request $request
     * @param int     $bonus
     *
     * @return JsonResponse
     */
    public function clubs(Request $request, int $bonus): JsonResponse {
        $bonusModel = $this->bonus($bonus);
        $data       = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return $this->data(
            $this->choices->clubs($bonusModel, $data['search'] ?? null)->map(fn (Team $team) => $this->presenter->bonusClub($team))->all()
        );
    }

    /**
     * Players of a club, which can be chosen for a player question
     *
     * @param Request $request
     * @param int     $bonus
     *
     * @return JsonResponse
     */
    public function players(Request $request, int $bonus): JsonResponse {
        $bonusModel = $this->bonus($bonus);
        if ($bonusModel->type !== 'player') {
            $this->fail(404, 'This question asks for a club, not for a player.');
        }

        $data = $request->validate([
            'club'   => ['required', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        if (!$this->choices->isClub($bonusModel, (int) $data['club'])) {
            throw ValidationException::withMessages(['club' => ['This club can not be chosen for this question.']]);
        }

        return $this->data(
            $this->choices->players((int) $data['club'], $data['search'] ?? null)->map(fn (Player $player) => $this->presenter->bonusPlayer($player))->all()
        );
    }

    /**
     * Set the own answers of a question. The list replaces all answers, so the call can be repeated safely. An empty
     * list deletes the answers.
     *
     * @param Request $request
     * @param int     $bonus
     *
     * @return JsonResponse
     */
    public function update(Request $request, int $bonus): JsonResponse {
        $bonusModel = $this->openBonus($bonus);
        $max        = max(1, (int) $bonusModel->possible_answers);

        $data = $request->validate([
            'answers'   => ['present', 'array', 'list', 'max:' . $max],
            'answers.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], [
            'answers.max' => $max === 1 ? 'Only one answer is possible for this question.' : 'Only ' . $max . ' answers are possible for this question.',
        ]);
        $answers = array_map('intval', $data['answers']);

        // Answers given already stay valid, even if the player changed the club in the meantime
        $given = BonusTip::where('bonus_id', $bonusModel->id)->where('user_id', Auth::id())->pluck('answer')->map(fn ($answer) => (int) $answer)->all();
        foreach ($answers as $index => $answer) {
            if (!in_array($answer, $given, true) && !$this->choices->isValidAnswer($bonusModel, $answer)) {
                throw ValidationException::withMessages([
                    'answers.' . $index => [$bonusModel->type === 'club' ? 'This club can not be chosen for this question.' : 'This player can not be chosen for this question.'],
                ]);
            }
        }

        $outcome = $this->updater->store(Auth::user(), $bonusModel, $answers);

        return response()->json(['data' => $this->present($bonusModel)], $outcome === BonusUpdater::CREATED ? 201 : 200);
    }

    /**
     * Delete the own answers of a question
     *
     * @param int $bonus
     *
     * @return JsonResponse
     */
    public function destroy(int $bonus): JsonResponse {
        $this->updater->store(Auth::user(), $this->openBonus($bonus), []);

        return response()->json(null, 204);
    }

    /**
     * A bonus question, only if its league is available (otherwise "not found", like an unknown question)
     *
     * @param int $id
     *
     * @return Bonus
     */
    private function bonus(int $id): Bonus {
        $bonus    = Bonus::with('bonusPoints')->find($id) ?? $this->fail(404, 'Bonus question not found.');
        $leagueId = Season::whereKey($bonus->season_id)->value('league_id');
        if ($leagueId === null || !$this->rules()->isLeagueAvailable((int) $leagueId)) {
            $this->fail(404, 'Bonus question not found.');
        }

        return $bonus;
    }

    /**
     * A bonus question which accepts answers, otherwise stop with an error
     *
     * @param int $id
     *
     * @return Bonus
     */
    private function openBonus(int $id): Bonus {
        $bonus = $this->bonus($id);

        // The data are replaced while the xDATAservice synchronizes
        if (SyncLock::isRunning()) {
            $this->fail(423, 'The data are being synchronized, please try again in a moment.');
        }
        if (!$this->updater->isOpen($bonus)) {
            $this->fail(409, 'The deadline for this bonus question has been reached.');
        }

        return $bonus;
    }

    /**
     * The question with the current own answers
     *
     * @param Bonus $bonus
     *
     * @return array<string, mixed>
     */
    private function present(Bonus $bonus): array {
        $leagueType = League::whereKey(Season::whereKey($bonus->season_id)->value('league_id'))->value('type');
        $answers    = BonusTip::where('bonus_id', $bonus->id)->where('user_id', Auth::id())->orderBy('id')->get();

        return $this->presenter->bonus($bonus, $answers, $leagueType);
    }
}
