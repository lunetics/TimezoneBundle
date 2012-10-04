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

use Lunetics\TimezoneBundle\TimezoneGuesser\GeoTimezoneGuesser;

/**
 * GeoTimezoneGuesser Tests
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class GeoTimezoneGuesserTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider getRequestWithValidIp
     *
     * @param $request
     */
    public function testValidIp($request)
    {
        $guesser = new GeoTimezoneGuesser;
        $this->assertInternalType('string', $guesser->guessTimezone($request));
        $this->assertInternalType('string', $guesser->getIdentifiedTimezone());
    }

    /**
     * @dataProvider getRequestWithInvalidIp
     *
     * @param $request
     */
    public function testInvalidIp($request)
    {
        $guesser = new GeoTimezoneGuesser;
        $this->assertFalse($guesser->guessTimezone($request));
    }

    public function getRequestWithValidIp()
    {
        $ipList = array(
            '69.147.83.199',
            '79.125.119.210',
            '188.94.27.25',
            '181.64.200.235',
            '174.84.251.45'
        );

        return $this->getDataProvider($ipList);
    }

    public function getRequestWithInvalidIp()
    {
        $ipList = array(
            '10.0.0.1',
            '127.0.0.1',
            '192.168.0.1'
        );

        return $this->getDataProvider($ipList);
    }

    public function getDataProvider($ips)
    {
        $data = array();
        foreach ($ips as $ip) {
            $request = $this->getRequestMock($ip);
            $data[] = array($request);
        }

        return $data;
    }

    public function getRequestMock($ip)
    {

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request', array('getClientIp'));
        $request->expects($this->any())
                ->method('getClientIp')
                ->will($this->returnValue($ip));

        return $request;
    }
}