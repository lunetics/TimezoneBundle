<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Context;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Contracts\Service\ResetInterface;

final class TimezoneExecutionContext implements TimezoneExecutionContextInterface, ResetInterface
{
    /** @var list<TimezoneId> */
    private array $stack = [];

    public function run(TimezoneId $timezone, callable $callback): mixed
    {
        $this->stack[] = $timezone;
        try {
            return $callback();
        } finally {
            array_pop($this->stack);
        }
    }

    public function current(): ?TimezoneId
    {
        return $this->stack[array_key_last($this->stack)] ?? null;
    }

    public function reset(): void
    {
        $this->stack = [];
    }
}
