<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolution;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Exception\ResolutionFailureExceptionInterface;
use Lunetics\TimezoneBundle\Resolver\TimezoneResolverInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class TimezoneResolverChain
{
    /** @var list<array{name: string, resolver: TimezoneResolverInterface}> */
    private array $resolvers;

    /**
     * Resolvers must already be in policy order. String iterable keys are used
     * as stable diagnostic identifiers when available.
     *
     * @param iterable<int|string, TimezoneResolverInterface> $resolvers
     */
    public function __construct(
        iterable $resolvers,
        private TimezoneId $defaultTimezone,
        private ResolutionFailureStrategy $failureStrategy,
        private ?LoggerInterface $logger = null,
    ) {
        $normalized = [];

        foreach ($resolvers as $name => $resolver) {
            $normalized[] = [
                'name' => $this->traceName($name, $resolver),
                'resolver' => $resolver,
            ];
        }

        $this->resolvers = $normalized;
    }

    /** @throws ResolutionFailureExceptionInterface */
    public function resolve(Request $request): TimezoneResolution
    {
        $attempts = [];

        foreach ($this->resolvers as $entry) {
            $startedAt = hrtime(true);
            try {
                $resolution = $entry['resolver']->resolve($request);
            } catch (ResolutionFailureExceptionInterface $failure) {
                $attempt = new TimezoneResolutionAttempt(
                    $entry['name'],
                    $failure instanceof InvalidTimezoneException
                        ? ResolutionAttemptOutcome::INVALID
                        : ResolutionAttemptOutcome::FAILED,
                    null,
                    $this->elapsedMicroseconds($startedAt),
                );
                $attempts[] = $attempt;

                if ($failure instanceof InvalidTimezoneException) {
                    $this->logger?->warning('Timezone resolver returned an invalid identifier.', $this->attemptContext($attempt));
                } else {
                    $this->logger?->error('Timezone resolver failed.', $this->attemptContext($attempt));
                }

                if (ResolutionFailureStrategy::THROW === $this->failureStrategy) {
                    (new TimezoneResolutionTrace($attempts, null))->attachTo($request);

                    throw $failure;
                }

                continue;
            }

            if (null === $resolution) {
                $attempt = new TimezoneResolutionAttempt(
                    $entry['name'],
                    ResolutionAttemptOutcome::NO_RESULT,
                    null,
                    $this->elapsedMicroseconds($startedAt),
                );
                $attempts[] = $attempt;
                $this->logger?->debug('Timezone resolver produced no result.', $this->attemptContext($attempt));

                continue;
            }

            $attempt = new TimezoneResolutionAttempt(
                $entry['name'],
                ResolutionAttemptOutcome::RESOLVED,
                $resolution->source,
                $this->elapsedMicroseconds($startedAt),
            );
            $attempts[] = $attempt;
            $this->logger?->info('Timezone resolver selected a result.', $this->attemptContext($attempt, $resolution));
            (new TimezoneResolutionTrace($attempts, $resolution))->attachTo($request);

            return $resolution;
        }

        $resolution = new TimezoneResolution(
            $this->defaultTimezone,
            'default',
            ResolutionKind::DEFAULT,
        );
        $attempt = new TimezoneResolutionAttempt(
            'configured_default',
            ResolutionAttemptOutcome::DEFAULTED,
            $resolution->source,
        );
        $attempts[] = $attempt;
        $this->logger?->info('Timezone resolution used the configured default.', $this->attemptContext($attempt, $resolution));
        (new TimezoneResolutionTrace($attempts, $resolution))->attachTo($request);

        return $resolution;
    }

    private function elapsedMicroseconds(int $startedAt): int
    {
        return max(0, intdiv(hrtime(true) - $startedAt, 1_000));
    }

    /** @return array{resolver: string, source?: string, kind?: string, timezone?: string, outcome: string, duration_microseconds: int} */
    private function attemptContext(TimezoneResolutionAttempt $attempt, ?TimezoneResolution $resolution = null): array
    {
        $context = [
            'resolver' => $attempt->resolver,
            'outcome' => $attempt->outcome->value,
            'duration_microseconds' => $attempt->durationMicroseconds,
        ];

        if (null !== $resolution) {
            $context['source'] = $resolution->source;
            $context['kind'] = $resolution->kind->value;
            $context['timezone'] = $resolution->timezone->value();
        }

        return $context;
    }

    private function traceName(int|string $key, TimezoneResolverInterface $resolver): string
    {
        if (is_string($key) && '' !== $key && 1 === preg_match('/^[A-Za-z0-9_.:\\\\-]{1,160}$/D', $key)) {
            return $key;
        }

        $class = $resolver::class;
        $anonymousMarker = strpos($class, '@anonymous');

        return false === $anonymousMarker ? $class : substr($class, 0, $anonymousMarker + 10);
    }
}
