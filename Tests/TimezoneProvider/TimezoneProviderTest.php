<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */


namespace Lunetics\TimezoneBundle\Tests\TimezoneProvider;

use Lunetics\TimezoneBundle\TimezoneProvider\TimezoneProvider;
use Lunetics\TimezoneBundle\Validator\TimezoneValidator;
use Symfony\Component\HttpFoundation\Request;

use Lunetics\TimezoneBundle\TimezoneGuesser\GeoTimezoneGuesser;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * GeoTimezoneGuesser Tests
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class TimezoneProvidertTest extends \PHPUnit_Framework_TestCase
{

    public function testDefaultTimezone()
    {
        $timezoneProvider = new TimezoneProvider($this->getValidatorMock());
        $this->assertInternalType('string', $timezoneProvider->getTimezone());
        $this->assertEquals('UTC', $timezoneProvider->getTimezone());
    }

    public function testCustomDefaultTimezone()
    {
        $timezoneProvider = new TimezoneProvider($this->getValidatorMock(), 'Europe/Berlin');
        $this->assertInternalType('string', $timezoneProvider->getTimezone());
        $this->assertEquals('Europe/Berlin', $timezoneProvider->getTimezone());
    }

    /**
     * @expectedException Lunetics\TimezoneBundle\Exception\TimezoneException
     */
    public function testInvalidTimezone()
    {
        $timezoneProvider = new TimezoneProvider($this->getValidatorMock(true));
    }

    public function testGetTimezone()
    {
        $timezoneProvider = new TimezoneProvider($this->getValidatorMock());
        $this->assertInternalType('string', $timezoneProvider->getTimezone());
        $this->assertEquals('UTC', $timezoneProvider->getTimezone());
    }

    public function testSetTimezone()
    {
        $timezoneProvider = new TimezoneProvider($this->getValidatorMock());
        $this->assertInternalType('string', $timezoneProvider->getTimezone());
        $this->assertEquals('UTC', $timezoneProvider->getTimezone());

        $return = $timezoneProvider->setTimezone('Europe/Berlin');
        $this->assertInstanceOf(get_class($timezoneProvider), $return);
        $this->assertEquals('Europe/Berlin', $timezoneProvider->getTimezone());
    }

    public function testGetTimezoneDateTimeObject()
    {
        $timezoneProvider = new TimezoneProvider($this->getValidatorMock());
        $object = $timezoneProvider->getDateTimezoneObject();
        $this->assertInstanceOf('\DateTimezone', $object);
        $this->assertEquals('UTC', $object->getName());
    }

    protected function getValidatorMock($returnError = false)
    {
        $mock = $this->getMockBuilder('Symfony\Component\Validator\ValidatorInterface')->disableOriginalConstructor()->getMock();
        $mock->expects($this->any())
            ->method('validateValue')
            ->willReturn($returnError ? new ConstraintViolationList(array($this->getConstraintViolationMock())) : new ConstraintViolationList());

        return $mock;
    }

    protected function getConstraintViolationMock()
    {
        return $this->getMockBuilder('Symfony\Component\Validator\ConstraintViolation')->disableOriginalConstructor()->getMock();
    }
}