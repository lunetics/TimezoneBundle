<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Storage;

use Lunetics\TimezoneBundle\Storage\CookieTimezoneStorage;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\TimezonePreference;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CookieTimezoneStorageTest extends TestCase
{
    public function testAbsentCookieIsReported(): void
    {
        self::assertSame(PreferenceReadStatus::ABSENT, $this->storage(new MutableClock('2026-01-01T00:00:00Z'))->read(new Request())->status);
    }

    public function testSignedCookieRoundTripsAndTamperingIsRejected(): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $storage = $this->storage($clock);
        $response = new Response();
        $preference = $this->preference('America/New_York', PreferenceSource::MANUAL, $clock->now());

        $storage->write(Request::create('https://example.test'), $response, $preference);
        $cookie = $this->onlyCookie($response);
        $value = $cookie->getValue();
        self::assertIsString($value);
        $read = $storage->read($this->requestWithCookie($value));

        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertNotNull($read->preference);
        self::assertTrue($preference->timezone->equals($read->preference->timezone));
        self::assertSame($preference->source, $read->preference->source);
        self::assertEquals($preference->recordedAt, $read->preference->recordedAt);

        $last = substr($value, -1);
        $tampered = substr($value, 0, -1).('A' === $last ? 'B' : 'A');
        self::assertSame(PreferenceReadStatus::INVALID, $storage->read($this->requestWithCookie($tampered))->status);
    }

    /** @param array<string, string>|bool|float|int|string|null $value */
    #[DataProvider('malformedCookies')]
    public function testMalformedAndOversizeCookiesAreRejected(array|bool|float|int|string|null $value): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $request = new Request();
        $request->cookies->set('_lunetics_timezone', $value);

        self::assertSame(PreferenceReadStatus::INVALID, $this->storage($clock, maxEncodedSize: 32)->read($request)->status);
    }

    /** @return iterable<string, array{array<string, string>|string}> */
    public static function malformedCookies(): iterable
    {
        yield 'not an envelope' => ['not-an-envelope'];
        yield 'invalid base64url' => ['abc.%%%'];
        yield 'oversize' => [str_repeat('x', 33)];
        yield 'non-string cookie input' => [['nested' => 'value']];
    }

    public function testExpiredAndFutureDatedCookiesAreRejected(): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $storage = $this->storage($clock, maxAge: 3600, futureSkew: 60);

        $expired = $this->encodedCookie($storage, new \DateTimeImmutable('2025-12-31T22:59:59Z'));
        self::assertSame(PreferenceReadStatus::EXPIRED, $storage->read($this->requestWithCookie($expired))->status);

        $future = $this->encodedCookie($storage, new \DateTimeImmutable('2026-01-01T00:01:01Z'));
        self::assertSame(PreferenceReadStatus::INVALID, $storage->read($this->requestWithCookie($future))->status);
    }

    public function testSignedCookieRejectsNonCanonicalParseableTimestamp(): void
    {
        $payload = json_encode(['v' => 1, 'timezone' => 'Europe/Berlin', 'source' => 'browser', 'recorded_at' => '2026-01-01 00:00:00 UTC'], JSON_THROW_ON_ERROR);
        $payload64 = $this->base64UrlEncode($payload);
        $key = hash_hkdf('sha256', 'test-only-secret', 32, 'lunetics-timezone-cookie-v1');
        $cookie = $payload64.'.'.$this->base64UrlEncode(hash_hmac('sha256', $payload64, $key, true));

        self::assertSame(PreferenceReadStatus::INVALID, $this->storage(new MutableClock('2026-01-01T00:00:00Z'))->read($this->requestWithCookie($cookie))->status);
    }

    #[DataProvider('schemes')]
    public function testSecureAutoFollowsRequestScheme(string $url, bool $secure): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $response = new Response();
        $this->storage($clock)->write(Request::create($url), $response, $this->preference('Europe/Berlin', PreferenceSource::BROWSER, $clock->now()));

        self::assertSame($secure, $this->onlyCookie($response)->isSecure());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function schemes(): iterable
    {
        yield 'http' => ['http://example.test', false];
        yield 'https' => ['https://example.test', true];
    }

    public function testWriteAndClearUseConfiguredCookieAttributes(): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $storage = new CookieTimezoneStorage('test-only-secret', $clock, 'tz_pref', 7200, '/account', 'example.test', true, false, Cookie::SAMESITE_STRICT);
        $request = Request::create('https://example.test/account');
        $writtenResponse = new Response();

        $storage->write($request, $writtenResponse, $this->preference('Europe/Berlin', PreferenceSource::BROWSER, $clock->now()));
        $written = $this->onlyCookie($writtenResponse);
        self::assertSame('tz_pref', $written->getName());
        self::assertSame('/account', $written->getPath());
        self::assertSame('example.test', $written->getDomain());
        self::assertTrue($written->isSecure());
        self::assertFalse($written->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $written->getSameSite());
        self::assertSame($clock->now()->getTimestamp() + 7200, $written->getExpiresTime());

        $clearedResponse = new Response();
        $storage->clear($request, $clearedResponse);
        $cleared = $this->onlyCookie($clearedResponse);
        self::assertSame('tz_pref', $cleared->getName());
        self::assertLessThanOrEqual(time(), $cleared->getExpiresTime());
        self::assertSame('/account', $cleared->getPath());
        self::assertSame('example.test', $cleared->getDomain());
        self::assertTrue($cleared->isSecure());
        self::assertFalse($cleared->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $cleared->getSameSite());
    }

    public function testClearKeepsAPreferenceWrittenToTheSameResponse(): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $storage = $this->storage($clock);
        $request = Request::create('https://example.test');
        $response = new Response();

        $storage->write($request, $response, $this->preference('Europe/Paris', PreferenceSource::MANUAL, $clock->now()));
        $storage->clear($request, $response);

        $cookie = $this->onlyCookie($response);
        self::assertNotSame('', (string) $cookie->getValue(), 'clear() must not clobber a preference written to this response.');
        self::assertGreaterThan($clock->now()->getTimestamp(), $cookie->getExpiresTime());
        $read = $storage->read($this->requestWithCookie((string) $cookie->getValue()));
        self::assertSame(PreferenceReadStatus::VALID, $read->status);
        self::assertSame('Europe/Paris', $read->preference?->timezone->value());
    }

    public function testClearRemovesAStaleCookieWhenNoFreshWriteIsPresent(): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $response = new Response();

        $this->storage($clock)->clear(Request::create('https://example.test'), $response);

        $cookie = $this->onlyCookie($response);
        self::assertSame('', (string) $cookie->getValue());
        self::assertLessThan($clock->now()->getTimestamp(), $cookie->getExpiresTime());
    }

    public function testConstructorRejectsInvalidCookieName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CookieTimezoneStorage('test-only-secret', new MutableClock('2026-01-01T00:00:00Z'), name: 'invalid cookie');
    }

    public function testConstructorRejectsSecurePrefixWithoutExplicitSecureTransport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('__Secure- cookies require secure transport.');
        new CookieTimezoneStorage('test-only-secret', new MutableClock('2026-01-01T00:00:00Z'), name: '__Secure-tz');
    }

    public function testSecurePrefixWithExplicitSecureTransportWritesSecureCookie(): void
    {
        $clock = new MutableClock('2026-01-01T00:00:00Z');
        $storage = new CookieTimezoneStorage('test-only-secret', $clock, name: '__Secure-tz', secure: true);
        $response = new Response();

        $storage->write(Request::create('https://example.test'), $response, $this->preference('Europe/Berlin', PreferenceSource::BROWSER, $clock->now()));

        self::assertTrue($this->onlyCookie($response)->isSecure());
    }

    private function storage(MutableClock $clock, int $maxAge = 31536000, int $futureSkew = 60, int $maxEncodedSize = 4096): CookieTimezoneStorage
    {
        return new CookieTimezoneStorage('test-only-secret', $clock, maxAge: $maxAge, futureSkew: $futureSkew, maxEncodedSize: $maxEncodedSize);
    }

    private function preference(string $timezone, PreferenceSource $source, \DateTimeImmutable $recordedAt): TimezonePreference
    {
        return new TimezonePreference(TimezoneId::fromString($timezone), $source, $recordedAt);
    }

    private function encodedCookie(CookieTimezoneStorage $storage, \DateTimeImmutable $recordedAt): string
    {
        $response = new Response();
        $storage->write(Request::create('https://example.test'), $response, $this->preference('Europe/Berlin', PreferenceSource::BROWSER, $recordedAt));

        $value = $this->onlyCookie($response)->getValue();
        self::assertIsString($value);

        return $value;
    }

    private function requestWithCookie(string $value): Request
    {
        $request = new Request();
        $request->cookies->set('_lunetics_timezone', $value);

        return $request;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function onlyCookie(Response $response): Cookie
    {
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        return $cookies[0];
    }
}

final class MutableClock implements ClockInterface
{
    private \DateTimeImmutable $time;

    public function __construct(string $time)
    {
        $this->time = new \DateTimeImmutable($time);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->time;
    }
}
