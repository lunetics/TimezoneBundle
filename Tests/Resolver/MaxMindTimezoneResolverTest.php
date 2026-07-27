<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Bridge\MaxMind\CallableMaxMindCityReader;
use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindCityReaderInterface;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolver\MaxMindTimezoneResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(MaxMindTimezoneResolver::class)]
#[CoversClass(CallableMaxMindCityReader::class)]
final class MaxMindTimezoneResolverTest extends TestCase
{
    public function testPublicAddressResolvesAndNoRecordReturnsNull(): void
    {
        $resolver = new MaxMindTimezoneResolver(new CallableMaxMindCityReader(static fn (string $ip): string => 'Europe/Berlin'));
        $resolution = $resolver->resolve($this->requestFrom('8.8.8.8'));
        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('maxmind_city', $resolution->source);
        self::assertSame(ResolutionKind::INFERRED, $resolution->kind);

        self::assertNull((new MaxMindTimezoneResolver(new CallableMaxMindCityReader(static fn (string $ip): null => null)))->resolve($this->requestFrom('1.1.1.1')));
    }

    /** @return iterable<string, array{string}> */
    public static function nonPublicAddresses(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'private' => ['10.0.0.1'];
        yield 'link local' => ['169.254.1.1'];
        yield 'multicast' => ['224.0.0.1'];
        yield 'reserved documentation range' => ['192.0.2.1'];
        yield 'ipv6 loopback' => ['::1'];
        yield 'ipv6 link local' => ['fe80::1'];
        yield 'ipv6 well-known nat64' => ['64:ff9b::808:808'];
        yield 'ipv6 local-use nat64' => ['64:ff9b:1::1'];
        yield 'ipv6 6to4' => ['2002:a00:1::1'];
    }

    #[DataProvider('nonPublicAddresses')]
    public function testNonPublicAddressesNeverCallReader(string $ipAddress): void
    {
        $reader = new class implements MaxMindCityReaderInterface {
            public bool $called = false;
            public function timezoneForIp(string $ipAddress): null { $this->called = true; return null; }
        };
        self::assertNull((new MaxMindTimezoneResolver($reader))->resolve($this->requestFrom($ipAddress)));
        self::assertFalse($reader->called);
    }

    public function testReaderFailureIsRedactedAndCallableDoesNotCatchErrors(): void
    {
        $resolver = new MaxMindTimezoneResolver(new CallableMaxMindCityReader(
            static fn (string $ip): never => throw new \RuntimeException('lookup failed for '.$ip),
        ));
        try {
            $resolver->resolve($this->requestFrom('8.8.8.8'));
            self::fail('Expected resolver exception.');
        } catch (TimezoneResolverException $exception) {
            self::assertStringNotContainsString('8.8.8.8', $exception->getMessage());
        }

        $broken = new CallableMaxMindCityReader(static fn (string $ip): never => throw new \TypeError('broken'));
        $this->expectException(\TypeError::class);
        (new MaxMindTimezoneResolver($broken))->resolve($this->requestFrom('8.8.4.4'));
    }

    private function requestFrom(string $ipAddress): Request
    {
        return Request::create('/', server: ['REMOTE_ADDR' => $ipAddress]);
    }
}
