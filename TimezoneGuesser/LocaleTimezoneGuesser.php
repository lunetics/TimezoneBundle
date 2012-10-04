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
 * Guesser Class to identify the timezone by the Request Locale
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class LocaleTimezoneGuesser implements TimezoneGuesserInterface
{
    private $identifiedTimezone;

    /**
     * {@inheritDoc}
     */
    public function guessTimezone(Request $request)
    {
        $countryCode = \Locale::getRegion($request->getLocale());
        if (null !== $countryCode) {
            // Needs the @, cause otherwise the geoip_region_by_name function will send a PHP Notice
            $this->identifiedTimezone = @geoip_time_zone_by_country_and_region($countryCode);
            if (false !== $this->identifiedTimezone) {
                return $this->identifiedTimezone;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifiedTimezone()
    {
        return $this->identifiedTimezone;
    }
}
