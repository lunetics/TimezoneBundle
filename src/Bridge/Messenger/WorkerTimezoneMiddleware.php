<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\Messenger;

use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;
use Lunetics\TimezoneBundle\Context\TimezoneExecutionContextInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class WorkerTimezoneMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TimezoneExecutionContextInterface $context,
        private CurrentTimezoneProviderInterface $provider,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(TimezoneStamp::class);
        $timezone = $stamp instanceof TimezoneStamp ? $stamp->toTimezoneId() : $this->provider->getTimezone();

        $result = $this->context->run(
            $timezone,
            static fn (): Envelope => $stack->next()->handle($envelope, $stack),
        );

        if (!$result instanceof Envelope) {
            throw new \UnexpectedValueException('The timezone execution context must return the middleware envelope.');
        }

        return $result;
    }
}
