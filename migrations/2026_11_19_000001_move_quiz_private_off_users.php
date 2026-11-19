<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Up to 1.1.x the leaderboard choice was a `users.quiz_private` column. Each
 * member who had it on gets an opt-out row, then the column goes. Down puts
 * the column back and fills it from the rows, and the migration that first
 * added it then drops it, so a reset leaves `users` as it found it.
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
        if ($schema->hasColumn('users', 'quiz_private')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->boolean('quiz_private')->default(false);
        });

        $db = $schema->getConnection();
        $db->table('users')
            ->whereIn('id', $db->table('quiz_leaderboard_opt_outs')->select('user_id'))
            ->update(['quiz_private' => true]);
    },
];
