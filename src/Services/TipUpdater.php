<?php
namespace FlorianBerger\RestApi\Services;

use App\Jobs\SendTipConfirmationEmail;
use App\Models\Game\Game;
use App\Models\Tip\Tip;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Create, change and delete the tip of a user, with the same rules as the tip page of the website
 */
class TipUpdater {
    public const CREATED   = 'created';
    public const UPDATED   = 'updated';
    public const UNCHANGED = 'unchanged';
    public const DELETED   = 'deleted';

    /**
     * Check, if the game still accepts tips
     *
     * @param Game $game
     *
     * @return bool
     */
    public function isOpen(Game $game): bool {
        return !$game->isNotPlannedAnymore() && !$game->reachedDeadline();
    }

    /**
     * Check, if a draw is not allowed as tip (the website refuses it for knockout games, if the result of the
     * extra time counts)
     *
     * @param Game $game
     * @param int  $homeGoals
     * @param int  $guestGoals
     *
     * @return bool
     */
    public function isForbiddenDraw(Game $game, int $homeGoals, int $guestGoals): bool {
        return (int) config('xtipp.points-calculation-playtime') === 2
            && $game->Round?->mode === 'ko'
            && $homeGoals === $guestGoals;
    }

    /**
     * Store the tip of a user. The game must be open, see isOpen().
     *
     * @param User     $user
     * @param Game     $game
     * @param int|null $homeGoals  Null for both goals deletes the tip, like on the website
     * @param int|null $guestGoals
     *
     * @return string One of the constants of this class
     */
    public function store(User $user, Game $game, ?int $homeGoals, ?int $guestGoals): string {
        // The tips have no unique index, so parallel calls must not create two tips
        $result = Cache::lock('rest-api-tip:' . $user->id . ':' . $game->id, 10)->block(5, function () use ($user, $game, $homeGoals, $guestGoals) {
            $tip = Tip::where('user_id', $user->id)->where('game_id', $game->id)->first();

            if ($homeGoals === null || $guestGoals === null) {
                if ($tip === null) {
                    return self::UNCHANGED;
                }

                $tip->delete();

                return self::DELETED;
            }

            if ($tip === null) {
                Tip::create(['user_id' => $user->id, 'game_id' => $game->id, 'home_goals' => $homeGoals, 'guest_goals' => $guestGoals]);

                return self::CREATED;
            }

            if ((int) $tip->home_goals === $homeGoals && (int) $tip->guest_goals === $guestGoals) {
                return self::UNCHANGED;
            }

            $tip->update(['home_goals' => $homeGoals, 'guest_goals' => $guestGoals]);

            return self::UPDATED;
        });

        // The confirmation is sent delayed for all changes together, like for the website
        if ($result !== self::UNCHANGED && $user->email_tip_confirmation === 1) {
            SendTipConfirmationEmail::schedule($user, [$game->id]);
        }

        return $result;
    }
}
