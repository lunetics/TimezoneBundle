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

use Symfony\Component\Yaml\Parser;
use Lunetics\TimezoneBundle\TimezoneGuesser\LocalemapperTimezoneGuesser;

/**
 * LocaleTimezoneGuesser Tests
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class LocalemapperTimezoneGuesserTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider getRequestWithValidLocale
     *
     * @param string $locale
     */
    public function testValidLocale($locale)
    {
        $guesser = $this->getLocaleMapperGuesser();
        $request = $this->getRequestMock($locale);
        $this->assertInternalType('string', $guesser->guessTimezone($request));
        $this->assertInternalType('string', $guesser->getIdentifiedTimezone());
    }

    /**
     * @dataProvider getRequestWithInvalidLocale
     *
     * @param string $locale
     */
    public function testInvalidLocale($locale)
    {
        $guesser = $this->getLocaleMapperGuesser();
        $request = $this->getRequestMock($locale);
        $this->assertFalse($guesser->guessTimezone($request));
    }

    /**
     * @return LocalemapperTimezoneGuesser
     */
    public function getLocaleMapperGuesser()
    {
        $localeMapper = new Parser();

        return new LocalemapperTimezoneGuesser($localeMapper->parse(file_get_contents(__DIR__ . '/../../Resources/config/LocaleMapper.yml')));
    }

    /**
     * Returns an Array with Locales in the LocaleMapper.yml
     *
     * @return array
     */
    public function getRequestWithValidLocale()
    {
        return array(
            array('de_DE'),
            array('en_US'),
            array('it_IT'),
            array('ru_RU'),
            array('de'),
            array('en'),
            array('zh')
        );
    }

    /**
     * Returns an Array with Locales NOT in the LocaleMapper.yml
     *
     * @return array
     */
    public function getRequestWithInvalidLocale()
    {
        return array(
            array('ar_AR'),
            array('at')
        );
    }

    /**
     * Mocks an Request
     *
     * @param string $locale
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function getRequestMock($locale)
    {

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request', array('getLocale'));
        $request->expects($this->any())
                ->method('getLocale')
                ->will($this->returnValue($locale));

        return $request;
    }
}