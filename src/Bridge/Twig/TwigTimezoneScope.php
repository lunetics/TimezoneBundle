<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\Twig;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\CoreExtension;

final class TwigTimezoneScope implements ResetInterface
{
    /** @var list<string|\DateTimeZone> */
    private array $stack = [];

    public function __construct(private readonly CoreExtension $coreExtension)
    {
    }

    public function enter(TimezoneId $timezone): void
    {
        $this->stack[] = $this->coreExtension->getTimezone();
        $this->coreExtension->setTimezone($timezone->value());
    }

    public function leave(): void
    {
        if ([] === $this->stack) {
            return;
        }

        $this->coreExtension->setTimezone(array_pop($this->stack));
    }

    public function run(TimezoneId $timezone, callable $callback): mixed
    {
        $this->enter($timezone);

        try {
            return $callback();
        } finally {
            $this->leave();
        }
    }

    public function reset(): void
    {
        if ([] === $this->stack) {
            return;
        }

        $timezone = $this->stack[0];
        $this->stack = [];
        $this->coreExtension->setTimezone($timezone);
    }
}
