<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\MaxMind;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use Lunetics\TimezoneBundle\Bridge\MaxMind\GeoIp2CityReader;
use Lunetics\TimezoneBundle\Bridge\MaxMind\LazyGeoIp2CityReader;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeoIp2CityReader::class)]
#[CoversClass(LazyGeoIp2CityReader::class)]
final class GeoIp2CityReaderTest extends TestCase
{
    public function testInjectedReaderExtractsOnlyLocationTimezone(): void
    {
        $reader = new StubGeoIp2Reader(new City([
            'city' => ['names' => ['en' => 'Sensitive city']],
            'location' => ['time_zone' => 'Europe/Berlin'],
        ]));

        self::assertSame('Europe/Berlin', (new GeoIp2CityReader($reader))->timezoneForIp('8.8.8.8'));
    }

    public function testAddressNotFoundMapsToNullAndUnsupportedDatabaseIsWrapped(): void
    {
        self::assertNull((new GeoIp2CityReader(new StubGeoIp2Reader(
            new AddressNotFoundException('not found'),
        )))->timezoneForIp('8.8.8.8'));

        $this->expectException(TimezoneResolverException::class);
        $this->expectExceptionMessage('The MaxMind City lookup failed.');
        (new GeoIp2CityReader(new StubGeoIp2Reader(
            new \BadMethodCallException('country database'),
        )))->timezoneForIp('8.8.8.8');
    }

    public function testLocalReaderIsConstructedOnlyOnFirstLookup(): void
    {
        $constructions = 0;
        $lazy = new LazyGeoIp2CityReader(
            '/not/opened/eagerly.mmdb',
            readerFactory: static function (string $path, array $locales) use (&$constructions): Reader {
                ++$constructions;
                return new StubGeoIp2Reader(new City(['location' => ['time_zone' => 'UTC']]));
            },
        );

        self::assertSame(0, $constructions);
        self::assertSame('UTC', $lazy->timezoneForIp('1.1.1.1'));
        self::assertSame('UTC', $lazy->timezoneForIp('8.8.8.8'));
        self::assertSame(1, $constructions);
    }

    public function testLazyConstructionFailureIsWrappedWithoutPathDisclosure(): void
    {
        $lazy = new LazyGeoIp2CityReader(
            '/sensitive/path.mmdb',
            readerFactory: static fn (string $path, array $locales): never => throw new \InvalidArgumentException($path),
        );

        try {
            $lazy->timezoneForIp('8.8.8.8');
            self::fail('Expected resolver exception.');
        } catch (TimezoneResolverException $exception) {
            self::assertSame('The MaxMind City database could not be opened.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }
}

final class StubGeoIp2Reader extends Reader
{
    public function __construct(private readonly City|\Exception $result) {}

    public function city(string $ipAddress): City
    {
        if ($this->result instanceof \Exception) {
            throw $this->result;
        }
        return $this->result;
    }
}
