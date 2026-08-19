<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Storage;

use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\SessionTimezoneStorage;
use Lunetics\TimezoneBundle\Storage\TimezonePreference;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead;
use Lunetics\TimezoneBundle\Exception\TimezoneStorageException;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;

final class StorageDomainTest extends TestCase
{
    public function testPreferenceUsesUtcAndReadInvariantIsEnforced(): void
    {
        $preference = new TimezonePreference(TimezoneId::fromString('Europe/Berlin'), PreferenceSource::MANUAL, new \DateTimeImmutable('2025-01-01 12:00:00+02:00'));
        self::assertSame('UTC', $preference->recordedAt->getTimezone()->getName());

        $this->expectException(\InvalidArgumentException::class);
        new TimezonePreferenceRead(PreferenceReadStatus::VALID);
    }

    public function testSessionReadDoesNotStartSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        self::assertSame(PreferenceReadStatus::ABSENT, (new SessionTimezoneStorage())->read($request)->status);
        self::assertFalse($session->isStarted());
    }

    public function testLazySessionReadsAndClearsExistingCookieBackedPreference(): void
    {
        $preference = ['v' => 1, 'timezone' => 'Europe/Berlin', 'source' => 'browser', 'recorded_at' => '2025-01-01T10:00:00+00:00'];
        $storage = new SessionTimezoneStorage();

        $readSession = $this->lazySessionRequest($preference, true);
        $read = $storage->read($readSession['request']);
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertTrue($readSession['session']->isStarted());

        $clearSession = $this->lazySessionRequest($preference, true);
        $storage->clear($clearSession['request'], new Response());
        self::assertTrue($clearSession['session']->isStarted());
        self::assertFalse($clearSession['session']->has('_lunetics_timezone'));
    }

    public function testLazySessionWithoutCookieIsInstantiatedButNotStartedForReadOrClear(): void
    {
        $preference = ['v' => 1, 'timezone' => 'Europe/Berlin', 'source' => 'browser', 'recorded_at' => '2025-01-01T10:00:00+00:00'];
        $storage = new SessionTimezoneStorage();

        foreach (['read', 'clear'] as $operation) {
            $lazy = $this->lazySessionRequest($preference, false);
            if ('read' === $operation) {
                self::assertSame(PreferenceReadStatus::ABSENT, $storage->read($lazy['request'])->status);
            } else {
                $storage->clear($lazy['request'], new Response());
            }
            self::assertSame(1, $lazy['factoryCalls']());
            self::assertFalse($lazy['session']->isStarted());
        }
    }

    public function testSessionRejectsNonCanonicalParseableTimestamp(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_lunetics_timezone', ['v' => 1, 'timezone' => 'Europe/Berlin', 'source' => 'browser', 'recorded_at' => '2025-01-01 10:00:00 UTC']);
        $request = new Request();
        $request->setSession($session);

        self::assertSame(PreferenceReadStatus::INVALID, (new SessionTimezoneStorage())->read($request)->status);
    }

    public function testSessionRoundTripAndInvalidEnvelope(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);
        $storage = new SessionTimezoneStorage();
        $preference = new TimezonePreference(TimezoneId::fromString('Europe/Berlin'), PreferenceSource::BROWSER, new \DateTimeImmutable('2025-01-01T10:00:00Z'));
        $storage->write($request, new Response(), $preference);
        self::assertSame(PreferenceReadStatus::VALID, $storage->read($request)->status);

        $session->set('_lunetics_timezone', ['v' => 1]);
        self::assertSame(PreferenceReadStatus::INVALID, $storage->read($request)->status);
    }

    public function testSessionWriteWithoutSessionWrapsSessionNotFoundException(): void
    {
        $preference = new TimezonePreference(TimezoneId::fromString('Europe/Berlin'), PreferenceSource::MANUAL, new \DateTimeImmutable('2026-01-01T00:00:00Z'));

        try {
            (new SessionTimezoneStorage())->write(new Request(), new Response(), $preference);
            self::fail('Expected storage exception.');
        } catch (TimezoneStorageException $exception) {
            self::assertSame('Timezone preference storage write failed.', $exception->getMessage());
            self::assertInstanceOf(SessionNotFoundException::class, $exception->getPrevious());
        }
    }

    /** @param array{v: int, timezone: string, source: string, recorded_at: string} $preference
     *  @return array{request: Request, session: Session, factoryCalls: \Closure(): int}
     */
    private function lazySessionRequest(array $preference, bool $withCookie): array
    {
        $sessionStorage = new MockArraySessionStorage();
        $sessionStorage->setSessionData(['_sf2_attributes' => ['_lunetics_timezone' => $preference]]);
        $session = new Session($sessionStorage);
        $factoryCalls = 0;
        $request = new Request();
        $request->setSessionFactory(static function () use ($session, &$factoryCalls): Session {
            ++$factoryCalls;

            return $session;
        });
        if ($withCookie) {
            $request->cookies->set($session->getName(), 'existing-session-id');
        }

        return ['request' => $request, 'session' => $session, 'factoryCalls' => static function () use (&$factoryCalls): int {
            return $factoryCalls;
        }];
    }
}
