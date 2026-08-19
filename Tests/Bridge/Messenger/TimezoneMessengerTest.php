<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\Messenger;

use Lunetics\TimezoneBundle\Bridge\Messenger\DispatchTimezoneMiddleware;
use Lunetics\TimezoneBundle\Bridge\Messenger\TimezoneStamp;
use Lunetics\TimezoneBundle\Bridge\Messenger\WorkerTimezoneMiddleware;
use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;
use Lunetics\TimezoneBundle\Context\TimezoneExecutionContext;
use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

final class TimezoneMessengerTest extends TestCase
{
    public function testStampHasScalarSerializationAndRevalidatesNativePayload(): void
    {
        $stamp = new TimezoneStamp('Europe/Berlin');
        self::assertSame('Europe/Berlin', $stamp->timezone);
        self::assertSame('Europe/Berlin', $stamp->toTimezoneId()->value());
        $roundTrip = unserialize(serialize($stamp));
        self::assertInstanceOf(TimezoneStamp::class, $roundTrip);
        self::assertSame('Europe/Berlin', $roundTrip->timezone);

        $this->expectException(InvalidTimezoneException::class);
        $class = TimezoneStamp::class;
        unserialize(sprintf('O:%d:"%s":1:{s:8:"timezone";s:3:"bad";}', strlen($class), $class));
    }

    public function testDispatchAddsMissingStampAndRetainsExistingStamp(): void
    {
        $provider = $this->provider('Europe/Berlin');
        $middleware = new DispatchTimezoneMiddleware($provider);
        $message = new \stdClass();
        $added = $middleware->handle(new Envelope($message), new StackMiddleware());
        self::assertSame('Europe/Berlin', $added->last(TimezoneStamp::class)?->timezone);

        $original = new TimezoneStamp('Asia/Tokyo');
        $retained = $middleware->handle(new Envelope($message, [$original]), new StackMiddleware());
        self::assertSame($original, $retained->last(TimezoneStamp::class));
        self::assertCount(1, $retained->all(TimezoneStamp::class));
    }

    public function testWorkerUsesLastStampAndCleansUpAfterSuccess(): void
    {
        $context = new TimezoneExecutionContext();
        $worker = new WorkerTimezoneMiddleware($context, $this->provider('UTC'));
        $seen = null;
        $terminal = $this->terminal(static function (Envelope $envelope) use ($context, &$seen): Envelope {
            $seen = $context->current()?->value();
            return $envelope;
        });
        $envelope = new Envelope(new \stdClass(), [new TimezoneStamp('Europe/Berlin'), new TimezoneStamp('Asia/Tokyo')]);

        $worker->handle($envelope, new StackMiddleware($terminal));
        self::assertSame('Asia/Tokyo', $seen);
        self::assertNull($context->current());
    }

    public function testWorkerCleansUpAfterFailure(): void
    {
        $context = new TimezoneExecutionContext();
        $worker = new WorkerTimezoneMiddleware($context, $this->provider('UTC'));
        $terminal = $this->terminal(static function (): never {
            throw new \RuntimeException('failure');
        });

        try {
            $worker->handle(new Envelope(new \stdClass(), [new TimezoneStamp('Europe/Berlin')]), new StackMiddleware($terminal));
            self::fail('Expected exception.');
        } catch (\RuntimeException $exception) {
            self::assertSame('failure', $exception->getMessage());
        }
        self::assertNull($context->current());
    }

    public function testWorkerIsolatesSequentialAndMissingStampMessages(): void
    {
        $context = new TimezoneExecutionContext();
        $worker = new WorkerTimezoneMiddleware($context, $this->provider('UTC'));
        $seen = [];
        $terminal = $this->terminal(static function (Envelope $envelope) use ($context, &$seen): Envelope {
            $seen[] = $context->current()?->value();
            return $envelope;
        });

        $worker->handle(new Envelope(new \stdClass(), [new TimezoneStamp('Europe/Berlin')]), new StackMiddleware($terminal));
        $worker->handle(new Envelope(new \stdClass()), new StackMiddleware($terminal));

        self::assertSame(['Europe/Berlin', 'UTC'], $seen);
        self::assertNull($context->current());
    }

    private function provider(string $timezone): CurrentTimezoneProviderInterface
    {
        $provider = $this->createStub(CurrentTimezoneProviderInterface::class);
        $provider->method('getTimezone')->willReturn(TimezoneId::fromString($timezone));
        return $provider;
    }

    /** @param callable(Envelope): Envelope $callback */
    private function terminal(callable $callback): MiddlewareInterface
    {
        return new class($callback) implements MiddlewareInterface {
            /** @param callable(Envelope): Envelope $callback */
            public function __construct(private $callback)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return ($this->callback)($envelope);
            }
        };
    }
}
