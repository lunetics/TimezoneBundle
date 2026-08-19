<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Exception;

/**
 * Marker for failures that a resolver chain may handle according to policy.
 *
 * Implementations must not contain request values in their messages.
 */
interface ResolutionFailureExceptionInterface extends \Throwable
{
}
