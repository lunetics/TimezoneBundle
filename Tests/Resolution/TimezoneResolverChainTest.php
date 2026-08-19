<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolution;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionAttemptOutcome;
use Lunetics\TimezoneBundle\Resolution\ResolutionFailureStrategy;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolutionAttempt;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolutionTrace;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolverChain;
use Lunetics\TimezoneBundle\Resolver\TimezoneResolverInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TimezoneResolverChain::class)]
#[CoversClass(TimezoneResolutionTrace::class)]
#[CoversClass(TimezoneResolutionAttempt::class)]
final class TimezoneResolverChainTest extends TestCase
{
    public function testSelectsTheFirstResultInConfiguredOrderAndRecordsSafeAttempts(): void
    {
        $request = Request::create('/');
        $chain = $this->chain([
            'first' => $this->resolver(static fn (): ?TimezoneResolution => null),
            'second' => $this->resolver(static fn (): TimezoneResolution => self::resolution('Europe/Berlin', 'second')),
            'not_called' => $this->resolver(static function (): never {
                throw new \LogicException('Must not be called.');
            }),
        ]);

        $resolution = $chain->resolve($request);
        $trace = TimezoneResolutionTrace::fromRequest($request);

        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertNotNull($trace);
        self::assertSame($resolution, $trace->selected);
        self::assertSame(['first', 'second'], array_column($trace->attempts, 'resolver'));
        self::assertSame(
            [ResolutionAttemptOutcome::NO_RESULT, ResolutionAttemptOutcome::RESOLVED],
            array_column($trace->attempts, 'outcome'),
        );
        self::assertSame([null, 'second'], array_column($trace->attempts, 'source'));
        foreach ($trace->attempts as $attempt) {
            self::assertGreaterThanOrEqual(0, $attempt->durationMicroseconds);
        }
    }

    public function testUsesASyntheticDefaultWithoutAResolverPriority(): void
    {
        $request = Request::create('/');
        $resolution = $this->chain([
            'empty' => $this->resolver(static fn (): ?TimezoneResolution => null),
        ])->resolve($request);
        $trace = TimezoneResolutionTrace::fromRequest($request);

        self::assertSame(ResolutionKind::DEFAULT, $resolution->kind);
        self::assertSame('UTC', $resolution->timezone->value());
        self::assertNotNull($trace);
        self::assertSame('configured_default', $trace->attempts[1]->resolver);
        self::assertSame(ResolutionAttemptOutcome::DEFAULTED, $trace->attempts[1]->outcome);
        self::assertSame(0, $trace->attempts[1]->durationMicroseconds);
    }

    public function testContinueSkipsOnlyDeclaredDomainAndResolverFailures(): void
    {
        $request = Request::create('/');
        $chain = $this->chain([
            'invalid' => $this->resolver(static function (): never {
                throw InvalidTimezoneException::invalidIdentifier();
            }),
            'failed' => $this->resolver(static function (): never {
                throw new TimezoneResolverException('Adapter failed without raw input.');
            }),
            'valid' => $this->resolver(static fn (): TimezoneResolution => self::resolution('UTC', 'valid')),
        ]);

        self::assertSame('valid', $chain->resolve($request)->source);
        $trace = TimezoneResolutionTrace::fromRequest($request);

        self::assertNotNull($trace);
        self::assertSame(
            [ResolutionAttemptOutcome::INVALID, ResolutionAttemptOutcome::FAILED, ResolutionAttemptOutcome::RESOLVED],
            array_column($trace->attempts, 'outcome'),
        );
    }

    public function testThrowPolicyRethrowsDeclaredFailureAndAttachesPartialTrace(): void
    {
        $request = Request::create('/');
        $failure = new TimezoneResolverException('Adapter failed.');
        $chain = $this->chain([
            'failed' => $this->resolver(static function () use ($failure): never {
                throw $failure;
            }),
        ], ResolutionFailureStrategy::THROW);

        try {
            $chain->resolve($request);
            self::fail('Expected the declared resolver failure to be rethrown.');
        } catch (TimezoneResolverException $actual) {
            self::assertSame($failure, $actual);
        }

        $trace = TimezoneResolutionTrace::fromRequest($request);
        self::assertNotNull($trace);
        self::assertNull($trace->selected);
        self::assertSame(ResolutionAttemptOutcome::FAILED, $trace->attempts[0]->outcome);
        self::assertGreaterThanOrEqual(0, $trace->attempts[0]->durationMicroseconds);
    }

    public function testAttemptRejectsANegativeDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimezoneResolutionAttempt('resolver', ResolutionAttemptOutcome::NO_RESULT, null, -1);
    }

    public function testProgrammerErrorsAlwaysBubbleEvenInContinueMode(): void
    {
        $chain = $this->chain([
            'broken' => $this->resolver(static function (): never {
                throw new \LogicException('Programming error.');
            }),
        ]);

        $this->expectException(\LogicException::class);
        $chain->resolve(Request::create('/'));
    }

    public function testLogsBoundedStructuredOutcomesWithoutExceptionDetails(): void
    {
        $logger = new RecordingLogger();
        $secret = 'secret previous-exception message';
        $chain = $this->chain([
            'empty' => $this->resolver(static fn (): ?TimezoneResolution => null),
            'invalid' => $this->resolver(static function (): never {
                throw InvalidTimezoneException::invalidIdentifier();
            }),
            'failed' => $this->resolver(static function () use ($secret): never {
                throw TimezoneResolverException::userAccessorFailed(new \RuntimeException($secret));
            }),
            'selected' => $this->resolver(static fn (): TimezoneResolution => self::resolution('Europe/Berlin', 'selected')),
        ], logger: $logger);

        $chain->resolve(Request::create('/'));

        self::assertSame(
            [LogLevel::DEBUG, LogLevel::WARNING, LogLevel::ERROR, LogLevel::INFO],
            array_column($logger->records, 'level'),
        );
        self::assertSame(
            ['no_result', 'invalid', 'failed', 'resolved'],
            array_column(array_column($logger->records, 'context'), 'outcome'),
        );
        self::assertSame(
            ['resolver', 'outcome', 'duration_microseconds'],
            array_keys($logger->records[2]['context']),
        );
        self::assertStringNotContainsString($secret, serialize($logger->records));
    }

    public function testLogsConfiguredDefaultAndLogsThrowFailureBeforeRethrow(): void
    {
        $defaultLogger = new RecordingLogger();
        $this->chain([], logger: $defaultLogger)->resolve(Request::create('/'));

        self::assertSame(LogLevel::INFO, $defaultLogger->records[0]['level']);
        self::assertSame('defaulted', $defaultLogger->records[0]['context']['outcome']);
        self::assertSame('UTC', $defaultLogger->records[0]['context']['timezone']);

        $throwLogger = new RecordingLogger();
        $request = Request::create('/');
        $chain = $this->chain([
            'failed' => $this->resolver(static function (): never {
                throw new TimezoneResolverException('Technical failure.');
            }),
        ], ResolutionFailureStrategy::THROW, $throwLogger);

        try {
            $chain->resolve($request);
            self::fail('Expected failure.');
        } catch (TimezoneResolverException) {
            self::assertSame(LogLevel::ERROR, $throwLogger->records[0]['level']);
            self::assertSame('failed', $throwLogger->records[0]['context']['outcome']);
            self::assertNotNull(TimezoneResolutionTrace::fromRequest($request));
        }
    }

    /**
     * @param array<string, TimezoneResolverInterface> $resolvers
     */
    private function chain(
        array $resolvers,
        ResolutionFailureStrategy $strategy = ResolutionFailureStrategy::CONTINUE,
        ?RecordingLogger $logger = null,
    ): TimezoneResolverChain {
        return new TimezoneResolverChain($resolvers, TimezoneId::fromString('UTC'), $strategy, $logger);
    }

    /** @param callable(Request): ?TimezoneResolution $callback */
    private function resolver(callable $callback): TimezoneResolverInterface
    {
        return new class($callback) implements TimezoneResolverInterface {
            /** @var \Closure(Request): ?TimezoneResolution */
            private \Closure $callback;

            /** @param callable(Request): ?TimezoneResolution $callback */
            public function __construct(callable $callback)
            {
                $this->callback = $callback(...);
            }

            public function resolve(Request $request): ?TimezoneResolution
            {
                return ($this->callback)($request);
            }
        };
    }

    private static function resolution(string $timezone, string $source): TimezoneResolution
    {
        return new TimezoneResolution(
            TimezoneId::fromString($timezone),
            $source,
            ResolutionKind::INFERRED,
        );
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param string|\Stringable $message
     * @param array<array-key, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        if (!is_string($level)) {
            throw new \InvalidArgumentException('The recording logger expects a string log level.');
        }

        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
