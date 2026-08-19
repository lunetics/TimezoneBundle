<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;

final readonly class RequestAttributeTimezoneResolver implements TimezoneResolverInterface
{
    public function __construct(private string $attribute = '_timezone')
    {
        if ('' === $attribute) {
            throw new \InvalidArgumentException('The timezone request attribute name cannot be empty.');
        }
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        if (!$request->attributes->has($this->attribute)) {
            return null;
        }

        $value = $request->attributes->get($this->attribute);
        if (null === $value) {
            return null;
        }

        $timezone = match (true) {
            $value instanceof TimezoneId => $value,
            $value instanceof \DateTimeZone => TimezoneId::fromDateTimeZone($value),
            is_string($value) => TimezoneId::fromString($value),
            default => throw InvalidTimezoneException::unsupportedValueType(),
        };

        return new TimezoneResolution($timezone, 'request_attribute', ResolutionKind::EXPLICIT);
    }
}
