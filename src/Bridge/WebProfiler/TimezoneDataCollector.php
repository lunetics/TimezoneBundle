<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\WebProfiler;

use Lunetics\TimezoneBundle\EventListener\InvalidPreferenceCleanupListener;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolutionTrace;
use Lunetics\TimezoneBundle\Resolver\StoredPreferenceTimezoneResolver;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class TimezoneDataCollector extends DataCollector
{
    // Defaults keep reset() safe on rehydrated collectors: profile
    // serialization carries only $data, not constructor state.
    private string $configuredDefault = '';
    /** @var list<array{name: string, priority: int}> */
    private array $configuredResolvers = [];

    /**
     * @param list<array{name: string, priority: int}> $configuredResolvers
     */
    public function __construct(string $configuredDefault, array $configuredResolvers)
    {
        $this->configuredDefault = $configuredDefault;
        $this->configuredResolvers = $configuredResolvers;
        $this->reset();
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $trace = TimezoneResolutionTrace::fromRequest($request);
        $selected = $trace?->selected;
        $read = $request->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);

        $attempts = [];
        foreach (null === $trace ? [] : $trace->attempts as $attempt) {
            $attempts[] = [
                'resolver' => $attempt->resolver,
                'outcome' => $attempt->outcome->value,
                'source' => $attempt->source,
                'duration_microseconds' => $attempt->durationMicroseconds,
            ];
        }

        $this->data = [
            'effective_timezone' => $selected?->timezone->value(),
            'effective_source' => $selected?->source,
            'effective_kind' => $selected?->kind->value,
            'configured_default' => $this->configuredDefault,
            'configured_resolvers' => $this->configuredResolvers,
            'attempts' => $attempts,
            'preference_read' => $read instanceof TimezonePreferenceRead,
            'preference_read_status' => $read instanceof TimezonePreferenceRead ? $read->status->value : null,
            'preference_written' => true === $request->attributes->get(TimezonePreferenceStorageInterface::PREFERENCE_WRITTEN_ATTRIBUTE),
            'preference_cleared' => true === $request->attributes->get(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE),
        ];
    }

    public function reset(): void
    {
        $this->data = [
            'effective_timezone' => null,
            'effective_source' => null,
            'effective_kind' => null,
            'configured_default' => $this->configuredDefault,
            'configured_resolvers' => $this->configuredResolvers,
            'attempts' => [],
            'preference_read' => false,
            'preference_read_status' => null,
            'preference_written' => false,
            'preference_cleared' => false,
        ];
    }

    public function getName(): string
    {
        return 'lunetics_timezone';
    }

    /** @return array{effective_timezone: ?string, effective_source: ?string, effective_kind: ?string, configured_default: string, configured_resolvers: list<array{name: string, priority: int}>, attempts: list<array{resolver: string, outcome: string, source: ?string, duration_microseconds: int}>, preference_read: bool, preference_read_status: ?string, preference_written: bool, preference_cleared: bool} */
    public function getDiagnostics(): array
    {
        if (!is_array($this->data) || !self::isDiagnostics($this->data)) {
            throw new \LogicException('Invalid timezone profiler diagnostics data.');
        }

        return $this->data;
    }

    /**
     * @param array<array-key, mixed> $data
     * @phpstan-assert-if-true array{effective_timezone: ?string, effective_source: ?string, effective_kind: ?string, configured_default: string, configured_resolvers: list<array{name: string, priority: int}>, attempts: list<array{resolver: string, outcome: string, source: ?string, duration_microseconds: int}>, preference_read: bool, preference_read_status: ?string, preference_written: bool, preference_cleared: bool} $data
     */
    private static function isDiagnostics(array $data): bool
    {
        if (array_keys($data) !== ['effective_timezone', 'effective_source', 'effective_kind', 'configured_default', 'configured_resolvers', 'attempts', 'preference_read', 'preference_read_status', 'preference_written', 'preference_cleared']
            || (null !== $data['effective_timezone'] && !is_string($data['effective_timezone']))
            || (null !== $data['effective_source'] && !is_string($data['effective_source']))
            || (null !== $data['effective_kind'] && !is_string($data['effective_kind']))
            || !is_string($data['configured_default'])
            || !is_array($data['configured_resolvers'])
            || !array_is_list($data['configured_resolvers'])
            || !is_array($data['attempts'])
            || !array_is_list($data['attempts'])
            || !is_bool($data['preference_read'])
            || (null !== $data['preference_read_status'] && !is_string($data['preference_read_status']))
            || !is_bool($data['preference_written'])
            || !is_bool($data['preference_cleared'])) {
            return false;
        }
        foreach ($data['configured_resolvers'] as $resolver) {
            if (!is_array($resolver) || array_keys($resolver) !== ['name', 'priority'] || !is_string($resolver['name']) || !is_int($resolver['priority'])) {
                return false;
            }
        }
        foreach ($data['attempts'] as $attempt) {
            if (!is_array($attempt) || array_keys($attempt) !== ['resolver', 'outcome', 'source', 'duration_microseconds'] || !is_string($attempt['resolver']) || !is_string($attempt['outcome']) || (null !== $attempt['source'] && !is_string($attempt['source'])) || !is_int($attempt['duration_microseconds'])) {
                return false;
            }
        }

        return true;
    }
}
