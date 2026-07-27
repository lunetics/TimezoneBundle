<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindCityReaderInterface;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

final readonly class MaxMindTimezoneResolver implements TimezoneResolverInterface
{
    public function __construct(private MaxMindCityReaderInterface $reader)
    {
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        $ipAddress = $request->getClientIp();
        if (null === $ipAddress || false === filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) || IpUtils::checkIp($ipAddress, self::explicitlyNonPublicRanges())) {
            return null;
        }

        try {
            $timezone = $this->reader->timezoneForIp($ipAddress);
        } catch (\Exception $exception) {
            throw TimezoneResolverException::maxMindLookupFailed($exception);
        }
        if (null === $timezone) {
            return null;
        }

        return new TimezoneResolution(
            is_string($timezone) ? TimezoneId::fromString($timezone) : $timezone,
            'maxmind_city',
            ResolutionKind::INFERRED,
        );
    }

    /** @return list<string> */
    private static function explicitlyNonPublicRanges(): array
    {
        // Union of Symfony's private-subnet list (its contents differ between
        // Symfony versions) and networks PHP's reserved-range filter does not
        // consistently reject: multicast, documentation, benchmarking,
        // discard-only and other special-purpose ranges.
        return array_values(array_unique(array_merge(IpUtils::PRIVATE_SUBNETS, [
            '0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24',
            '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24',
            '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
            '::/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48',
            '100::/64', '2001:2::/48', '2001:db8::/32', '2002::/16',
            'fc00::/7', 'fe80::/10', 'ff00::/8',
        ])));
    }
}
