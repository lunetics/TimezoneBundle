<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Resolver\HeaderTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\HeaderTrustMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(HeaderTimezoneResolver::class)]
final class HeaderTimezoneResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
    }

    public function testAnyModeAcceptsASingleValidHeader(): void
    {
        $request = $this->requestWithHeader('Europe/Berlin');

        $resolution = (new HeaderTimezoneResolver(trustMode: HeaderTrustMode::ANY))->resolve($request);

        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('trusted_header', $resolution->source);
    }

    public function testAllowlistUsesRawRemoteAddressRatherThanForwardedClientIp(): void
    {
        $request = $this->requestWithHeader('UTC', '10.20.30.40');
        $request->headers->set('X-Forwarded-For', '203.0.113.10');
        $resolver = new HeaderTimezoneResolver(
            trustMode: HeaderTrustMode::ALLOWLIST,
            trustedSources: ['10.20.30.0/24'],
        );

        self::assertSame('UTC', $resolver->resolve($request)?->timezone->value());

        $request->server->set('REMOTE_ADDR', '192.0.2.1');
        self::assertNull($resolver->resolve($request));
    }

    public function testFrameworkModeRequiresAFrameworkTrustedProxy(): void
    {
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        $trusted = $this->requestWithHeader('UTC', '10.0.0.1');
        $untrusted = $this->requestWithHeader('UTC', '10.0.0.2');
        $resolver = new HeaderTimezoneResolver(trustMode: HeaderTrustMode::FRAMEWORK);

        self::assertSame('UTC', $resolver->resolve($trusted)?->timezone->value());
        self::assertNull($resolver->resolve($untrusted));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidHeaderValues(): iterable
    {
        yield 'empty' => ['   '];
        yield 'comma separated' => ['UTC, Europe/Berlin'];
        yield 'unknown timezone' => ['Invalid/Timezone'];
        yield 'too long' => [str_repeat('A', 256)];
    }

    #[DataProvider('invalidHeaderValues')]
    public function testRejectsMalformedOrInvalidHeaderValues(string $value): void
    {
        $this->expectException(InvalidTimezoneException::class);

        (new HeaderTimezoneResolver(trustMode: HeaderTrustMode::ANY))->resolve($this->requestWithHeader($value));
    }

    public function testRejectsMultipleHeaderLines(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-Timezone', ['UTC', 'Europe/Berlin']);

        $this->expectException(InvalidTimezoneException::class);
        (new HeaderTimezoneResolver(trustMode: HeaderTrustMode::ANY))->resolve($request);
    }

    public function testReturnsNullWhenTrustedRequestHasNoHeader(): void
    {
        self::assertNull((new HeaderTimezoneResolver(trustMode: HeaderTrustMode::ANY))->resolve(Request::create('/')));
    }

    public function testAllowlistModeRequiresSources(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HeaderTimezoneResolver(trustMode: HeaderTrustMode::ALLOWLIST);
    }

    private function requestWithHeader(string $value, string $remoteAddress = '127.0.0.1'): Request
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => $remoteAddress]);
        $request->headers->set('X-Timezone', $value);

        return $request;
    }
}
