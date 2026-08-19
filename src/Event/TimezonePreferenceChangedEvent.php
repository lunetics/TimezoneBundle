<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Event;

use Lunetics\TimezoneBundle\Storage\TimezonePreference;
use Symfony\Component\HttpFoundation\Request;

final readonly class TimezonePreferenceChangedEvent
{
    public function __construct(public Request $request, public ?TimezonePreference $previous, public ?TimezonePreference $current)
    {
        if (null === $previous && null === $current) {
            throw new \InvalidArgumentException('A preference change requires a previous or current value.');
        }
    }
}
