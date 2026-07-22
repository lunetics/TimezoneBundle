<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\MaxMind;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

interface MaxMindCityReaderInterface
{
    public function timezoneForIp(string $ipAddress): TimezoneId|string|null;
}
