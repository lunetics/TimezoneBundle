<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Exception;

class TimezoneResolverException extends TimezoneException implements ResolutionFailureExceptionInterface
{
    public static function invalidResultType(): self
    {
        return new self('A timezone resolver returned an unsupported value type.');
    }

    public static function userAccessorFailed(\Exception $previous): self
    {
        return new self('The user timezone accessor failed.', 0, $previous);
    }

    public static function oidcProviderFailed(\Exception $previous): self
    {
        return new self('The OIDC claims provider failed.', 0, $previous);
    }

    public static function maxMindDatabaseFailed(\Exception $previous): self
    {
        return new self('The MaxMind City database could not be opened.', 0, $previous);
    }

    public static function maxMindLookupFailed(\Exception $previous): self
    {
        return new self('The MaxMind City lookup failed.', 0, $previous);
    }
}
