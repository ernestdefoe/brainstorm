<?php

namespace ErnestDefoe\Gauntlet;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates and updates a quiz and its questions from the editor's payload.
 *
 * Every value is checked and clamped here; nothing in the payload reaches a
 * column it was not meant for. Rewards are only touched by actors who may
 * attach them: for anyone else they keep whatever they were.
 */
class Saver
{
    public const MAX_QUESTIONS = 200;
    public const MAX_OPTIONS = 10;
    public const MAX_ACCEPTED = 20;

    public function __construct(
        private ConnectionInterface $db,
        private RichText $rich,
        private TranslatorInterface $translator,
        private Rewards $rewards
    ) {
    }

    /** @param array<string, mixed> $data */
    public function save(?Quiz $quiz, array $data, User $actor): Quiz
    {
        $quiz ??= new Quiz();
        $isNew = ! $quiz->exists;

        $title = trim((string) Arr::get($data, 'title', $quiz->title ?? ''));
        if ($title === '' || mb_strlen($title) > 200) {
            $this->fail('title', 'title');
        }

        $status = Arr::get($data, 'status', $quiz->status ?? 'draft');
        if (! in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        $reveal = Arr::get($data, 'reveal', $quiz->reveal ?? 'answers');
        if (! in_array($reveal, Quiz::REVEALS, true)) {
            $reveal = 'answers';
        }

        $opensAt = $this->date(Arr::get($data, 'opensAt'));
        $closesAt = $this->date(Arr::get($data, 'closesAt'));
        if ($opensAt && $closesAt && $closesAt->lte($opensAt)) {
            $this->fail('closesAt', 'dates');
        }

        $questions = $this->questions(Arr::get($data, 'questions', []));

        if ($status === 'published' && ! $questions) {
            $this->fail('status', 'publish_empty');
        }

        return $this->db->transaction(function () use ($quiz, $isNew, $data, $actor, $title, $status, $reveal, $opensAt, $closesAt, $questions) {
            if ($isNew) {
                $quiz->user_id = $actor->id;
            }

            $quiz->title = $title;
            $quiz->slug = Str::limit(Str::slug($title), 190, '') ?: 'quiz';
            $quiz->status = $status;
            $quiz->time_limit = $this->int($data, 'timeLimit', 10, 86400);
            $quiz->pass_mark = $this->int($data, 'passMark', 0, 100) ?? 70;
            $quiz->max_attempts = $this->int($data, 'maxAttempts', 1, 1000);
            $quiz->question_pick = $this->int($data, 'questionPick', 1, self::MAX_QUESTIONS);
            $quiz->shuffle_questions = (bool) Arr::get($data, 'shuffleQuestions', false);
            $quiz->shuffle_options = (bool) Arr::get($data, 'shuffleOptions', true);
            $quiz->partial_credit = (bool) Arr::get($data, 'partialCredit', false);
            $quiz->reveal = $reveal;
            $quiz->leaderboard = (bool) Arr::get($data, 'leaderboard', true);
            $quiz->opens_at = $opensAt;
            $quiz->closes_at = $closesAt;
            $quiz->question_count = count($questions);

            if ($status === 'published' && ! $quiz->published_at) {
                $quiz->published_at = Carbon::now();
            }

            if (Access::canReward($actor)) {
                $quiz->reward_points = $this->int($data, 'rewardPoints', -100000, 100000) ?? 0;
                $quiz->reward_badge_id = $this->badge(Arr::get($data, 'rewardBadgeId'));
            } elseif ($isNew) {
                $quiz->reward_points = 0;
                $quiz->reward_badge_id = null;
            }

            // A new quiz is saved first: the formatter is given it as context.
            if ($isNew) {
                $quiz->save();
            }
            $quiz->description = $this->rich->store((string) Arr::get($data, 'description', ''), $quiz, $actor);
            $quiz->save();

            $this->syncQuestions($quiz, $questions, $actor);

            return $quiz;
        });
    }

    /**
     * Questions keep their ids across saves (attempts point at them by id),
     * new ones are added and the ones left out are deleted.
     *
     * @param list<array<string, mixed>> $questions
     */
    private function syncQuestions(Quiz $quiz, array $questions, User $actor): void
    {
        $existing = Question::query()->where('quiz_id', $quiz->id)->get()->keyBy('id');
        $kept = [];

        foreach ($questions as $position => $q) {
            $question = isset($q['id']) ? $existing->get($q['id']) : null;
            $question ??= new Question();

            $question->quiz_id = $quiz->id;
            $question->position = $position;
            $question->type = $q['type'];
            $question->body = (string) $this->rich->store($q['body'], $quiz, $actor, 5000);
            $question->image_url = $q['imageUrl'];
            $question->points = $q['points'];
            $question->options = $q['options'];
            $question->explanation = $this->rich->store($q['explanation'], $quiz, $actor, 5000);
            $question->save();

            $kept[] = $question->id;
        }

        Question::query()->where('quiz_id', $quiz->id)->whereNotIn('id', $kept ?: [0])->delete();
        $quiz->unsetRelation('questions');
    }

    /** @return list<array<string, mixed>> */
    private function questions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        if (count($raw) > self::MAX_QUESTIONS) {
            $this->fail('questions', 'too_many_questions', ['{max}' => self::MAX_QUESTIONS]);
        }

        $out = [];
        foreach (array_values($raw) as $i => $q) {
            if (! is_array($q)) {
                continue;
            }

            $n = ['{number}' => $i + 1];
            $type = $q['type'] ?? null;
            if (! in_array($type, Question::TYPES, true)) {
                $this->fail('questions', 'question_type', $n);
            }

            $body = trim((string) ($q['body'] ?? ''));
            if ($body === '' || mb_strlen($body) > 5000) {
                $this->fail('questions', 'question_body', $n);
            }

            $out[] = [
                'id' => isset($q['id']) && is_numeric($q['id']) ? (int) $q['id'] : null,
                'type' => $type,
                'body' => $body,
                'imageUrl' => $this->image($q['imageUrl'] ?? null, $n),
                'points' => max(1, min(100, (int) ($q['points'] ?? 1))),
                'explanation' => mb_substr(trim((string) ($q['explanation'] ?? '')), 0, 5000),
                'options' => $this->options($type, $q['options'] ?? [], $n),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, int> $n
     * @return list<array{id: string, text: string, correct: bool}>
     */
    private function options(string $type, mixed $raw, array $n): array
    {
        $raw = is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];

        if ($type === 'boolean') {
            // Two fixed options; the payload only says which one is right.
            $true = false;
            foreach ($raw as $o) {
                if (($o['id'] ?? null) === 'true') {
                    $true = (bool) ($o['correct'] ?? false);
                }
            }

            return [
                ['id' => 'true', 'text' => '', 'correct' => $true],
                ['id' => 'false', 'text' => '', 'correct' => ! $true],
            ];
        }

        $out = [];
        $seen = [];
        foreach ($raw as $o) {
            $text = trim((string) ($o['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > 300) {
                $this->fail('questions', 'option_length', $n);
            }

            // Kept from the editor when well-formed, so a saved attempt's
            // answers still name the same options; otherwise a fresh random id
            // that says nothing about the option's place or truth.
            $id = (string) ($o['id'] ?? '');
            if (! preg_match('/^[a-z0-9]{6,16}$/', $id) || isset($seen[$id])) {
                $id = bin2hex(random_bytes(4));
            }
            $seen[$id] = true;

            $out[] = ['id' => $id, 'text' => $text, 'correct' => $type === 'text' || ! empty($o['correct'])];
        }

        $max = $type === 'text' ? self::MAX_ACCEPTED : self::MAX_OPTIONS;
        $min = $type === 'text' ? 1 : 2;
        if (count($out) < $min || count($out) > $max) {
            $this->fail('questions', $type === 'text' ? 'accepted_count' : 'option_count', $n + ['{min}' => $min, '{max}' => $max]);
        }

        if (! array_filter($out, fn ($o) => $o['correct'])) {
            $this->fail('questions', 'no_correct', $n);
        }

        return $out;
    }

    /** @param array<string, int> $n */
    private function image(mixed $url, array $n): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // http(s) only, no whitespace or control characters anywhere: the
        // value lands in an <img src>, and a scheme check on a value with a
        // control character in it is a check that can be walked around.
        if (mb_strlen($url) > 500 || preg_match('/[\x00-\x20\x7f]/', $url) || ! preg_match('#^https?://[^/]#i', $url)) {
            $this->fail('questions', 'image_url', $n);
        }

        return $url;
    }

    private function badge(mixed $id): ?int
    {
        if (! is_numeric($id) || (int) $id <= 0 || ! $this->rewards->badgesAvailable()) {
            return null;
        }

        return $this->db->table('fof_badges')->where('id', (int) $id)->exists() ? (int) $id : null;
    }

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $min, int $max): ?int
    {
        $value = Arr::get($data, $key);
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            $this->fail('opensAt', 'dates');
        }
    }

    /** @param array<string, int|string> $params */
    private function fail(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => $this->translator->trans('ernestdefoe-gauntlet.api.'.$key, $params)]);
    }
}
