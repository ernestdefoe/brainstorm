<?php

namespace ErnestDefoe\Gauntlet;

use Flarum\User\User;

/**
 * Who may do what to a quiz. Every controller asks here, so the rules live in
 * one place.
 */
class Access
{
    public static function canView(User $actor, Quiz $quiz): bool
    {
        return $actor->hasPermission('gauntlet.view')
            && ($quiz->isPublished() || self::canEdit($actor, $quiz));
    }

    public static function canCreate(User $actor): bool
    {
        return ! $actor->isGuest() && ($actor->hasPermission('gauntlet.create') || $actor->hasPermission('gauntlet.manage'));
    }

    /** The author while they may still write quizzes, or a manager. */
    public static function canEdit(User $actor, Quiz $quiz): bool
    {
        if ($actor->isGuest()) {
            return false;
        }

        return $actor->hasPermission('gauntlet.manage')
            || ($quiz->user_id === $actor->id && $actor->hasPermission('gauntlet.create'));
    }

    /**
     * Attaching points or a badge. Kept from authors on purpose: an author who
     * could attach rewards could write a quiz only they can pass and pay
     * themselves.
     */
    public static function canReward(User $actor): bool
    {
        return $actor->hasPermission('gauntlet.reward');
    }

    /** Editors may always try their quiz, drafts included: as practice. */
    public static function canTake(User $actor, Quiz $quiz): bool
    {
        if (self::canEdit($actor, $quiz)) {
            return $quiz->question_count > 0;
        }

        return ! $actor->isGuest()
            && $actor->hasPermission('gauntlet.view')
            && $actor->hasPermission('gauntlet.take')
            && $quiz->isOpen();
    }
}
