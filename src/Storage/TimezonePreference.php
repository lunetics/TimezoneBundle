<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

final readonly class TimezonePreference
{
    public \DateTimeImmutable $recordedAt;

    public function __construct(
        public TimezoneId $timezone,
        public PreferenceSource $source,
        \DateTimeImmutable $recordedAt,
    ) {
        $this->recordedAt = $recordedAt->setTimezone(new \DateTimeZone('UTC'));
    }

    public function equals(self $other): bool
    {
        return $this->timezone->equals($other->timezone)
            && $this->source === $other->source;
    }
}
