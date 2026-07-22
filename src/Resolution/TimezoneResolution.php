<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

final readonly class TimezoneResolution
{
    public function __construct(
        public TimezoneId $timezone,
        public string $source,
        public ResolutionKind $kind,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $source)) {
            throw new \InvalidArgumentException('The resolution source must be a safe, non-empty identifier.');
        }
    }
}
