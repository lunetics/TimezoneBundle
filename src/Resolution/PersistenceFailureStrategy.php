<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

enum PersistenceFailureStrategy: string
{
    case CONTINUE = 'continue';
    case THROW = 'throw';
}
