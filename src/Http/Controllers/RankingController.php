<?php
namespace FlorianBerger\RestApi\Http\Controllers;

use App\Enums\RankingSortType;
use App\Models\Ranking\Ranking;
use App\Models\Ranking\RankingSort;
use App\Models\Round\Round;
use App\Models\Season\Season;
use App\Models\User;
use FlorianBerger\RestApi\Services\MatchdayResolver;
use FlorianBerger\RestApi\Services\Presenter;
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
        $filter = $this->matchdays->rankingFilter($seasonModel, $query['from'] ?? null, $query['to'] ?? null)
            ?? $this->fail(422, 'The matchdays "from" and "to" must be keys of the matchday list of this season, "from" not after "to".');

        $rows = Ranking::ranking($seasonModel->id, RankingSortType::SINGLE, $filter['matchDays'], $filter['rounds']);

        // The points of the current matchday/round
        $current = $this->currentPoints($seasonModel);

        return $this->data($this->rows($rows, $current));
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
     * @param Collection $rows
     * @param array      $currentPoints Points of the current matchday/round by user id, null for none
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(Collection $rows, ?array $currentPoints = null): array {
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
            if ($currentPoints !== null) {
                $entry['current_matchday_points'] = (int) ($currentPoints[$row->user_id] ?? 0);
            }
            $output[] = $entry;
        }

        return $output;
    }

    /**
     * Points of the users at the current matchday/round of a season
     *
     * The matchday number is only unique inside a round (e.g. every cup round has the matchday 1), so only the rounds
     * of the current round group are used.
     *
     * @param Season $season
     *
     * @return array<int, int|string>
     */
    private function currentPoints(Season $season): array {
        $game = $season->getCurrentOrLastGame();
        if (!$game) {
            return [];
        }

        $round = Round::find($game->round_id);
        if ($round === null) {
            return [];
        }

        $prefix   = DB::getTablePrefix();
        $roundIds = Round::where('season_id', $season->id)->where('sort_1', $round->sort_1)->pluck('id');

        return Ranking::join('ranking_metas', 'ranking_metas.id', '=', 'rankings.ranking_meta_id')
            ->where('ranking_metas.season_id', $season->id)
            ->whereIn('ranking_metas.round_id', $roundIds)
            ->where('ranking_metas.match_day', $game->game_match_day_id)
            ->groupBy('rankings.user_id')
            ->selectRaw($prefix . 'rankings.user_id, SUM(' . $prefix . 'rankings.points) as points')
            ->pluck('points', 'user_id')
            ->all();
    }
}
