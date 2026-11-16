<?php
namespace FlorianBerger\RestApi\Services;

use App\Helper\TipPointCalculation;
use App\Models\Bonus\Bonus;
use App\Models\Bonus\BonusPoint;
use App\Models\Bonus\BonusTip;
use App\Models\Game\Game;
use App\Models\League;
use App\Models\Player;
use App\Models\Season\Season;
use App\Models\Team;
use App\Models\Tip\Tip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Maps the models of xTIPP to the JSON of the API. The field names are part of the API, so no model is returned as it is
 * (this also keeps personal data like e-mail addresses out of the answers).
 */
class Presenter {
    /**
     * @param BonusChoices $bonusChoices
     */
    public function __construct(private readonly BonusChoices $bonusChoices) {
    }

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

    /**
     * A bonus question with the own answers
     *
     * @param Bonus                    $bonus      Needs the relation "bonusPoints"
     * @param Collection<int, BonusTip> $answers    The own answers
     * @param string|null              $leagueType Type of the league ("national" shows the national team of a player)
     *
     * @return array<string, mixed>
     */
    public function bonus(Bonus $bonus, Collection $answers, ?string $leagueType): array {
        return [
            'id'               => $bonus->id,
            'season_id'        => $bonus->season_id,
            'question'         => $bonus->question,
            'type'             => $bonus->type,
            'possible_answers' => (int) $bonus->possible_answers,
            'end'              => $bonus->bonus_end !== null ? Carbon::parse($bonus->bonus_end)->toIso8601String() : null,
            'points'           => $bonus->bonusPoints->map(fn (BonusPoint $point) => [
                'points' => (int) $point->point,
                'until'  => $point->point_end?->toIso8601String(),
            ])->values()->all(),
            'deadline_reached' => $bonus->reachedTipDeadline(),
            'answers'          => $answers->map(fn (BonusTip $tip) => $this->bonusAnswer($bonus, $tip, $leagueType))->values()->all(),
        ];
    }

    /**
     * One answer of a bonus question, with the club and the player like on the website
     *
     * @param Bonus       $bonus
     * @param BonusTip    $tip
     * @param string|null $leagueType
     *
     * @return array<string, mixed>
     */
    public function bonusAnswer(Bonus $bonus, BonusTip $tip, ?string $leagueType): array {
        $club   = null;
        $player = null;

        if ($bonus->type === 'club') {
            $team = Team::withTrashed()->find($tip->answer);
            $club = $team !== null ? ['id' => $team->id, 'name' => $team->name] : null;
        } else {
            $playerModel = Player::withTrashed()->find($tip->answer);
            if ($playerModel !== null) {
                $player = ['id' => $playerModel->id, 'name' => $playerModel->getCorrectName()];
                $team   = $this->bonusChoices->clubOfPlayer($playerModel, $leagueType);
                $club   = $team !== null ? ['id' => $team->id, 'name' => $team->name] : null;
            }
        }

        // Points only when the administrator has entered the answer, like on the website
        $tip->setRelation('bonus', $bonus);
        $points = $tip->getPoints();

        return [
            'answer'     => (int) $tip->answer,
            'club'       => $club,
            'player'     => $player,
            'points'     => $points['roundId'] !== null ? (int) $points['bonusPoints'] : null,
            'updated_at' => ($tip->updated_at ?? $tip->created_at)?->toIso8601String(),
        ];
    }

    /**
     * A club or country which can be chosen as answer
     *
     * @param Team $team
     *
     * @return array<string, mixed>
     */
    public function bonusClub(Team $team): array {
        return ['id' => $team->id, 'name' => $team->name];
    }

    /**
     * A player who can be chosen as answer
     *
     * @param Player $player
     *
     * @return array<string, mixed>
     */
    public function bonusPlayer(Player $player): array {
        return ['id' => $player->id, 'name' => $player->getCorrectName()];
    }
}
