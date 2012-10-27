<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle;

/**
 * Defines aliases for Events in this bundle
 */
final class TimezoneBundleEvents
{
    /**
     * The lunetics_timezone.change event is thrown each time the timezone changes.
     *
     * The event listener receives an Lunetics\TimezoneBundle\Event\FilterTimezoneEvent instance
     *
     * @var string
     *
     */
    const TIMEZONE_CHANGE = 'lunetics_timezone.change';
}