<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

final readonly class TimezoneResolutionAttempt
{
    public function __construct(
        public string $resolver,
        public ResolutionAttemptOutcome $outcome,
        public ?string $source = null,
        public int $durationMicroseconds = 0,
    ) {
        if ('' === $resolver) {
            throw new \InvalidArgumentException('The resolver trace identifier cannot be empty.');
        }
        if (0 > $durationMicroseconds) {
            throw new \InvalidArgumentException('The resolver attempt duration cannot be negative.');
        }
    }
}
