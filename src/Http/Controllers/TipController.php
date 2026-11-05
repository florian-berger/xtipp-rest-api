<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Helper\SyncLock;
use App\Models\Game\Game;
use App\Models\Season\Season;
use App\Models\Tip\Tip;
use FlorianBerger\RestApi\Services\Presenter;
use FlorianBerger\RestApi\Services\TipUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Change the own tip of a game
 */
class TipController extends ApiController {
    /**
     * @param TipUpdater $tips
     * @param Presenter  $presenter
     */
    public function __construct(
        private readonly TipUpdater $tips,
        private readonly Presenter $presenter,
    ) {
    }

    /**
     * Create or change the own tip of a game (the same call creates and changes, so it can be repeated safely).
     * Both goals null delete the tip, like on the website.
     *
     * @param Request $request
     * @param int     $game
     *
     * @return JsonResponse
     */
    public function update(Request $request, int $game): JsonResponse {
        $gameModel = $this->openGame($game);

        $data = $request->validate([
            'home_goals'  => ['present', 'nullable', 'integer', 'between:0,99'],
            'guest_goals' => ['present', 'nullable', 'integer', 'between:0,99'],
        ]);

        $home  = $data['home_goals'] !== null ? (int) $data['home_goals'] : null;
        $guest = $data['guest_goals'] !== null ? (int) $data['guest_goals'] : null;
        if (($home === null) !== ($guest === null)) {
            throw ValidationException::withMessages([
                ($home === null ? 'home_goals' : 'guest_goals') => ['Both goals must be given, or both must be null to delete the tip.'],
            ]);
        }
        if ($home !== null && $this->tips->isForbiddenDraw($gameModel, $home, $guest)) {
            throw ValidationException::withMessages(['guest_goals' => ['A draw is not allowed as tip for this game.']]);
        }

        $outcome = $this->tips->store(Auth::user(), $gameModel, $home, $guest);

        return $this->respond($gameModel, $outcome);
    }

    /**
     * Delete the own tip of a game
     *
     * @param int $game
     *
     * @return JsonResponse
     */
    public function destroy(int $game): JsonResponse {
        $gameModel = $this->openGame($game);

        $this->tips->store(Auth::user(), $gameModel, null, null);

        return response()->json(null, 204);
    }

    /**
     * Find a game which accepts tips, otherwise stop with an error
     *
     * @param int $id
     *
     * @return Game
     */
    private function openGame(int $id): Game {
        $game = Game::with(['Round', 'homeTeam', 'awayTeam'])->find($id) ?? $this->fail(404, 'Game not found.');

        // Only games of an available league (like an unknown game otherwise)
        $leagueId = $game->Round !== null ? Season::whereKey($game->Round->season_id)->value('league_id') : null;
        if ($leagueId === null || !$this->rules()->isLeagueAvailable((int) $leagueId)) {
            $this->fail(404, 'Game not found.');
        }

        // The data are replaced while the xDATAservice synchronizes
        if (SyncLock::isRunning()) {
            $this->fail(423, 'The data are being synchronized, please try again in a moment.');
        }
        if (!$this->tips->isOpen($game)) {
            $this->fail(409, 'The deadline for this game has been reached.');
        }

        return $game;
    }

    /**
     * @param Game   $game
     * @param string $outcome
     *
     * @return JsonResponse
     */
    private function respond(Game $game, string $outcome): JsonResponse {
        $tip = $outcome === TipUpdater::DELETED
            ? null
            : Tip::where('user_id', Auth::id())->where('game_id', $game->id)->first();

        return response()->json(
            ['data' => ['game' => $this->presenter->game($game), 'tip' => $this->presenter->tip($this->presenter->result($game), $tip)]],
            $outcome === TipUpdater::CREATED ? 201 : 200
        );
    }
}
