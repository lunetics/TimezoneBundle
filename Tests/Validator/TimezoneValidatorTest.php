<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\Tests\Validator;

use Lunetics\TimezoneBundle\Validator\Timezone;
use Lunetics\TimezoneBundle\Validator\TimezoneValidator;

/**
 * Test for the Timezone Validator
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class TimezoneValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;

    /**
     * Setup
     */
    public function setUp()
    {
        \PHPUnit_Framework_Error_Deprecated::$enabled = FALSE;
        $this->context = $this->getContext();
    }

    /**
     * Provider for Valid Timezones
     *
     * @return array
     */
    public function validTimezones()
    {
        return array(
            array('Europe/Berlin'),
            array('UTC'),
            array('America/New_York')
        );
    }

    /**
     * Test if Timezone is valid
     *
     * @param string $timezone
     *
     * @dataProvider validTimezones
     */
    public function testTimezoneIsValid($timezone)
    {
        $constraint = new Timezone();
        $this->context->expects($this->never())
                ->method('addViolation');
        $this->getTimezoneValidator()->validate($timezone, $constraint);
    }

    /**
     * Provider for Invalid Timezones
     *
     * @return array
     */
    public function invalidTimezones()
    {
        return array(
            array('GMT'),
            array('NONE'),
            array('EST')
        );
    }

    /**
     * Test if Timezone is invalid
     *
     * @param string $timezone
     *
     * @dataProvider invalidTimezones
     */
    public function testTimezoneIsInvalid($timezone)
    {
        $constraint = new Timezone();
        $this->context->expects($this->once())
                ->method('addViolation')
                ->with($this->equalTo($constraint->message), $this->equalTo(array('%string%' => $timezone)));
        $this->getTimezoneValidator()->validate($timezone, $constraint);
    }

    /**
     * Returns an Executioncontext
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getContext()
    {
        return $this->getMockBuilder('Symfony\Component\Validator\Context\ExecutionContext')->disableOriginalConstructor()->getMock();
    }

    /**
     * Returns the TimezoneValidator
     *
     * @return \Lunetics\TimezoneBundle\Validator\TimezoneValidator
     */
    private function getTimezoneValidator()
    {
        $validator = new TimezoneValidator();
        $validator->initialize($this->context);

        return $validator;
    }
}
