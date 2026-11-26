<?php

namespace ErnestDefoe\Brainstorm\Access;

use ErnestDefoe\Brainstorm\Integration\Courses\CourseGate;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Published quizzes for anyone who may view quizzes, plus the drafts this
 * actor may edit. A course-only quiz also needs the course gate's yes (an
 * enrolled member, the course's instructors, course managers); its own
 * editors always see it.
 */
class ScopeQuizVisibility
{
    public function __construct(private CourseGate $courses)
    {
    }

    public function __invoke(User $actor, Builder $query): void
    {
        $table = $query->getModel()->getTable();

        if (! $actor->hasPermission('brainstorm.view')) {
            $query->whereIn("$table.id", []);

            return;
        }

        if ($actor->hasPermission('brainstorm.manage')) {
            return;
        }

        $query->where(function (Builder $q) use ($actor, $table) {
            $q->where(function (Builder $open) use ($actor, $table) {
                $open->where("$table.status", 'published');
                $this->courses->scope($open, $actor, $table);
            });

            if (! $actor->isGuest() && $actor->hasPermission('brainstorm.create')) {
                $q->orWhere("$table.user_id", $actor->id);
            }
        });
    }
}
