<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

final readonly class TimezonePreferenceRead
{
    public function __construct(public PreferenceReadStatus $status, public ?TimezonePreference $preference = null)
    {
        if (($status === PreferenceReadStatus::VALID) !== (null !== $preference)) {
            throw new \InvalidArgumentException('Only a valid preference read may contain a preference.');
        }
    }
}
