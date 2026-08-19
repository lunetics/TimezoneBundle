<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

interface CountryTimezoneSourceInterface
{
    /** @return list<TimezoneId> */
    public function forCountry(string $countryCode): array;
}
