<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;

final readonly class LocaleMappingTimezoneResolver implements TimezoneResolverInterface
{
    /** @var array<string, TimezoneId> */
    private array $mapping;

    /** @param array<string, mixed> $mapping */
    public function __construct(array $mapping)
    {
        $validatedMapping = [];

        foreach ($mapping as $locale => $timezone) {
            if ('' === $locale || (!is_string($timezone) && !$timezone instanceof TimezoneId)) {
                throw new \InvalidArgumentException('Locale mappings require non-empty locale keys and timezone identifiers.');
            }

            $normalizedLocale = self::normalizeLocale($locale);
            if (isset($validatedMapping[$normalizedLocale])) {
                throw new \InvalidArgumentException(sprintf('Locale mapping key "%s" collides after normalization.', $locale));
            }

            $validatedMapping[$normalizedLocale] = is_string($timezone) ? TimezoneId::fromString($timezone) : $timezone;
        }

        $this->mapping = $validatedMapping;
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        $timezone = $this->mapping[self::normalizeLocale($request->getLocale())] ?? null;

        return null === $timezone
            ? null
            : new TimezoneResolution($timezone, 'locale_mapping', ResolutionKind::INFERRED);
    }

    private static function normalizeLocale(string $locale): string
    {
        return str_replace('-', '_', $locale);
    }
}
