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

use Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserManager;

/**
 * TimezoneGuesserManager Tests
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class TimezoneGuesserManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTimezoneGuessingInvalidGuesser()
    {
        $guesserManager = new TimezoneGuesserManager(array('foo'));
        $guesserManager->addGuesser($this->getGuesserMock(), 'bar');
        $guesserManager->runTimezoneGuessing($this->getRequest());
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function getGuesserMock()
    {
        return $this->getMock('Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserInterface');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        $request = Request::create('/hello-world', 'GET');

        return $request;
    }
}
