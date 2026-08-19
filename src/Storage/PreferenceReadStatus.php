<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

enum PreferenceReadStatus: string
{
    case ABSENT = 'absent';
    case VALID = 'valid';
    case INVALID = 'invalid';
    case EXPIRED = 'expired';
}
