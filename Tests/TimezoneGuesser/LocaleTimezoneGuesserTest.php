<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\Tests\TimezoneGuesser;

use Symfony\Component\HttpFoundation\Request;

use Lunetics\TimezoneBundle\TimezoneGuesser\LocaleTimezoneGuesser;

/**
 * LocaleTimezoneGuesser Tests
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class LocaleTimezoneGuesserTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        if (!extension_loaded('geoip')) {
            $this->markTestSkipped();
        }
    }

    /**
     * @dataProvider getRequestWithValidLocale
     *
     * @param $locale
     */
    public function testValidLocale($locale)
    {
        $guesser = new LocaleTimezoneGuesser;
        $request = $this->getRequestMock($locale);
        $this->assertInternalType('string', $guesser->guessTimezone($request));
        $this->assertInternalType('string', $guesser->getIdentifiedTimezone());
    }

    /**
     * @dataProvider getRequestWithInvalidLocale
     *
     * @param $locale
     */
    public function testInvalidLocale($locale)
    {
        $guesser = new LocaleTimezoneGuesser;
        $request = $this->getRequestMock($locale);
        $this->assertFalse($guesser->guessTimezone($request));
    }

    public function getRequestWithValidLocale()
    {
        return array(
            array('de_DE'),
            array('it_IT'),
            array('ch_IE')
        );
    }

    public function getRequestWithInvalidLocale()
    {
        return array(
            array('en_US'),
            array('de'),
            array('en'),
            array('it'),
        );
    }


    public function getRequestMock($locale)
    {

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request', array('getLocale'));
        $request->expects($this->any())
                ->method('getLocale')
                ->will($this->returnValue($locale));

        return $request;
    }
}