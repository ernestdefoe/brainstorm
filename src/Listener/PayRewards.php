<?php

namespace ErnestDefoe\Gauntlet\Listener;

use ErnestDefoe\Gauntlet\Event\AttemptFinished;
use ErnestDefoe\Gauntlet\Rewards;

/** A first pass pays the quiz's reward. */
class PayRewards
{
    public function __construct(private Rewards $rewards)
    {
    }

    public function handle(AttemptFinished $event): void
    {
        if ($event->firstPass) {
            $this->rewards->pay($event->attempt->quiz, $event->user);
        }
    }
}
