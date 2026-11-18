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
     * The answers are stored by xTIPP itself (BonusTip::storeAnswers(), like the bonus page of the website): an answer
     * which is given already keeps its time (the points depend on it), every answer is stored only once, and further
     * rows with the same answer (e.g. of an old import) are removed.
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
            $before = $this->storedAnswers($user, $bonus);

            if (count($answers) === 0) {
                // Not with storeAnswers(): for a question with one answer, the website keeps the answer of an empty
                // field, but for the API an empty list deletes the answers
                BonusTip::where('bonus_id', $bonus->id)->where('user_id', $user->id)->delete();
            } else {
                BonusTip::storeAnswers($bonus, (int) $user->id, $answers);
            }

            if ($this->storedAnswers($user, $bonus) === $before) {
                return self::UNCHANGED;
            }
            if ($before === []) {
                return self::CREATED;
            }

            return count($answers) === 0 ? self::DELETED : self::UPDATED;
        });
    }

    /**
     * The stored answers of a user for a question, to find out what a call changed
     *
     * @param User  $user
     * @param Bonus $bonus
     *
     * @return array<int, int> Id of the row => answer
     */
    private function storedAnswers(User $user, Bonus $bonus): array {
        return BonusTip::where('bonus_id', $bonus->id)
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->pluck('answer', 'id')
            ->map(fn ($answer) => (int) $answer)
            ->all();
    }
}
