<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\MaxMind;

use GeoIp2\Database\Reader;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;

/** Lazily opens a local GeoLite2-City, GeoIP2-City, or City-compatible database. */
final class LazyGeoIp2CityReader implements MaxMindCityReaderInterface
{
    private ?GeoIp2CityReader $adapter = null;

    /** @var \Closure(string, list<string>): Reader */
    private \Closure $readerFactory;

    /** @var list<string> */
    private readonly array $locales;

    /**
     * The optional factory is an injection seam for applications with custom
     * Reader construction and for tests that do not carry an MMDB fixture.
     *
     * @param list<string> $locales
     * @param null|callable(string, list<string>): Reader $readerFactory
     */
    public function __construct(
        private readonly string $databasePath,
        array $locales = ['en'],
        ?callable $readerFactory = null,
    ) {
        $this->locales = $locales;
        $this->readerFactory = null === $readerFactory
            ? self::openReader(...)
            : $readerFactory(...);
    }

    public function timezoneForIp(string $ipAddress): ?string
    {
        if (null === $this->adapter) {
            try {
                $this->adapter = new GeoIp2CityReader(($this->readerFactory)($this->databasePath, $this->locales));
            } catch (\Exception $exception) {
                throw TimezoneResolverException::maxMindDatabaseFailed($exception);
            }
        }

        return $this->adapter->timezoneForIp($ipAddress);
    }

    /** @param list<string> $locales */
    private static function openReader(string $path, array $locales): Reader
    {
        return new Reader($path, $locales);
    }
}
