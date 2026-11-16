<?php
namespace FlorianBerger\RestApi\Services;

use App\Models\Bonus\Bonus;
use App\Models\Bonus\BonusTip;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Store the answers of a user for a bonus question, with the same rules as the bonus page of the website
 * (TipController::storeBonus() of xTIPP)
 */
class BonusUpdater {
    public const CREATED   = 'created';
    public const UPDATED   = 'updated';
    public const UNCHANGED = 'unchanged';
    public const DELETED   = 'deleted';

    /**
     * Check, if the question still accepts answers (at least one deadline for points is in the future)
     *
     * @param Bonus $bonus
     *
     * @return bool
     */
    public function isOpen(Bonus $bonus): bool {
        return !$bonus->reachedTipDeadline();
    }

    /**
     * Replace the answers of a user. The question must be open, see isOpen(). The answers must be valid and distinct,
     * and there must be not more than "possible_answers".
     *
     * Like on the website, an answer which is given already stays untouched: the points depend on the time of the
     * answer, so storing it again would lose points.
     *
     * @param User  $user
     * @param Bonus $bonus
     * @param int[] $answers An empty list deletes all answers
     *
     * @return string One of the constants of this class
     */
    public function store(User $user, Bonus $bonus, array $answers): string {
        // The answers have no unique index, so parallel calls must not create two answers
        return Cache::lock('rest-api-bonus:' . $user->id . ':' . $bonus->id, 10)->block(5, function () use ($user, $bonus, $answers) {
            $existing = BonusTip::where('bonus_id', $bonus->id)->where('user_id', $user->id)->orderBy('id')->get();
            $changed  = false;

            if ((int) $bonus->possible_answers > 1 || count($answers) === 0) {
                // Keep the answers which are given again, create the new ones and delete the others
                $keep = [];
                foreach ($answers as $answer) {
                    $tip = $existing->first(fn (BonusTip $tip) => (int) $tip->answer === $answer && !in_array($tip->id, $keep, true));
                    if ($tip !== null) {
                        $keep[] = $tip->id;
                        continue;
                    }

                    BonusTip::create(['bonus_id' => $bonus->id, 'user_id' => $user->id, 'answer' => $answer]);
                    $changed = true;
                }

                foreach ($existing as $tip) {
                    if (!in_array($tip->id, $keep, true)) {
                        $tip->delete();
                        $changed = true;
                    }
                }
            } else {
                // One answer: change it, if it is different
                $answer = $answers[0];
                $tip    = $existing->first();
                if ($tip === null) {
                    BonusTip::create(['bonus_id' => $bonus->id, 'user_id' => $user->id, 'answer' => $answer]);
                    $changed = true;
                } elseif ((int) $tip->answer !== $answer) {
                    $tip->update(['answer' => $answer]);
                    $changed = true;
                }
            }

            if (!$changed) {
                return self::UNCHANGED;
            }
            if ($existing->isEmpty()) {
                return self::CREATED;
            }

            return count($answers) === 0 ? self::DELETED : self::UPDATED;
        });
    }
}
