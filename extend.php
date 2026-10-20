<?php

/*
 * Gauntlet — quizzes and knowledge tests for Flarum.
 *
 * The shape of the extension: a quiz is written in the forum, dealt to each
 * player as a paper with no answer key on it, marked on the server against
 * the server's own clock, and remembered as one best result per member, which
 * is what leaderboards, badges, points and Onramp read.
 */

use ErnestDefoe\Gauntlet\Api;
use ErnestDefoe\Gauntlet\Event\AttemptFinished;
use ErnestDefoe\Gauntlet\Integration\Badges\BadgesProvider;
use ErnestDefoe\Gauntlet\Integration\Onramp\PassQuizzesStep;
use ErnestDefoe\Gauntlet\Listener\PayRewards;
use ErnestDefoe\Gauntlet\Access;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // The pages, the player and the editor are their own chunks.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        // Static paths before {id}: FastRoute refuses a static route that an
        // earlier variable one already matches.
        ->route('/quizzes', 'gauntlet.index')
        ->route('/quizzes/new', 'gauntlet.new')
        ->route('/quizzes/leaderboard', 'gauntlet.leaderboard')
        ->route('/quizzes/{id:\d+(?:-[^/]*)?}', 'gauntlet.quiz')
        ->route('/quizzes/{id:\d+}/edit', 'gauntlet.edit'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->get('/quizzes', 'gauntlet.quizzes.index', Api\ListQuizzesController::class)
        ->post('/quizzes', 'gauntlet.quizzes.create', Api\SaveQuizController::class)
        ->get('/quiz-editor', 'gauntlet.editor', Api\EditorController::class)
        ->get('/quiz-leaderboard', 'gauntlet.leaderboard', Api\LeaderboardController::class)
        ->get('/quizzes/{id:[0-9]+}', 'gauntlet.quizzes.show', Api\ShowQuizController::class)
        ->patch('/quizzes/{id:[0-9]+}', 'gauntlet.quizzes.update', Api\SaveQuizController::class)
        ->delete('/quizzes/{id:[0-9]+}', 'gauntlet.quizzes.delete', Api\DeleteQuizController::class)
        ->post('/quizzes/{id:[0-9]+}/attempts', 'gauntlet.attempts.start', Api\StartAttemptController::class)
        ->get('/quizzes/{id:[0-9]+}/leaderboard', 'gauntlet.quizzes.leaderboard', Api\LeaderboardController::class)
        ->get('/quizzes/{id:[0-9]+}/results', 'gauntlet.quizzes.results', Api\ResultsController::class)
        ->delete('/quizzes/{id:[0-9]+}/results/{user:[0-9]+}', 'gauntlet.quizzes.reset', Api\ResultsController::class)
        ->get('/quiz-attempts/{id:[0-9]+}', 'gauntlet.attempts.show', Api\AttemptController::class)
        ->patch('/quiz-attempts/{id:[0-9]+}', 'gauntlet.attempts.save', Api\AttemptController::class)
        ->post('/quiz-attempts/{id:[0-9]+}/submit', 'gauntlet.attempts.submit', Api\AttemptController::class),

    (new Extend\Event())
        ->listen(AttemptFinished::class, PayRewards::class),

    (new Extend\Settings())
        ->default('ernestdefoe-gauntlet.show_nav', true)
        ->serializeToForum('gauntletShowNav', 'ernestdefoe-gauntlet.show_nav', 'boolval'),

    // Both are permissions the actor's groups were already loaded for: no query.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewQuizzes')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('gauntlet.view')),
            Schema\Boolean::make('canCreateQuizzes')
                ->get(fn ($forum, Context $context) => Access::canCreate($context->getActor())),
        ]),

    (new Extend\Model(User::class))
        ->cast('quiz_private', 'boolean'),

    // The member's own "keep me off quiz leaderboards" choice, on their own
    // user only: read and written by them, invisible to everyone else.
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('quizPrivate')
                ->property('quiz_private')
                ->visible(fn (User $user, Context $context) => $context->getActor()->id === $user->id)
                ->writable(fn (User $user, Context $context) => $context->getActor()->id === $user->id),
        ]),

    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-badges', fn () => [
            (new Extend\ServiceProvider())->register(BadgesProvider::class),
        ])
        ->whenExtensionEnabled('ernestdefoe-onramp', fn () => [
            (new \ErnestDefoe\Onramp\Extend\StepTypes())->add(PassQuizzesStep::class),
        ]),
];
