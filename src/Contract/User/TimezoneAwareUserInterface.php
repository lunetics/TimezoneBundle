<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Contract\User;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

interface TimezoneAwareUserInterface
{
    public function getTimezone(): TimezoneId|string|null;
}
