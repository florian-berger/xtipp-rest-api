<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Models\Game\Game;
use App\Models\Season\Season;
use App\Models\Tip\Tip;
use Carbon\Carbon;
use FlorianBerger\RestApi\Services\MatchdayResolver;
use FlorianBerger\RestApi\Services\Presenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Matchdays and rounds of a season with their games, the own tips and the tip overview
 */
class MatchdayController extends ApiController {
    /**
     * @param MatchdayResolver $matchdays
     * @param Presenter        $presenter
     */
    public function __construct(
        private readonly MatchdayResolver $matchdays,
        private readonly Presenter $presenter,
    ) {
    }

    /**
     * All matchdays and rounds of a season, the key is the identifier in all further calls
     *
     * @param int $season
     *
     * @return JsonResponse
     */
    public function index(int $season): JsonResponse {
        $info = $this->matchdays->forSeason($this->season($season));

        $list = [];
        foreach ($info['items'] as $key => $name) {
            $list[] = [
                'key'       => $key,
                'name'      => $name,
                'current'   => $key === $info['current'],
                'has_table' => (bool) ($info['with_table'][$key] ?? false),
            ];
        }

        return $this->data($list);
    }

    /**
     * One matchday or round with its games
     *
     * @param int    $season
     * @param string $matchday
     *
     * @return JsonResponse
     */
    public function show(int $season, string $matchday): JsonResponse {
        $seasonModel = $this->season($season);
        $games       = $this->games($seasonModel, $matchday);

        return $this->data([
            'key'   => $matchday,
            'name'  => $this->matchdays->forSeason($seasonModel)['items'][$matchday],
            'games' => $games->map(fn (Game $game) => $this->presenter->game($game))->all(),
        ]);
    }

    /**
     * The own tips of a matchday or round, with the points
     *
     * @param int    $season
     * @param string $matchday
     *
     * @return JsonResponse
     */
    public function tips(int $season, string $matchday): JsonResponse {
        $games = $this->games($this->season($season), $matchday);
        $tips  = Tip::where('user_id', Auth::id())->whereIn('game_id', $games->modelKeys())->get()->keyBy('game_id');

        return $this->data($games->map(function (Game $game) use ($tips) {
            $result = $this->presenter->result($game);

            return [
                'game' => $this->presenter->game($game),
                'tip'  => $this->presenter->tip($result, $tips->get($game->id)),
            ];
        })->all());
    }

    /**
     * The tip overview of a matchday or round
     *
     * Same rules as on the website: the tips of the other users are only visible, when the deadline of the game is
     * reached (the status of the game does not matter). Before, only the own tip is returned.
     *
     * @param int    $season
     * @param string $matchday
     *
     * @return JsonResponse
     */
    public function overview(int $season, string $matchday): JsonResponse {
        $games = $this->games($this->season($season), $matchday, ['tips.user']);

        return $this->data($games->map(function (Game $game) {
            $result      = $this->presenter->result($game);
            // Only the deadline counts, exactly like the website: a game can be cancelled or aborted before its
            // deadline and be planned again later, then the tips must still be hidden
            $visible     = $game->reachedDeadline();
            $ownTip      = $game->tips->firstWhere('user_id', Auth::id());
            $otherTips   = [];

            if ($visible) {
                foreach ($game->sortedUserTips() as $tip) {
                    if ((int) $tip->user_id === (int) Auth::id()) {
                        continue;
                    }

                    $otherTips[] = ['user' => $this->presenter->user($tip->user, $tip->user_id)] + $this->presenter->tip($result, $tip);
                }
            }

            return [
                'game'         => $this->presenter->game($game),
                'own_tip'      => $this->presenter->tip($result, $ownTip),
                'tips_visible' => $visible,
                'tips'         => $otherTips,
                'allocation'   => $this->allocation($game),
            ];
        })->all());
    }

    /**
     * Distribution of the tips (home win/draw/away win), with the same display rules as on the website
     *
     * @param Game $game Needs the loaded tips
     *
     * @return array<string, mixed>|null Null, if the website would not show it
     */
    private function allocation(Game $game): ?array {
        if ((int) config('xtipp.tip-overview-allocation', 1) !== 1) {
            return null;
        }

        $total = $game->tips->count();
        if ($total < (int) config('xtipp.tip-overview-allocation-minimum-tips-to-show', 5)) {
            return null;
        }

        $showBefore = (int) config('xtipp.tip-overview-allocation-show-before-game-start', 0);
        if ($showBefore !== 0 && Carbon::now()->lt($game->date->copy()->subMinutes($showBefore))) {
            return null;
        }

        $home = $game->tips->filter(fn (Tip $tip) => $tip->home_goals > $tip->guest_goals)->count();
        $away = $game->tips->filter(fn (Tip $tip) => $tip->home_goals < $tip->guest_goals)->count();
        $draw = $total - $home - $away;

        $part = fn (int $count) => ['count' => $count, 'percentage' => round($count / $total * 100, 2)];

        return ['total' => $total, 'home' => $part($home), 'draw' => $part($draw), 'away' => $part($away)];
    }

    /**
     * The games of a matchday or round
     *
     * @param Season   $season
     * @param string   $matchday
     * @param string[] $with     Further relations
     *
     * @return Collection<int, Game>
     */
    private function games(Season $season, string $matchday, array $with = []): Collection {
        $query = $this->matchdays->gamesQuery($season, $matchday) ?? $this->fail(404, 'Matchday not found.');

        return $query->with(array_merge(['homeTeam', 'awayTeam', 'Round'], $with))->get();
    }
}
