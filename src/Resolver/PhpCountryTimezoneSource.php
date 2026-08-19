<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

final class PhpCountryTimezoneSource implements CountryTimezoneSourceInterface
{
    public function forCountry(string $countryCode): array
    {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $countryCode)) {
            throw new \InvalidArgumentException('Expected an ISO 3166-1 alpha-2 country code.');
        }

        return array_map(
            static fn (string $identifier): TimezoneId => TimezoneId::fromString($identifier),
            \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $countryCode),
        );
    }
}
