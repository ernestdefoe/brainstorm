<?php

use Flarum\Database\Migration;

/**
 * Superseded: 1.2 keeps the leaderboard choice in its own table, and
 * 2026_11_19_000001 moves these values there and drops this column again.
 * Kept so the column's add and drop stay a matched pair on reset.
 */
return Migration::addColumns('users', [
    'quiz_private' => ['boolean', 'default' => false],
]);
