<?php

// The parts of ernestdefoe/onramp that Gauntlet uses, for PHPStan only.

namespace ErnestDefoe\Onramp\StepType {
    use Flarum\User\User;

    abstract class StepType
    {
        abstract public function key(): string;

        abstract public function icon(): string;

        /** @return array<class-string, callable(object): mixed> */
        abstract public function events(): array;

        /** @param array<string, mixed> $config */
        abstract public function progress(User $user, array $config): int;

        public function extension(): ?string
        {
            return null;
        }

        /** @return list<string> */
        public function fields(): array
        {
            return [];
        }
    }
}

namespace ErnestDefoe\Onramp\Extend {
    use Flarum\Extend\ExtenderInterface;
    use Flarum\Extension\Extension;
    use Illuminate\Contracts\Container\Container;

    class StepTypes implements ExtenderInterface
    {
        /** @param class-string $type */
        public function add(string $type): self
        {
            return $this;
        }

        public function extend(Container $container, ?Extension $extension = null): void
        {
        }
    }
}
