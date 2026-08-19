<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Exception;

final class InvalidTimezoneException extends TimezoneException implements ResolutionFailureExceptionInterface
{
    public static function invalidIdentifier(): self
    {
        return new self('Expected a supported IANA timezone identifier.');
    }

    public static function unsupportedValueType(): self
    {
        return new self('Expected a timezone identifier, DateTimeZone, or TimezoneId.');
    }
}
