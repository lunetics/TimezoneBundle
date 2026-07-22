<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Context;

use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;

interface CurrentTimezoneProviderInterface
{
    public function getResolution(): TimezoneResolution;
    public function getResolutionForRequest(Request $request): TimezoneResolution;
    public function getTimezone(): TimezoneId;
    public function getDateTimeZone(): \DateTimeZone;
}
