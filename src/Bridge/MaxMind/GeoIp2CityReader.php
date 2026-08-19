<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\MaxMind;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use MaxMind\Db\Reader\InvalidDatabaseException;

/** Adapts an already constructed geoip2/geoip2 database reader. */
final readonly class GeoIp2CityReader implements MaxMindCityReaderInterface
{
    public function __construct(private Reader $reader)
    {
    }

    public function timezoneForIp(string $ipAddress): ?string
    {
        try {
            return $this->reader->city($ipAddress)->location->timeZone;
        } catch (AddressNotFoundException) {
            return null;
        } catch (InvalidDatabaseException|\BadMethodCallException $exception) {
            throw TimezoneResolverException::maxMindLookupFailed($exception);
        }
    }
}
