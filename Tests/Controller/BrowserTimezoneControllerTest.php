<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Controller;

use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use Lunetics\TimezoneBundle\Event\TimezonePreferenceChangedEvent;
use Lunetics\TimezoneBundle\Exception\TimezoneStorageException;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\PreferenceWriteMarkingStorage;
use Lunetics\TimezoneBundle\Storage\TimezonePreference;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class BrowserTimezoneControllerTest extends TestCase
{
    #[DataProvider('rejectedRequests')]
    public function testRejectsInvalidProtocolInput(?string $contentType, string $body, int $status): void
    {
        $storage = new RecordingStorage();
        $dispatcher = new RecordingDispatcher();

        $response = ($this->controller($storage, $dispatcher))($this->request($body, $contentType));

        self::assertSame($status, $response->getStatusCode());
        self::assertSame(0, $storage->reads);
        self::assertSame([], $dispatcher->events);
    }

    /** @return iterable<string, array{?string, string, int}> */
    public static function rejectedRequests(): iterable
    {
        yield 'missing content type' => [null, '{"timezone":"Europe/Berlin"}', 415];
        yield 'non-json content type' => ['text/plain', '{"timezone":"Europe/Berlin"}', 415];
        yield 'body over limit' => ['application/json', str_repeat(' ', 1025), 413];
        yield 'malformed json' => ['application/json', '{', 400];
        yield 'list instead of object' => ['application/json', '["Europe/Berlin"]', 400];
        yield 'missing timezone' => ['application/json', '{}', 400];
        yield 'additional property' => ['application/json', '{"timezone":"Europe/Berlin","extra":true}', 400];
        yield 'non-string timezone' => ['application/json', '{"timezone":1}', 400];
        yield 'invalid timezone' => ['application/json', '{"timezone":"Mars/Olympus"}', 422];
    }

    public function testAcceptsVendorJsonAndWritesAndDispatchesExactlyOnce(): void
    {
        $storage = new RecordingStorage();
        $dispatcher = new RecordingDispatcher();
        $request = $this->request('{"timezone":"Europe/Berlin"}', 'application/vnd.lunetics+json; charset=utf-8');
        $stack = new RequestStack();
        $stack->push($request);

        $response = ($this->controller(new PreferenceWriteMarkingStorage($storage, $stack), $dispatcher))($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertCount(1, $storage->writes);
        self::assertSame('Europe/Berlin', $storage->writes[0]->timezone->value());
        self::assertSame(PreferenceSource::BROWSER, $storage->writes[0]->source);
        self::assertTrue($request->attributes->getBoolean(BrowserTimezoneController::PREFERENCE_WRITTEN_ATTRIBUTE));
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(TimezonePreferenceChangedEvent::class, $dispatcher->events[0]);
        self::assertNull($dispatcher->events[0]->previous);
        self::assertSame($storage->writes[0], $dispatcher->events[0]->current);
    }

    #[DataProvider('suppressedPreferences')]
    public function testStoredPreferenceCanSuppressWrite(PreferenceSource $source, string $timezone): void
    {
        $storage = new RecordingStorage(new TimezonePreferenceRead(PreferenceReadStatus::VALID, $this->preference($timezone, $source)));
        $dispatcher = new RecordingDispatcher();
        $request = $this->request('{"timezone":"Europe/Berlin"}', 'application/json');
        $stack = new RequestStack();
        $stack->push($request);

        $response = ($this->controller(new PreferenceWriteMarkingStorage($storage, $stack), $dispatcher))($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame([], $storage->writes);
        self::assertFalse($request->attributes->has(BrowserTimezoneController::PREFERENCE_WRITTEN_ATTRIBUTE));
        self::assertSame([], $dispatcher->events);
    }

    /** @return iterable<string, array{PreferenceSource, string}> */
    public static function suppressedPreferences(): iterable
    {
        yield 'manual preference always wins' => [PreferenceSource::MANUAL, 'America/New_York'];
        yield 'same browser timezone is unchanged' => [PreferenceSource::BROWSER, 'Europe/Berlin'];
    }

    #[DataProvider('storageFailures')]
    public function testStorageFailuresReturnServiceUnavailableAndDispatchNothing(bool $failRead, bool $failWrite): void
    {
        $storage = new RecordingStorage(failRead: $failRead, failWrite: $failWrite);
        $dispatcher = new RecordingDispatcher();
        $request = $this->request('{"timezone":"Europe/Berlin"}', 'application/json');
        $stack = new RequestStack();
        $stack->push($request);

        $response = ($this->controller(new PreferenceWriteMarkingStorage($storage, $stack), $dispatcher))($request);

        self::assertSame(503, $response->getStatusCode());
        self::assertFalse($request->attributes->has(BrowserTimezoneController::PREFERENCE_WRITTEN_ATTRIBUTE));
        self::assertSame([], $dispatcher->events);
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function storageFailures(): iterable
    {
        yield 'read failure' => [true, false];
        yield 'write failure' => [false, true];
    }

    public function testConfiguredCsrfProtectionRejectsInvalidTokenBeforeStorage(): void
    {
        $storage = new RecordingStorage();
        $dispatcher = new RecordingDispatcher();
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects(self::once())->method('isTokenValid')->with(self::callback(
            static fn (CsrfToken $token): bool => 'browser-test' === $token->getId() && 'bad' === $token->getValue(),
        ))->willReturn(false);
        $controller = new BrowserTimezoneController($storage, $this->clock(), $dispatcher, $csrf, 'browser-test', 'X-Timezone-Csrf');
        $request = $this->request('{"timezone":"Europe/Berlin"}', 'application/json');
        $request->headers->set('X-Timezone-Csrf', 'bad');

        self::assertSame(403, $controller($request)->getStatusCode());
        self::assertSame(0, $storage->reads);
        self::assertSame([], $dispatcher->events);
    }

    public function testCsrfProtectionUsesDefaultTokenIdAndHeader(): void
    {
        $storage = new RecordingStorage();
        $dispatcher = new RecordingDispatcher();
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects(self::once())->method('isTokenValid')->with(self::callback(
            static fn (CsrfToken $token): bool => 'lunetics_timezone.preference' === $token->getId() && 'default-token' === $token->getValue(),
        ))->willReturn(true);
        $controller = new BrowserTimezoneController($storage, $this->clock(), $dispatcher, $csrf);
        $request = $this->request('{"timezone":"Europe/Berlin"}', 'application/json');
        $request->headers->set('X-CSRF-Token', 'default-token');

        self::assertSame(204, $controller($request)->getStatusCode());
    }

    private function controller(TimezonePreferenceStorageInterface $storage, RecordingDispatcher $dispatcher): BrowserTimezoneController
    {
        return new BrowserTimezoneController($storage, $this->clock(), $dispatcher);
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-02-03T04:05:06Z');
            }
        };
    }

    private function request(string $body, ?string $contentType): Request
    {
        $server = null === $contentType ? [] : ['CONTENT_TYPE' => $contentType];

        return Request::create('/_lunetics/timezone/browser', 'POST', [], [], [], $server, $body);
    }

    private function preference(string $timezone, PreferenceSource $source): TimezonePreference
    {
        return new TimezonePreference(TimezoneId::fromString($timezone), $source, new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }
}

final class RecordingStorage implements TimezonePreferenceStorageInterface
{
    public int $reads = 0;
    /** @var list<TimezonePreference> */
    public array $writes = [];

    public function __construct(
        private readonly ?TimezonePreferenceRead $result = null,
        private readonly bool $failRead = false,
        private readonly bool $failWrite = false,
    ) {
    }

    public function read(Request $request): TimezonePreferenceRead
    {
        ++$this->reads;
        if ($this->failRead) {
            throw TimezoneStorageException::operationFailed('read', new \RuntimeException('unavailable'));
        }

        return $this->result ?? new TimezonePreferenceRead(PreferenceReadStatus::ABSENT);
    }

    public function write(Request $request, Response $response, TimezonePreference $preference): void
    {
        if ($this->failWrite) {
            throw TimezoneStorageException::operationFailed('write', new \RuntimeException('unavailable'));
        }
        $this->writes[] = $preference;
    }

    public function clear(Request $request, Response $response): void
    {
    }
}

final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        return $event;
    }
}
