<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Filter for the timezone event
 */
class FilterTimezoneEvent extends TimezoneEvent
{
    /**
     * Constructor
     *
     * @param string $timezone
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($timezone)
    {
        $this->setTimezone($timezone);
    }
}