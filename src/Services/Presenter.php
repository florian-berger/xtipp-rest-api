<?php
namespace FlorianBerger\RestApi\Services;

use App\Helper\TipPointCalculation;
use App\Models\Game\Game;
use App\Models\League;
use App\Models\Season\Season;
use App\Models\Tip\Tip;
use App\Models\User;

/**
 * Maps the models of xTIPP to the JSON of the API. The field names are part of the API, so no model is returned as it is
 * (this also keeps personal data like e-mail addresses out of the answers).
 */
class Presenter {
    /**
     * @param League $league
     *
     * @return array<string, mixed>
     */
    public function league(League $league): array {
        return [
            'id'                => $league->id,
            'name'              => $league->name,
            'type'              => $league->type,
            'gender'            => $league->gender,
            'code'              => $league->code,
            'current_season_id' => $league->currentSeason()?->id,
        ];
    }

    /**
     * @param Season $season
     *
     * @return array<string, mixed>
     */
    public function season(Season $season): array {
        return [
            'id'        => $season->id,
            'league_id' => $season->league_id,
            'name'      => $season->name,
        ];
    }

    /**
     * Only the id and the name, which the website shows, too
     *
     * @param User|null $user
     * @param int|null  $fallbackId
     *
     * @return array<string, mixed>
     */
    public function user(?User $user, ?int $fallbackId = null): array {
        return [
            'id'   => $user?->id ?? $fallbackId,
            'name' => $user?->getFullName(),
        ];
    }

    /**
     * @param Game $game Needs the relations "homeTeam", "awayTeam" and "Round"
     *
     * @return array<string, mixed>
     */
    public function game(Game $game): array {
        return [
            'id'                => $game->id,
            'round'             => ['id' => $game->round_id, 'name' => $game->Round?->name],
            'kickoff'           => $game->date?->toIso8601String(),
            'status'            => $game->status,
            'home'              => $this->team($game, 'home'),
            'away'              => $this->team($game, 'away'),
            'result'            => $this->result($game),
            'deadline_reached'  => $game->isNotPlannedAnymore() || $game->reachedDeadline(),
        ];
    }

    /**
     * @param Game   $game
     * @param string $side "home" or "away"
     *
     * @return array<string, mixed>
     */
    private function team(Game $game, string $side): array {
        $team = $side === 'home' ? $game->homeTeam : $game->awayTeam;

        return [
            'id'          => $team?->id,
            'name'        => $team?->name,
            'placeholder' => $side === 'home' ? $game->home_placeholder : $game->away_placeholder,
        ];
    }

    /**
     * The result which is used for the points (depends on the setting for the playing time), only when the game started
     *
     * @param Game $game
     *
     * @return array{home: int, away: int}|null
     */
    public function result(Game $game): ?array {
        if (!$game->isNotPlannedAnymore()) {
            return null;
        }

        $playtime = (int) config('xtipp.points-calculation-playtime', 0);

        return ['home' => $game->homeTeamGoals($playtime), 'away' => $game->awayTeamGoals($playtime)];
    }

    /**
     * Points of a tip, null as long as the game has not started
     *
     * @param array{home: int, away: int}|null $result Result of the game, see result() (get it once per game)
     * @param Tip|null                         $tip
     *
     * @return int|null
     */
    public function points(?array $result, ?Tip $tip): ?int {
        if ($result === null) {
            return null;
        }

        if ($tip === null) {
            return 0;
        }

        $calculated = TipPointCalculation::calculatePointsForGame(
            ['home' => $result['home'], 'guest' => $result['away']],
            ['home' => $tip->home_goals, 'guest' => $tip->guest_goals]
        );

        return (int) $calculated['points'];
    }

    /**
     * @param array{home: int, away: int}|null $result Result of the game, see result()
     * @param Tip|null                         $tip
     *
     * @return array<string, mixed>|null
     */
    public function tip(?array $result, ?Tip $tip): ?array {
        if ($tip === null) {
            return null;
        }

        return [
            'home_goals'  => (int) $tip->home_goals,
            'guest_goals' => (int) $tip->guest_goals,
            'points'      => $this->points($result, $tip),
        ];
    }
}
