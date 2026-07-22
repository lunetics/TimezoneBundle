<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Event;

use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Symfony\Component\HttpFoundation\Request;

final readonly class TimezoneResolvedEvent
{
    public function __construct(public Request $request, public TimezoneResolution $resolution)
    {
    }
}
