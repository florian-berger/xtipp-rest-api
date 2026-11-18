<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Enums\RankingSortType;
use App\Models\Game\Game;
use App\Models\Ranking\Ranking;
use App\Models\Ranking\RankingSort;
use App\Models\Round\Round;
use App\Models\Season\Season;
use App\Models\User;
use FlorianBerger\RestApi\Services\MatchdayResolver;
use FlorianBerger\RestApi\Services\Presenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single ranking of a season and the all-time ranking of a league
 */
class RankingController extends ApiController {
    /** Columns of a ranking row, which decide whether two users share a place */
    private const COLUMNS = ['tips', 'result', 'difference', 'tendency', 'extra', 'bonus', 'points'];

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
     * Ranking of a season, optionally limited from one matchday/round to another (keys of the matchday list)
     *
     * @param Request $request
     * @param int     $season
     *
     * @return JsonResponse
     */
    public function bySeason(Request $request, int $season): JsonResponse {
        $seasonModel = $this->season($season);

        $query = $request->validate([
            'from' => ['nullable', 'string', 'max:100'],
            'to'   => ['nullable', 'string', 'max:100'],
        ]);
        $filter    = $this->matchdays->rankingFilter($seasonModel, $query['from'] ?? null, $query['to'] ?? null);
        $selection = $this->matchdays->selection($seasonModel, $query['from'] ?? null, $query['to'] ?? null);
        if ($filter === null || $selection === null) {
            $this->fail(422, 'The matchdays "from" and "to" must be keys of the matchday list of this season, "from" not after "to".');
        }

        $rows = Ranking::ranking($seasonModel->id, RankingSortType::SINGLE, $filter['matchDays'], $filter['rounds']);

        // Like the website since xTIPP 1.0.36: the points of the last played matchday of the selection (none, if the
        // selection has no played game) and the change of the places by this matchday
        $lastPlayed     = $this->lastPlayedMatchday($seasonModel, $selection);
        $points         = $this->matchdayPoints($seasonModel, $lastPlayed);
        $previousPlaces = $this->previousPlaces($seasonModel, $filter, $lastPlayed);
        $currentPlaces  = $previousPlaces !== null ? Ranking::calcPlaces($rows) : [];

        $output = $this->rows($rows, $points);
        foreach ($output as $index => $entry) {
            $userId = $entry['user']['id'];

            $output[$index]['place_change'] = isset($previousPlaces[$userId], $currentPlaces[$userId])
                ? $previousPlaces[$userId] - $currentPlaces[$userId]
                : null;
        }

        return $this->data($output);
    }

