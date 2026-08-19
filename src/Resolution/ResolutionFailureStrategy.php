<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

enum ResolutionFailureStrategy: string
{
    case CONTINUE = 'continue';
    case THROW = 'throw';
}
