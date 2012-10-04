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
class LocalemapperTimezoneGuesser implements TimezoneGuesserInterface
{
    private $localeMapper;
    private $identifiedTimezone;

    /**
     * Constructur
     *
     * @param array $localeMapper
     */
    public function __construct(array $localeMapper)
    {
        $this->localeMapper = $localeMapper;
    }

    /**
     * {@inheritDoc}
     */
    public function guessTimezone(Request $request)
    {
        $locale = $request->getLocale();
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
            $this->identifiedTimezone = $this->findLocale($locale, 'locales_full');
        } elseif (preg_match('/^[a-z]{2}$/', $locale)) {
            $this->identifiedTimezone = $this->findLocale($locale, 'locales_lang_only');
        }
        if (null !== $this->identifiedTimezone) {
            return $this->identifiedTimezone;
        }

        return false;
    }

    /**
     * Returns the timezone for a given locale for the given mapper
     *
     * @param string $locale     Locale
     * @param string $localeType Array Key of the list
     *
     * @return string
     */
    protected function findLocale($locale, $localeType)
    {
        return isset($this->localeMapper[$localeType][$locale]) ? $this->localeMapper[$localeType][$locale] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifiedTimezone()
    {
        return $this->identifiedTimezone;
    }
}