    /**
     * All-time ranking of all seasons of a league
     *
     * @param int $league
     *
     * @return JsonResponse
     */
    public function allTime(int $league): JsonResponse {
        $leagueModel = $this->league($league);
        $prefix      = DB::getTablePrefix();

        $query = Ranking::leftJoin('ranking_metas', 'ranking_metas.id', '=', 'rankings.ranking_meta_id')
            ->selectRaw($prefix . 'rankings.user_id,
                         SUM(' . $prefix . 'rankings.tips) as tips,
                         SUM(' . $prefix . 'rankings.result) as result,
                         SUM(' . $prefix . 'rankings.difference) as difference,
                         SUM(' . $prefix . 'rankings.tendency) as tendency,
                         SUM(' . $prefix . 'rankings.points) as points,
                         SUM(' . $prefix . 'rankings.extra) as extra,
                         SUM(' . $prefix . 'rankings.bonus) as bonus')
            ->whereIn('ranking_metas.season_id', Season::where('league_id', $leagueModel->id)->pluck('id'))
            ->groupBy('rankings.user_id');

        foreach (RankingSort::getSort(RankingSortType::SINGLE) as $sort) {
            $query->orderBy($sort->column, $sort->sort);
        }
        $query->orderBy('rankings.user_id');

        return $this->data($this->rows($query->get()));
    }

    /**
     * Rows of the API: place (equal rows share the place, the next place is skipped), user and the sums
     *
     * @param Collection      $rows
     * @param array|null|bool $currentPoints Points of the matchday by user id, null for no points (field is null),
     *                                       false for a ranking without the field
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(Collection $rows, array|null|bool $currentPoints = false): array {
        $users = User::withTrashed()->whereIn('id', $rows->pluck('user_id')->unique())->get()->keyBy('id');

        $output   = [];
        $previous = null;
        $place    = 0;
        foreach ($rows as $index => $row) {
            $values = [];
            foreach (self::COLUMNS as $column) {
                $values[$column] = (int) $row->{$column};
            }

            if ($values !== $previous) {
                $place = $index + 1;
            }
            $previous = $values;

            $entry = ['place' => $place, 'user' => $this->presenter->user($users->get($row->user_id), (int) $row->user_id)] + $values;
            if ($currentPoints !== false) {
                $entry['current_matchday_points'] = $currentPoints !== null ? (int) ($currentPoints[$row->user_id] ?? 0) : null;
            }
            $output[] = $entry;
        }

        return $output;
    }

    /**
     * The live and finished games of a season
     *
     * @param Season $season
     *
     * @return Builder
     */
    private function playedGames(Season $season): Builder {
        return Game::whereHas('Round', fn (Builder $round) => $round->where('season_id', $season->id))
            ->whereIn('status', ['live', 'finished']);
    }

    /**
     * The last played matchday of a selection, like RankingController::getLastPlayedMatchDayOfSelection() of xTIPP
     *
     * The matchday number is only unique inside a round (e.g. every cup round can have the matchday 1), so selected
     * rounds are limited to the last played round group (e.g. all groups of a group phase).
     *
     * @param Season $season
     * @param array  $selection See MatchdayResolver::selection()
     *
     * @return array{roundIds: array<int, int>, matchDay: int|null}|null Null without a usable selection, matchDay null
     *                                                                   if the selection has no played game
     */
    private function lastPlayedMatchday(Season $season, array $selection): ?array {
        $matchDays = $selection['matchDays'];

        // Selected matchdays
        if (isset($matchDays['to'])) {
            $matchDay = $this->playedGames($season)
                ->whereBetween('game_match_day_id', [(int) ($matchDays['from'] ?? 1), (int) $matchDays['to']])
                ->max('game_match_day_id');
            if ($matchDay === null) {
                return ['roundIds' => [], 'matchDay' => null];
            }

            return [
                'roundIds' => $this->playedGames($season)->where('game_match_day_id', $matchDay)->distinct()->pluck('round_id')->map(fn ($id) => (int) $id)->all(),
                'matchDay' => (int) $matchDay,
            ];
        }

        // Selected rounds
        if (empty($selection['rounds']['in'])) {
            return null;
        }

        $playedRoundIds = $this->playedGames($season)->whereIn('round_id', $selection['rounds']['in'])->distinct()->pluck('round_id');
        $playedRounds   = Round::whereIn('id', $playedRoundIds)->get();
        if ($playedRounds->isEmpty()) {
            return ['roundIds' => [], 'matchDay' => null];
        }

        // The last played round group and its newest matchday
        $roundIds = $playedRounds->where('sort_1', $playedRounds->max('sort_1'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [
            'roundIds' => $roundIds,
            'matchDay' => (int) $this->playedGames($season)->whereIn('round_id', $roundIds)->max('game_match_day_id'),
        ];
    }

    /**
     * Points of the users at the last played matchday of the selection, without a usable selection at the current
     * matchday/round of the season (like RankingController::getActualPointsOfUsers() of xTIPP)
     *
     * @param Season     $season
     * @param array|null $lastPlayed See lastPlayedMatchday()
     *
     * @return array<int, int|string>|null Null, if the selection has no played game (no points are shown then)
     */
    private function matchdayPoints(Season $season, ?array $lastPlayed): ?array {
        if ($lastPlayed !== null && $lastPlayed['matchDay'] === null) {
            return null;
        }

        if ($lastPlayed !== null) {
            $roundIds = $lastPlayed['roundIds'];
            $matchDay = $lastPlayed['matchDay'];
        } else {
            // The current round group of the season
            $game  = $season->getCurrentOrLastGame();
            $round = $game ? Round::find($game->round_id) : null;
            if ($round === null) {
                return [];
            }

            $roundIds = Round::where('season_id', $season->id)->where('sort_1', $round->sort_1)->pluck('id');
            $matchDay = $game->game_match_day_id;
        }

        $prefix = DB::getTablePrefix();

        return Ranking::join('ranking_metas', 'ranking_metas.id', '=', 'rankings.ranking_meta_id')
            ->where('ranking_metas.season_id', $season->id)
            ->whereIn('ranking_metas.round_id', $roundIds)
            ->where('ranking_metas.match_day', $matchDay)
            ->groupBy('rankings.user_id')
            ->selectRaw($prefix . 'rankings.user_id, SUM(' . $prefix . 'rankings.points) as points')
            ->pluck('points', 'user_id')
            ->all();
    }

    /**
     * Places of the users before the last played matchday of the selection (like
     * RankingController::getPreviousPlaces() of xTIPP)
     *
     * @param Season     $season
     * @param array      $filter     Filter of the ranking, see MatchdayResolver::rankingFilter()
     * @param array|null $lastPlayed See lastPlayedMatchday()
     *
     * @return array<int, int>|null User id => place, null if nothing was played before this matchday
     */
    private function previousPlaces(Season $season, array $filter, ?array $lastPlayed): ?array {
        if ($lastPlayed === null || $lastPlayed['matchDay'] === null) {
            return null;
        }

        $previous = Ranking::ranking(
            $season->id,
            RankingSortType::SINGLE,
            $filter['matchDays'],
            $filter['rounds'],
            excludedMatchDay: $lastPlayed,
        );

        // Without points nothing was played before, and all users would share the first place
        if (!$previous->contains(fn ($row) => $row->points > 0)) {
            return null;
        }

        return Ranking::calcPlaces($previous);
    }
}
