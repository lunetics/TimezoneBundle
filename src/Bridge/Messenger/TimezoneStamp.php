<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\Messenger;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class TimezoneStamp implements StampInterface
{
    public function __construct(public readonly string $timezone)
    {
        TimezoneId::fromString($timezone);
    }

    public function toTimezoneId(): TimezoneId
    {
        return TimezoneId::fromString($this->timezone);
    }

    /** @return array{timezone: string} */
    public function __serialize(): array
    {
        return ['timezone' => $this->timezone];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        if (1 !== count($data) || !isset($data['timezone']) || !is_string($data['timezone'])) {
            throw InvalidTimezoneException::invalidIdentifier();
        }

        $validated = TimezoneId::fromString($data['timezone']);
        $this->timezone = $validated->value();
    }
}
