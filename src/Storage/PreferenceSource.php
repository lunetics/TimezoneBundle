<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

enum PreferenceSource: string
{
    case MANUAL = 'manual';
    case BROWSER = 'browser';
}
