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
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param string $timezone
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($timezone, Request $request)
    {
        if (!is_string($timezone) || null == $timezone || '' == $timezone) {
            throw new \InvalidArgumentException(sprintf('Wrong type, expected \'string\' got \'%s\'', gettype($timezone)));
        }

        $this->timezone = $timezone;
        $this->request = $request;
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

    /**
     * Returns the request
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}