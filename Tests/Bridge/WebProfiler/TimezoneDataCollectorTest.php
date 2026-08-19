<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\WebProfiler;

use Lunetics\TimezoneBundle\Bridge\WebProfiler\TimezoneDataCollector;
use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use Lunetics\TimezoneBundle\EventListener\InvalidPreferenceCleanupListener;
use Lunetics\TimezoneBundle\Resolution\ResolutionAttemptOutcome;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolutionAttempt;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolutionTrace;
use Lunetics\TimezoneBundle\Resolver\StoredPreferenceTimezoneResolver;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TimezoneDataCollectorTest extends TestCase
{
    public function testCollectsOnlyBoundedScalarDiagnosticsAndResets(): void
    {
        $collector = new TimezoneDataCollector('UTC', [['name' => 'safe_resolver', 'priority' => 10]]);
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.8'], 'secret body');
        $resolution = new TimezoneResolution(TimezoneId::fromString('Europe/Berlin'), 'safe_source', ResolutionKind::INFERRED);
        (new TimezoneResolutionTrace([
            new TimezoneResolutionAttempt('safe_resolver', ResolutionAttemptOutcome::RESOLVED, 'safe_source', 12),
        ], $resolution))->attachTo($request);
        $request->attributes->set(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE, new TimezonePreferenceRead(PreferenceReadStatus::ABSENT));
        $request->attributes->set(BrowserTimezoneController::PREFERENCE_WRITTEN_ATTRIBUTE, true);
        $request->attributes->set(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE, true);

        $collector->collect($request, new Response(), new \RuntimeException('secret exception'));
        $data = $collector->getDiagnostics();
        self::assertSame('Europe/Berlin', $data['effective_timezone']);
        self::assertSame(12, $data['attempts'][0]['duration_microseconds']);
        self::assertSame('absent', $data['preference_read_status']);
        self::assertTrue($data['preference_written']);
        self::assertTrue($data['preference_cleared']);
        $serialized = serialize($data);
        self::assertStringNotContainsString('203.0.113.8', $serialized);
        self::assertStringNotContainsString('secret body', $serialized);
        self::assertStringNotContainsString('secret exception', $serialized);

        $collector->reset();
        self::assertNull($collector->getDiagnostics()['effective_timezone']);
        self::assertSame([], $collector->getDiagnostics()['attempts']);
    }

    public function testProfilerTemplateHasValidTwigSyntax(): void
    {
        $source = file_get_contents(__DIR__.'/../../../Resources/views/Collector/timezone.html.twig');
        self::assertIsString($source);
        $twig = new Environment(new ArrayLoader(['timezone' => $source]));
        $twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('timezone')));
        self::addToAssertionCount(1);
    }

    public function testResetOnARehydratedCollectorFallsBackToEmptyConfiguration(): void
    {
        $collector = new TimezoneDataCollector('UTC', [['name' => 'request_attribute', 'priority' => 1000]]);
        $restored = unserialize(serialize($collector));
        self::assertInstanceOf(TimezoneDataCollector::class, $restored);

        $restored->reset();

        $diagnostics = $restored->getDiagnostics();
        self::assertSame('', $diagnostics['configured_default']);
        self::assertSame([], $diagnostics['configured_resolvers']);
    }

    public function testSerializationPreservesExactDiagnostics(): void
    {
        $collector = new TimezoneDataCollector('UTC', [['name' => 'request_attribute', 'priority' => 1000]]);
        $request = new Request();
        $resolution = new TimezoneResolution(TimezoneId::fromString('Europe/Berlin'), 'request_attribute', ResolutionKind::EXPLICIT);
        (new TimezoneResolutionTrace([
            new TimezoneResolutionAttempt('request_attribute', ResolutionAttemptOutcome::RESOLVED, 'request_attribute', 7),
        ], $resolution))->attachTo($request);
        $request->attributes->set(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE, new TimezonePreferenceRead(PreferenceReadStatus::VALID, new \Lunetics\TimezoneBundle\Storage\TimezonePreference(
            TimezoneId::fromString('Europe/Berlin'),
            \Lunetics\TimezoneBundle\Storage\PreferenceSource::MANUAL,
            new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        )));
        $collector->collect($request, new Response());
        $diagnostics = $collector->getDiagnostics();

        $restored = unserialize(serialize($collector));

        self::assertInstanceOf(TimezoneDataCollector::class, $restored);
        self::assertSame($diagnostics, $restored->getDiagnostics());
    }
}
