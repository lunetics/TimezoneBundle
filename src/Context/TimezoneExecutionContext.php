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
        if ([] === $this->stack) {
            return null;
        }

        return $this->stack[count($this->stack) - 1];
    }

    public function reset(): void
    {
        $this->stack = [];
    }
}
