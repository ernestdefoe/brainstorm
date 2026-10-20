<?php

namespace ErnestDefoe\Gauntlet;

use Flarum\Formatter\Formatter;
use Flarum\User\User;

/**
 * A quiz's description, its questions and their explanations go through the
 * forum's own formatter, the one posts use: whatever the forum's editor writes
 * (Markdown, BBCode, Scribe's HTML) is parsed on save into s9e XML and
 * rendered to safe HTML on read. Nothing a member typed reaches the page any
 * other way.
 */
class RichText
{
    public function __construct(protected Formatter $formatter)
    {
    }

    /** Parse what was typed into the XML that is stored; null when empty. */
    public function store(string $source, Quiz $context, User $actor, int $max = 20000): ?string
    {
        $source = trim(mb_substr($source, 0, $max));

        return $source === '' ? null : $this->formatter->parse($source, $context, $actor);
    }

    /** The stored XML as safe HTML, or null. */
    public function html(?string $stored, Quiz $context): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            return $this->formatter->render($stored, $context);
        } catch (\Throwable) {
            // Never let a formatter hiccup take the page down: show the text.
            return '<p>'.nl2br(e(strip_tags($stored))).'</p>';
        }
    }

    /** What the editor is filled with when the quiz is edited. */
    public function source(?string $stored, Quiz $context): string
    {
        return $stored === null || $stored === '' ? '' : (string) $this->formatter->unparse($stored, $context);
    }
}
