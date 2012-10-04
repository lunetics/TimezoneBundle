<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\TimezoneGuesser;

use Symfony\Component\HttpFoundation\Request;
use Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserInterface;

/**
 * Guesser Class to identify the timezone by the geoip pecl extension
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class GeoTimezoneGuesser implements TimezoneGuesserInterface
{
    private $identifiedTimezone;

    /**
     * {@inheritDoc}
     */
    public function guessTimezone(Request $request)
    {
        $ip = $request->getClientIp();
        // Returns false if IP is Private or localhost
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) || $ip == '127.0.0.1') {
            return false;
        }
        $geoIpResult = geoip_record_by_name($ip);
        if (!is_array($geoIpResult)) {
            return false;
        }
        $countryCode = $geoIpResult['country_code'];
        $region = $geoIpResult['region'];
        $this->identifiedTimezone = geoip_time_zone_by_country_and_region($countryCode, isset($region) ? $region : null);

        return $this->identifiedTimezone;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifiedTimezone()
    {
        return $this->identifiedTimezone;
    }
}
