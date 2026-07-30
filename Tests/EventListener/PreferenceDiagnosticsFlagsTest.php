<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\EventListener;

use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use Lunetics\TimezoneBundle\Event\TimezonePreferenceChangedEvent;
use Lunetics\TimezoneBundle\EventListener\InvalidPreferenceCleanupListener;
use Lunetics\TimezoneBundle\Exception\TimezoneStorageException;
use Lunetics\TimezoneBundle\Resolution\PersistenceFailureStrategy;
use Lunetics\TimezoneBundle\Resolver\StoredPreferenceTimezoneResolver;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\CookieTimezoneStorage;
use Lunetics\TimezoneBundle\Storage\PreferenceWriteMarkingStorage;
use Lunetics\TimezoneBundle\Storage\SessionTimezoneStorage;
use Lunetics\TimezoneBundle\Storage\TimezonePreference;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PreferenceDiagnosticsFlagsTest extends TestCase
{
    public function testFreshSessionPreferenceIsNotClearedBecauseResolverCachedInvalidEnvelope(): void
    {
        $clock = $this->fixedClock();
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_lunetics_timezone', ['v' => 1]);
        $request = $this->browserRequest();
        $request->setSession($session);
        $storage = $this->marking(new SessionTimezoneStorage(), $request);
        $events = [];
        $dispatcher = $this->recordingDispatcher($events);

        (new StoredPreferenceTimezoneResolver($storage))->resolve($request);
        $cachedRead = $request->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);
        self::assertInstanceOf(TimezonePreferenceRead::class, $cachedRead);
        self::assertSame(PreferenceReadStatus::INVALID, $cachedRead->status);
        $response = (new BrowserTimezoneController($storage, $clock, $dispatcher))($request);
        (new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE))->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response),
        );

        $this->assertSuccessfulReplacement($request, $response, $events);
        $read = $storage->read($request);
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertSame('Europe/Berlin', $read->preference?->timezone->value());
    }

    public function testFreshCookiePreferenceIsNotClearedBecauseResolverCachedExpiredPreference(): void
    {
        $clock = $this->fixedClock();
        $inner = new CookieTimezoneStorage('test-only-secret', $clock, maxAge: 3600);
        $expiredResponse = new Response();
        $inner->write(
            Request::create('https://example.test'),
            $expiredResponse,
            new TimezonePreference(TimezoneId::fromString('America/New_York'), PreferenceSource::BROWSER, new \DateTimeImmutable('2025-12-31T22:59:59Z')),
        );
        $expiredCookie = $expiredResponse->headers->getCookies()[0];
        $request = $this->browserRequest('https://example.test/_lunetics/timezone/browser');
        $request->cookies->set($expiredCookie->getName(), $expiredCookie->getValue());
        $storage = $this->marking($inner, $request);
        $events = [];
        $dispatcher = $this->recordingDispatcher($events);

        (new StoredPreferenceTimezoneResolver($storage))->resolve($request);
        $cachedRead = $request->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);
        self::assertInstanceOf(TimezonePreferenceRead::class, $cachedRead);
        self::assertSame(PreferenceReadStatus::EXPIRED, $cachedRead->status);
        $response = (new BrowserTimezoneController($storage, $clock, $dispatcher))($request);
        (new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE))->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response),
        );

        $this->assertSuccessfulReplacement($request, $response, $events);
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        $freshCookie = $cookies[0];
        self::assertInstanceOf(Cookie::class, $freshCookie);
        self::assertNotSame('', $freshCookie->getValue());
        self::assertGreaterThan($clock->now()->getTimestamp(), $freshCookie->getExpiresTime());
        $followUp = Request::create('https://example.test');
        $followUp->cookies->set($freshCookie->getName(), $freshCookie->getValue());
        $read = $storage->read($followUp);
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertSame('Europe/Berlin', $read->preference?->timezone->value());
    }

    public function testApplicationManualSessionWriteSurvivesCleanupAfterInvalidRead(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_lunetics_timezone', ['v' => 1]);
        $request = Request::create('/settings', 'POST');
        $request->setSession($session);
        $storage = $this->marking(new SessionTimezoneStorage(), $request);

        (new StoredPreferenceTimezoneResolver($storage))->resolve($request);
        $cachedRead = $request->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);
        self::assertInstanceOf(TimezonePreferenceRead::class, $cachedRead);
        self::assertSame(PreferenceReadStatus::INVALID, $cachedRead->status);
        $response = new Response();
        $storage->write($request, $response, new TimezonePreference(TimezoneId::fromString('America/New_York'), PreferenceSource::MANUAL, new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        (new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE))->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response),
        );

        self::assertFalse($request->attributes->has(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE));
        $read = $storage->read($request);
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertSame('America/New_York', $read->preference?->timezone->value());
        self::assertSame(PreferenceSource::MANUAL, $read->preference->source);
    }

    public function testApplicationManualCookieWriteSurvivesCleanupAfterExpiredRead(): void
    {
        $clock = $this->fixedClock();
        $inner = new CookieTimezoneStorage('test-only-secret', $clock, maxAge: 3600);
        $expiredResponse = new Response();
        $inner->write(
            Request::create('https://example.test'),
            $expiredResponse,
            new TimezonePreference(TimezoneId::fromString('America/New_York'), PreferenceSource::BROWSER, new \DateTimeImmutable('2025-12-31T22:59:59Z')),
        );
        $expiredCookie = $expiredResponse->headers->getCookies()[0];
        $request = Request::create('https://example.test/settings', 'POST');
        $request->cookies->set($expiredCookie->getName(), $expiredCookie->getValue());
        $storage = $this->marking($inner, $request);

        (new StoredPreferenceTimezoneResolver($storage))->resolve($request);
        $cachedRead = $request->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);
        self::assertInstanceOf(TimezonePreferenceRead::class, $cachedRead);
        self::assertSame(PreferenceReadStatus::EXPIRED, $cachedRead->status);
        $response = new Response();
        $storage->write($request, $response, new TimezonePreference(TimezoneId::fromString('Europe/Paris'), PreferenceSource::MANUAL, $clock->now()));
        (new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE))->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response),
        );

        self::assertFalse($request->attributes->has(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE));
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        $freshCookie = $cookies[0];
        self::assertInstanceOf(Cookie::class, $freshCookie);
        self::assertNotSame('', (string) $freshCookie->getValue());
        self::assertGreaterThan($clock->now()->getTimestamp(), $freshCookie->getExpiresTime());
        $followUp = Request::create('https://example.test');
        $followUp->cookies->set($freshCookie->getName(), $freshCookie->getValue());
        $read = $storage->read($followUp);
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertSame('Europe/Paris', $read->preference?->timezone->value());
        self::assertSame(PreferenceSource::MANUAL, $read->preference->source);
    }

    #[DataProvider('writeOutcomes')]
    public function testWrittenFlagRequiresAnActualSuccessfulWrite(bool $fail, bool $expectedFlag): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"timezone":"Europe/Berlin"}');
        $storage = $this->marking($this->storage($fail, false), $request);
        $controller = new BrowserTimezoneController($storage, $clock, $dispatcher);

        $controller($request);

        self::assertSame($expectedFlag, true === $request->attributes->get(BrowserTimezoneController::PREFERENCE_WRITTEN_ATTRIBUTE));
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function writeOutcomes(): iterable
    {
        yield 'successful' => [false, true];
        yield 'failed' => [true, false];
    }

    #[DataProvider('clearOutcomes')]
    public function testClearedFlagRequiresAnActualSuccessfulCleanup(bool $fail, bool $expectedFlag): void
    {
        $request = Request::create('/');
        $request->attributes->set(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE, new TimezonePreferenceRead(PreferenceReadStatus::INVALID));
        $listener = new InvalidPreferenceCleanupListener($this->storage(false, $fail), PersistenceFailureStrategy::CONTINUE);
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());

        $listener->onKernelResponse($event);

        self::assertSame($expectedFlag, true === $request->attributes->get(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE));
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function clearOutcomes(): iterable
    {
        yield 'successful' => [false, true];
        yield 'failed' => [true, false];
    }

    public function testSubRequestDoesNotInspectOrCleanCopiedInvalidPreference(): void
    {
        $request = Request::create('/');
        $request->attributes->set(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE, new TimezonePreferenceRead(PreferenceReadStatus::INVALID));
        $storage = new RecordingCleanupStorage();
        $listener = new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE);
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST, new Response());

        $listener->onKernelResponse($event);

        self::assertSame(0, $storage->clearCalls);
        self::assertFalse($request->attributes->has(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE));
    }

    public function testThrowStrategyPropagatesTypedClearFailureOnMainRequest(): void
    {
        $request = Request::create('/');
        $request->attributes->set(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE, new TimezonePreferenceRead(PreferenceReadStatus::INVALID));
        $listener = new InvalidPreferenceCleanupListener($this->storage(false, true), PersistenceFailureStrategy::THROW);
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());

        $this->expectException(TimezoneStorageException::class);
        $listener->onKernelResponse($event);
    }

    public function testSubrequestWriteMarksTheMainRequestForCleanup(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_lunetics_timezone', ['v' => 1]);
        $mainRequest = Request::create('/page');
        $mainRequest->setSession($session);
        $storage = $this->marking(new SessionTimezoneStorage(), $mainRequest);

        (new StoredPreferenceTimezoneResolver($storage))->resolve($mainRequest);
        $cachedRead = $mainRequest->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);
        self::assertInstanceOf(TimezonePreferenceRead::class, $cachedRead);
        self::assertSame(PreferenceReadStatus::INVALID, $cachedRead->status);
        $subRequest = Request::create('/_fragment');
        $subRequest->setSession($session);
        $response = new Response();
        $storage->write($subRequest, $response, new TimezonePreference(TimezoneId::fromString('Europe/Paris'), PreferenceSource::MANUAL, new \DateTimeImmutable('2026-01-01T00:00:00Z')));

        self::assertTrue($mainRequest->attributes->getBoolean(TimezonePreferenceStorageInterface::PREFERENCE_WRITTEN_ATTRIBUTE));
        (new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE))->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $mainRequest, HttpKernelInterface::MAIN_REQUEST, $response),
        );

        self::assertFalse($mainRequest->attributes->has(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE));
        $read = $storage->read($mainRequest);
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertSame('Europe/Paris', $read->preference?->timezone->value());
        self::assertSame(PreferenceSource::MANUAL, $read->preference->source);
    }

    public function testDiscardedSubrequestCookieWriteStillClearsTheStaleCookie(): void
    {
        $clock = $this->fixedClock();
        $inner = new CookieTimezoneStorage('test-only-secret', $clock, maxAge: 3600);
        $expiredResponse = new Response();
        $inner->write(
            Request::create('https://example.test'),
            $expiredResponse,
            new TimezonePreference(TimezoneId::fromString('America/New_York'), PreferenceSource::BROWSER, new \DateTimeImmutable('2025-12-31T22:00:00Z')),
        );
        $expiredCookie = $expiredResponse->headers->getCookies()[0];
        $mainRequest = Request::create('https://example.test/page');
        $mainRequest->cookies->set($expiredCookie->getName(), $expiredCookie->getValue());
        $stack = new RequestStack();
        $stack->push($mainRequest);
        $storage = new PreferenceWriteMarkingStorage($inner, $stack);

        (new StoredPreferenceTimezoneResolver($storage))->resolve($mainRequest);
        $subRequest = Request::create('https://example.test/_fragment');
        $stack->push($subRequest);
        $storage->write($subRequest, new Response(), new TimezonePreference(TimezoneId::fromString('Europe/Paris'), PreferenceSource::MANUAL, $clock->now()));
        $stack->pop();

        self::assertFalse($mainRequest->attributes->has(TimezonePreferenceStorageInterface::PREFERENCE_WRITTEN_ATTRIBUTE), 'A response-scoped write must not suppress cleanup on a different response.');
        $mainResponse = new Response();
        (new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE))->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $mainRequest, HttpKernelInterface::MAIN_REQUEST, $mainResponse),
        );

        $cookies = $mainResponse->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('', (string) $cookies[0]->getValue());
        self::assertLessThan($clock->now()->getTimestamp(), $cookies[0]->getExpiresTime());
        self::assertTrue($mainRequest->attributes->getBoolean(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE));
    }

    public function testForwardedCookieWriteSurvivesCleanupOnTheSharedResponse(): void
    {
        $clock = $this->fixedClock();
        $inner = new CookieTimezoneStorage('test-only-secret', $clock, maxAge: 3600);
        $expiredResponse = new Response();
        $inner->write(
            Request::create('https://example.test'),
            $expiredResponse,
            new TimezonePreference(TimezoneId::fromString('America/New_York'), PreferenceSource::BROWSER, new \DateTimeImmutable('2025-12-31T22:00:00Z')),
        );
        $expiredCookie = $expiredResponse->headers->getCookies()[0];
        $mainRequest = Request::create('https://example.test/page');
        $mainRequest->cookies->set($expiredCookie->getName(), $expiredCookie->getValue());
        $stack = new RequestStack();
        $stack->push($mainRequest);
        $storage = new PreferenceWriteMarkingStorage($inner, $stack);

        (new StoredPreferenceTimezoneResolver($storage))->resolve($mainRequest);
        $sharedResponse = new Response();
        $forwarded = Request::create('https://example.test/forwarded');
        $stack->push($forwarded);
        $storage->write($forwarded, $sharedResponse, new TimezonePreference(TimezoneId::fromString('Europe/Paris'), PreferenceSource::MANUAL, $clock->now()));
        $stack->pop();
        (new InvalidPreferenceCleanupListener($storage, PersistenceFailureStrategy::CONTINUE))->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $mainRequest, HttpKernelInterface::MAIN_REQUEST, $sharedResponse),
        );

        $cookies = $sharedResponse->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertNotSame('', (string) $cookies[0]->getValue());
        $followUp = Request::create('https://example.test');
        $followUp->cookies->set($cookies[0]->getName(), $cookies[0]->getValue());
        $read = $inner->read($followUp);
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertSame('Europe/Paris', $read->preference?->timezone->value());
    }

    private function marking(TimezonePreferenceStorageInterface $inner, Request $mainRequest): PreferenceWriteMarkingStorage
    {
        $stack = new RequestStack();
        $stack->push($mainRequest);

        return new PreferenceWriteMarkingStorage($inner, $stack);
    }

    private function fixedClock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-01-01T00:00:00Z');
            }
        };
    }

    private function browserRequest(string $uri = '/_lunetics/timezone/browser'): Request
    {
        return Request::create($uri, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"timezone":"Europe/Berlin"}');
    }

    /** @param list<TimezonePreferenceChangedEvent> $events */
    private function recordingDispatcher(array &$events): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TimezonePreferenceChangedEvent::class, static function (TimezonePreferenceChangedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        return $dispatcher;
    }

    /** @param list<TimezonePreferenceChangedEvent> $events */
    private function assertSuccessfulReplacement(Request $request, Response $response, array $events): void
    {
        self::assertSame(204, $response->getStatusCode());
        self::assertCount(1, $events);
        self::assertNull($events[0]->previous);
        self::assertNotNull($events[0]->current);
        self::assertSame('Europe/Berlin', $events[0]->current->timezone->value());
        self::assertSame(PreferenceSource::BROWSER, $events[0]->current->source);
        self::assertTrue($request->attributes->getBoolean(BrowserTimezoneController::PREFERENCE_WRITTEN_ATTRIBUTE));
        self::assertFalse($request->attributes->has(InvalidPreferenceCleanupListener::PREFERENCE_CLEARED_ATTRIBUTE));
    }

    private function storage(bool $failWrite, bool $failClear): TimezonePreferenceStorageInterface
    {
        return new class($failWrite, $failClear) implements TimezonePreferenceStorageInterface {
            public function __construct(private readonly bool $failWrite, private readonly bool $failClear)
            {
            }

            public function read(Request $request): TimezonePreferenceRead
            {
                return new TimezonePreferenceRead(PreferenceReadStatus::ABSENT);
            }

            public function write(Request $request, Response $response, TimezonePreference $preference): void
            {
                if ($this->failWrite) {
                    throw TimezoneStorageException::operationFailed('write', new \RuntimeException());
                }
            }

            public function clear(Request $request, Response $response): void
            {
                if ($this->failClear) {
                    throw TimezoneStorageException::operationFailed('clear', new \RuntimeException());
                }
            }
        };
    }
}

final class RecordingCleanupStorage implements TimezonePreferenceStorageInterface
{
    public int $clearCalls = 0;

    public function read(Request $request): TimezonePreferenceRead
    {
        return new TimezonePreferenceRead(PreferenceReadStatus::ABSENT);
    }

    public function write(Request $request, Response $response, TimezonePreference $preference): void
    {
    }

    public function clear(Request $request, Response $response): void
    {
        ++$this->clearCalls;
    }
}
