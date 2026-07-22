<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

use Symfony\Component\HttpFoundation\Request;

final readonly class TimezoneResolutionTrace
{
    public const REQUEST_ATTRIBUTE = '_lunetics_timezone_resolution_trace';

    /**
     * @param list<TimezoneResolutionAttempt> $attempts
     */
    public function __construct(
        public array $attempts,
        public ?TimezoneResolution $selected,
    ) {
    }

    public function attachTo(Request $request): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $this);
    }

    public static function fromRequest(Request $request): ?self
    {
        $trace = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        return $trace instanceof self ? $trace : null;
    }
}
