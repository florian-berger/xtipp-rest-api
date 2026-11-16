<?php
namespace FlorianBerger\RestApi\Services;

use App\Models\Bonus\Bonus;
use App\Models\Player;
use App\Models\Round\Round;
use App\Models\Round\RoundTeam;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The clubs and players which can be chosen as answer of a bonus question, with the same rules as the select fields of
 * the bonus page of the website (TipController::getSelectClub() and TipController::getSelectPlayer() of xTIPP)
 */
class BonusChoices {
    /**
     * Ids of the teams which can be chosen for a question: the teams of the round of the question, otherwise the teams
     * of all rounds of the season. Null means no restriction (the season has no rounds), like on the website.
     *
     * @param Bonus $bonus
     *
     * @return int[]|null
     */
    public function clubIds(Bonus $bonus): ?array {
        $roundIds       = (int) $bonus->round_id !== 0 ? [(int) $bonus->round_id] : [];
        $seasonRoundIds = Round::where('season_id', $bonus->season_id)->pluck('id')->all();

        if (count($roundIds) === 0 && count($seasonRoundIds) === 0) {
            return null;
        }

        $teamIds = $this->teamIdsOfRounds($roundIds);
        if (count($teamIds) === 0) {
            $teamIds = $this->teamIdsOfRounds($seasonRoundIds);
        }

        return $teamIds;
    }

    /**
     * Clubs/countries which can be chosen for a question, sorted by name
     *
     * @param Bonus       $bonus
     * @param string|null $search Part of the name
     *
     * @return Collection<int, Team>
     */
    public function clubs(Bonus $bonus, ?string $search = null): Collection {
        $query = $this->clubQuery($bonus)->orderBy('name');
        if ($search !== null && $search !== '') {
            $query->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%');
        }

        return $query->get(['id', 'name']);
    }

    /**
     * Check, if a club can be chosen for a question (as answer of a club question or to find the players of a player
     * question)
     *
     * @param Bonus $bonus
     * @param int   $clubId
     *
     * @return bool
     */
    public function isClub(Bonus $bonus, int $clubId): bool {
        return $this->clubQuery($bonus)->whereKey($clubId)->exists();
    }

    /**
     * Current players of a club, sorted by last name
     *
     * @param int         $clubId
     * @param string|null $search Part of the name, as the website shows it
     *
     * @return Collection<int, Player>
     */
    public function players(int $clubId, ?string $search = null): Collection {
        $players = Player::whereHas('Teams', fn ($query) => $query->where('teams.id', $clubId))
            ->orderBy('last_name')
            ->get();

        if ($search !== null && $search !== '') {
            $players = $players->filter(fn (Player $player) => mb_stripos((string) $player->getCorrectName(), $search) !== false);
        }

        return $players->values();
    }

    /**
     * Check, if an answer (id of a team or a player, depending on the type of the question) can be chosen
     *
     * @param Bonus $bonus
     * @param int   $answer
     *
     * @return bool
     */
    public function isValidAnswer(Bonus $bonus, int $answer): bool {
        if ($bonus->type === 'club') {
            return $this->isClub($bonus, $answer);
        }

        // A player of one of the clubs which can be chosen for the question
        $clubIds = $this->clubIds($bonus);

        return Player::whereKey($answer)
            ->whereHas('Teams', function ($query) use ($clubIds) {
                if ($clubIds !== null) {
                    $query->whereIn('teams.id', $clubIds);
                }
            })
            ->exists();
    }

    /**
     * The club which the website shows for a player (the national team in a league of national teams)
     *
     * @param Player $player
     * @param string|null $leagueType
     *
     * @return Team|null
     */
    public function clubOfPlayer(Player $player, ?string $leagueType): ?Team {
        if ($leagueType === 'national') {
            return $player->currentNationTeam();
        }

        return $player->Teams->where('type', 'club')->first();
    }

    /**
     * @param Bonus $bonus
     *
     * @return Builder
     */
    private function clubQuery(Bonus $bonus): Builder {
        $query   = Team::query();
        $clubIds = $this->clubIds($bonus);
        if ($clubIds !== null) {
            $query->whereIn('id', $clubIds);
        }

        return $query;
    }

    /**
     * @param int[] $roundIds
     *
     * @return int[]
     */
    private function teamIdsOfRounds(array $roundIds): array {
        if (count($roundIds) === 0) {
            return [];
        }

        return RoundTeam::whereIn('round_id', $roundIds)
            ->whereNotNull('team_id')
            ->distinct()
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
