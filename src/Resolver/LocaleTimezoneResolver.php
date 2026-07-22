<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Symfony\Component\HttpFoundation\Request;

final readonly class LocaleTimezoneResolver implements TimezoneResolverInterface
{
    public function __construct(private CountryTimezoneSourceInterface $timezoneSource)
    {
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        $country = self::countryFromLocale($request->getLocale());
        if (null === $country) {
            return null;
        }

        $timezones = $this->timezoneSource->forCountry($country);
        if (1 !== count($timezones)) {
            return null;
        }

        return new TimezoneResolution($timezones[0], 'locale_country', ResolutionKind::INFERRED);
    }

    private static function countryFromLocale(string $locale): ?string
    {
        $normalized = str_replace('-', '_', $locale);

        if (extension_loaded('intl') && class_exists(\Locale::class)) {
            $region = \Locale::getRegion($normalized);
            if (is_string($region) && 1 === preg_match('/^[A-Z]{2}$/D', $region)) {
                return $region;
            }
        }

        $parts = preg_split('/[_-]/', $locale);
        if (false === $parts || count($parts) < 2 || 1 !== preg_match('/^[A-Za-z]{2,3}$/D', $parts[0])) {
            return null;
        }

        foreach (array_slice($parts, 1) as $part) {
            if (1 === preg_match('/^[A-Za-z]{2}$/D', $part)) {
                return strtoupper($part);
            }

            if (1 !== preg_match('/^[A-Za-z]{4}$/D', $part)) {
                break;
            }
        }

        return null;
    }
}
