<?php

namespace ErnestDefoe\Brainstorm\Search;

use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;

/**
 * filter[q]: quizzes whose title contains the words, in any case.
 *
 * A contains-match, so "capitals" finds "European capitals". It scans
 * titles, which is fine for a forum's quizzes (hundreds, not millions) and
 * only runs when someone searches the quiz picker or list.
 *
 * @extends AbstractFulltextFilter<DatabaseSearchState>
 */
class QuizFulltext extends AbstractFulltextFilter
{
    public function search(SearchState $state, string $value): void
    {
        /** @var DatabaseSearchState $state */
        // LIKE's wildcards are dropped, not escaped: SQLite has no default escape character.
        $words = trim(str_replace(['%', '_', '\\'], ' ', mb_substr(trim($value), 0, 100)));

        if ($words !== '') {
            // whereLike is case-insensitive on every database (ILIKE on PostgreSQL).
            $state->getQuery()->whereLike('quizzes.title', '%'.$words.'%');
        }
    }
}
