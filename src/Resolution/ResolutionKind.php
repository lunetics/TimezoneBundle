<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

enum ResolutionKind: string
{
    case EXPLICIT = 'explicit';
    case AUTHENTICATED = 'authenticated';
    case PERSISTED = 'persisted';
    case INFERRED = 'inferred';
    case DEFAULT = 'default';
}
