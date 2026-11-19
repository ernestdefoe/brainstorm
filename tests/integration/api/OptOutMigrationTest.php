<?php

namespace ErnestDefoe\Brainstorm\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/** 1.1.x kept the leaderboard choice on `users`; upgrading moves it to its own table. */
class OptOutMigrationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-brainstorm');
        $this->prepareDatabase([User::class => [$this->normalUser(), ['id' => 3, 'username' => 'carol', 'email' => 'carol@machine.local', 'is_email_confirmed' => 1]]]);
    }

    #[Test]
    public function the_choice_moves_off_users_and_back(): void
    {
        $schema = $this->database()->getSchemaBuilder();
        $move = require __DIR__.'/../../../migrations/2026_11_19_000001_move_quiz_private_off_users.php';
        $this->assertFalse($schema->hasColumn('users', 'quiz_private'), 'an installed forum has no column on users');

        // A forum as 1.1.x left it: carol had opted out.
        $this->database()->table('quiz_leaderboard_opt_outs')->insert(['user_id' => 2]);
        $move['down']($schema);
        $this->assertTrue((bool) $this->database()->table('users')->where('id', 2)->value('quiz_private'), 'down fills the column back in');
        $this->database()->table('quiz_leaderboard_opt_outs')->delete();
        $this->database()->table('users')->where('id', 3)->update(['quiz_private' => true]);

        $move['up']($schema);

        $this->assertFalse($schema->hasColumn('users', 'quiz_private'));
        $this->assertSame([2, 3], $this->database()->table('quiz_leaderboard_opt_outs')->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all());
    }
}
