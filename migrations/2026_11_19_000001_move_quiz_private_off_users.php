<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Up to 1.1.x the leaderboard choice was a `users.quiz_private` column. On a
 * forum upgraded from it, each member who had it on gets an opt-out row and
 * the column goes; a new install never had it, so there is nothing to do.
 *
 * Down leaves `users` as it is: 1.2 neither adds nor reads that column, and
 * the opt-out table's own migration takes the choices away on uninstall.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'quiz_private')) {
            return;
        }

        $db = $schema->getConnection();
        $now = Carbon::now();

        $db->table('users')->where('quiz_private', true)->orderBy('id')->select('id')
            ->chunkById(500, function ($users) use ($db, $now) {
                $db->table('quiz_leaderboard_opt_outs')->insertOrIgnore(
                    $users->map(fn ($u) => ['user_id' => $u->id, 'created_at' => $now])->all()
                );
            });

        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('quiz_private');
        });
    },
    'down' => function (Builder $schema) {
        // Nothing to restore: see above.
    },
];
