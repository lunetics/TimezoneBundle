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

/**
 * This describes the Interface for the TimezoneGuesser
 *
 * @author Christophe Willemsen <willemsen.christophe@gmail.com>
 * @author Matthias Breddin <mb@lunetics.com>
 */
interface TimezoneGuesserInterface
{
    /**
     * Returns the Timezone could be guessed, false on no guessing
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return boolean
     */
    public function guessTimezone(Request $request);

    /**
     * Returns the Identified Timezone, null if no Timezone is set/found
     *
     * @return string|null
     */
    public function getIdentifiedTimezone();
}
