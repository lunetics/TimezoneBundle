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
use Symfony\Component\HttpFoundation\Request;

/**
 * Filter for the timezone event
 */
class FilterTimezoneEvent extends Event
{
    /**
     * @var string
     */
    protected $timezone;

    /**
     * Constructor
     *
     * @param string $timezone
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($timezone)
    {
        if (!is_string($timezone) || null == $timezone || '' == $timezone) {
            throw new \InvalidArgumentException(sprintf('Wrong type, expected \'string\' got \'%s\'', gettype($timezone)));
        }

        $this->timezone = $timezone;
    }

    /**
     * Returns the timezone string
     *
     * @return string
     */
    public function getTimezone()
    {
        return $this->timezone;
    }
}