<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolver services must be explicitly tagged with "lunetics_timezone.resolver".
 */
interface TimezoneResolverInterface
{
    public function resolve(Request $request): ?TimezoneResolution;
}
