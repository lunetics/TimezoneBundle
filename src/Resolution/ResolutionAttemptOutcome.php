<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

enum ResolutionAttemptOutcome: string
{
    case NO_RESULT = 'no_result';
    case RESOLVED = 'resolved';
    case INVALID = 'invalid';
    case FAILED = 'failed';
    case DEFAULTED = 'defaulted';
}
