<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

enum HeaderTrustMode: string
{
    case FRAMEWORK = 'framework';
    case ALLOWLIST = 'allowlist';
    case ANY = 'any';
}
