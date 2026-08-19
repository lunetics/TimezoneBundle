<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\MaxMind;

use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;

final readonly class CallableMaxMindCityReader implements MaxMindCityReaderInterface
{
    /** @var \Closure(string): (TimezoneId|string|null) */
    private \Closure $reader;

    /** @param callable(string): (TimezoneId|string|null) $reader */
    public function __construct(callable $reader)
    {
        $this->reader = $reader(...);
    }

    public function timezoneForIp(string $ipAddress): TimezoneId|string|null
    {
        try {
            return ($this->reader)($ipAddress);
        } catch (\Exception $exception) {
            throw TimezoneResolverException::maxMindLookupFailed($exception);
        }
    }
}
