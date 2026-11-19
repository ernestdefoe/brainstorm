<?php

namespace ErnestDefoe\Brainstorm\Integration\Badges;

use ErnestDefoe\Brainstorm\Event\AttemptFinished;
use ErnestDefoe\Brainstorm\Integration\Rewards\BadgeSource;
use Flarum\Foundation\AbstractServiceProvider;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\Trigger\MetricManager;
use FoF\Badges\Trigger\TriggerEvaluator;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Brainstorm's two triggers in FoF Badges, the listener that evaluates them,
 * and FoF Badges as the source of quiz reward badges.
 *
 * 🚨 FoF Badges' own listener only recognises the events it knows, so
 * Brainstorm asks the evaluator itself — the same two calls that listener makes.
 *
 * Registered from extend.php only when fof-badges is enabled.
 */
class BadgesProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(BadgeSource::class, FofBadges::class);

        $this->container->extend(MetricManager::class, function (MetricManager $manager) {
            $manager->register($this->container->make(QuizzesPassedMetric::class));
            $manager->register($this->container->make(PerfectScoresMetric::class));

            return $manager;
        });
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(AttemptFinished::class, function (AttemptFinished $event): void {
            $evaluator = $this->container->make(TriggerEvaluator::class);
            $awarder = $this->container->make(BadgeAwarder::class);

            foreach ($evaluator->evaluateForEvent($event->user, AttemptFinished::class) as $badge) {
                $awarder->award($event->user, $badge);
            }
        });
    }
}
