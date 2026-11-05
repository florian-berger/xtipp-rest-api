<?php
namespace FlorianBerger\RestApi\Services;

use App\Models\Game\Game;
use App\Models\Season\Season;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translates the matchday keys of xTIPP ("matchday_5", "round_12-13") into games and ranking filters
 *
 * The keys are the same as in the web interface (see Season::preparedMatchDayRounds()), so the API shows exactly the
 * matchdays and rounds a user knows from the website.
 */
class MatchdayResolver {
    /**
     * Matchdays and rounds of a season, keyed by their key
     *
     * @param Season $season
     *
     * @return array{items: array<string, string>, with_table: array<string, int>, current: string}
     */
    public function forSeason(Season $season): array {
        $prepared = $season->preparedMatchDayRounds();

        return [
            'items'      => $prepared['matchDaysRounds'],
            'with_table' => $prepared['matchDaysRoundsWithTable'],
            'current'    => $prepared['lastMatchDayRound'],
        ];
    }

    /**
     * Check, if a key is well-formed, before it is used in a query
     *
     * @param string $key
     *
     * @return bool
     */
    public function isWellFormed(string $key): bool {
        return preg_match('/^(matchday_\d{1,6}|round_\d{1,10}(-\d{1,10})*)$/', $key) === 1;
    }

    /**
     * Query of the games of a matchday or round of a season
     *
     * @param Season $season
     * @param string $key
     *
     * @return Builder|null Null, if the key is not a matchday or round of this season
     */
    public function gamesQuery(Season $season, string $key): ?Builder {
        if (!$this->isWellFormed($key) || !array_key_exists($key, $this->forSeason($season)['items'])) {
            return null;
        }

        [$type, $identifier] = explode('_', $key, 2);

        $query = Game::query()->whereHas('Round', fn (Builder $round) => $round->where('season_id', $season->id));
        if ($type === 'matchday') {
            $query->where('game_match_day_id', (int) $identifier);
        } else {
            // A round key can contain several rounds (e.g. "round_12-13"). Exactly like the tip page of the website,
            // only the games of the first round are used ((int) "12-13" is 12).
            $query->where('round_id', (int) $identifier);
        }

        return $query->orderBy('date')->orderBy('id');
    }

    /**
     * Filter of the ranking between two matchdays/rounds, with the same meaning as in the web interface
     *
     * @param Season      $season
     * @param string|null $from Key of the first matchday/round, null for the start of the season
     * @param string|null $to   Key of the last matchday/round, null for the end of the season
     *
     * @return array{matchDays: array, rounds: array}|null Null, if a key is not a matchday or round of this season
     */
    public function rankingFilter(Season $season, ?string $from, ?string $to): ?array {
        $items = array_keys($this->forSeason($season)['items']);
        if ($items === []) {
            return ['matchDays' => [], 'rounds' => []];
        }

        $from ??= $items[0];
        $to   ??= $items[array_key_last($items)];

        $fromIndex = array_search($from, $items, true);
        $toIndex   = array_search($to, $items, true);
        if ($fromIndex === false || $toIndex === false || $fromIndex > $toIndex) {
            return null;
        }

        // The whole season needs no filter
        if ($fromIndex === 0 && $toIndex === array_key_last($items)) {
            return ['matchDays' => [], 'rounds' => []];
        }

        // The ranking is filtered either by match days or by rounds
        [$fromType, $fromIdentifier] = explode('_', $from, 2);
        [$toType, $toIdentifier]     = explode('_', $to, 2);
        if ($fromType === 'matchday' && $toType === 'matchday') {
            return ['matchDays' => ['from' => (int) $fromIdentifier, 'to' => (int) $toIdentifier], 'rounds' => []];
        }

        // Rounds of all keys from the start to the end key
        $roundIds = [];
        foreach (array_slice($items, $fromIndex, $toIndex - $fromIndex + 1) as $key) {
            [$type, $identifier] = explode('_', $key, 2);
            if ($type === 'matchday') {
                $ids = Game::query()
                    ->whereHas('Round', fn (Builder $round) => $round->where('season_id', $season->id))
                    ->where('game_match_day_id', (int) $identifier)
                    ->distinct()
                    ->pluck('round_id')
                    ->all();
            } else {
                $ids = array_map('intval', explode('-', $identifier));
            }

            $roundIds = array_merge($roundIds, $ids);
        }

        return ['matchDays' => [], 'rounds' => ['in' => array_values(array_unique($roundIds))]];
    }
}
