<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\Messenger;

use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class DispatchTimezoneMiddleware implements MiddlewareInterface
{
    public function __construct(private CurrentTimezoneProviderInterface $provider)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(TimezoneStamp::class)) {
            $envelope = $envelope->with(new TimezoneStamp($this->provider->getTimezone()->value()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
