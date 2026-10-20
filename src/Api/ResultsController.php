<?php

namespace ErnestDefoe\Gauntlet\Api;

use ErnestDefoe\Gauntlet\Attempt;
use ErnestDefoe\Gauntlet\Attempts;
use ErnestDefoe\Gauntlet\Presenter;
use ErnestDefoe\Gauntlet\Question;
use ErnestDefoe\Gauntlet\Scorer;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET    /api/quizzes/{id}/results         — finished attempts and how each question fared
 * DELETE /api/quizzes/{id}/results/{user}  — reset one member's attempts
 *
 * For people who may edit the quiz. Attempts are shown whatever the members'
 * leaderboard choice: this is the quiz's own marking, not a ranking.
 */
class ResultsController implements RequestHandlerInterface
{
    use Concerns;

    public const PAGE = 50;

    /** Question statistics are taken from at most this many recent attempts. */
    public const SAMPLE = 1000;

    public function __construct(private Presenter $presenter, private Attempts $attempts)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $quiz = $this->editableQuiz($request, $actor);

        if ($request->getMethod() === 'DELETE') {
            $this->attempts->reset($quiz, $this->routeId($request, 'user'));

            return new EmptyResponse(204);
        }

        $offset = max(0, (int) ($request->getQueryParams()['offset'] ?? 0));
        $page = Attempt::query()->where('quiz_id', $quiz->id)->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')->orderByDesc('id')
            ->skip($offset)->take(self::PAGE + 1)
            ->get(['id', 'user_id', 'submitted_at', 'score', 'max_score', 'percent', 'passed', 'duration', 'expired', 'practice']);

        $users = User::query()->whereIn('id', $page->pluck('user_id')->unique()->all())->get()->keyBy('id');

        $attempts = $page->take(self::PAGE)->map(fn (Attempt $a) => [
            'id' => $a->id,
            'user' => ($u = $users->get($a->user_id)) ? $this->presenter->user($u) : null,
            'submittedAt' => $a->submitted_at?->toIso8601String(),
            'score' => $a->score,
            'maxScore' => $a->max_score,
            'percent' => $a->percent,
            'passed' => $a->passed,
            'duration' => $a->duration,
            'expired' => $a->expired,
            'practice' => $a->practice,
        ])->values()->all();

        return new JsonResponse([
            'data' => $attempts,
            'meta' => [
                'next' => $page->count() > self::PAGE ? $offset + self::PAGE : null,
                'questions' => $offset ? null : $this->questionStats($quiz->id, $quiz->partial_credit),
            ],
        ]);
    }

    /**
     * How often each question was answered right, from recent attempts,
     * marked by the same scorer that marked them.
     *
     * @return list<array{id: int, asked: int, correct: float}>
     */
    private function questionStats(int $quizId, bool $partial): array
    {
        $questions = Question::query()->where('quiz_id', $quizId)->orderBy('position')->get()->keyBy('id');
        $asked = $earned = [];

        Attempt::query()->where('quiz_id', $quizId)->whereNotNull('submitted_at')->where('practice', false)
            ->orderByDesc('id')->limit(self::SAMPLE)
            ->get(['id', 'layout', 'answers'])
            ->each(function (Attempt $a) use ($questions, $partial, &$asked, &$earned) {
                foreach ($a->questionIds() as $id) {
                    if ($q = $questions->get($id)) {
                        $asked[$id] = ($asked[$id] ?? 0) + 1;
                        $earned[$id] = ($earned[$id] ?? 0) + Scorer::fraction($q, $a->answers[(string) $id] ?? null, $partial);
                    }
                }
            });

        return $questions->map(fn (Question $q) => [
            'id' => $q->id,
            'asked' => $asked[$q->id] ?? 0,
            'correct' => isset($asked[$q->id]) ? round($earned[$q->id] / $asked[$q->id] * 100, 1) : 0.0,
        ])->values()->all();
    }
}
