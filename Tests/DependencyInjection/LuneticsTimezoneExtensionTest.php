<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Lunetics\TimezoneBundle\DependencyInjection\LuneticsTimezoneExtension;
use Symfony\Component\Yaml\Parser;

class LuneticsTimezoneExtensionTest extends \PHPUnit_Framework_TestCase
{
    protected $configuration;

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testBundleLoadThrowsExceptionUnlessDetectorsOrderIsSet()
    {
        $loader = new LuneticsTimezoneExtension();
        $config = $this->getEmptyConfig();
        unset($config['guesser']['order']);
        $loader->load(array($config), new ContainerBuilder());
    }

    /**
     * @return ContainerBuilder
     */
    protected function createEmptyConfiguration()
    {
        $this->configuration = new ContainerBuilder();
        $loader = new LuneticsTimezoneExtension;
        $config = $this->getEmptyConfig();
        $loader->load(array($config), $this->configuration);
        $this->assertTrue($this->configuration instanceof ContainerBuilder);
    }

    /**
     * @return ContainerBuilder
     */
    protected function createFullConfiguration()
    {
        $this->configuration = new ContainerBuilder();
        $loader = new LuneticsTimezoneExtension;
        $config = $this->getFullConfig();
        $loader->load(array($config), $this->configuration);
        $this->assertTrue($this->configuration instanceof ContainerBuilder);
    }

    /**
     * getEmptyConfig
     *
     * @return array
     */
    protected function getEmptyConfig()
    {
        $yaml = <<<EOF
guesser:
    order:
        - geo
        - locale
EOF;
        $parser = new Parser();

        return $parser->parse($yaml);
    }

    protected function getFullConfig()
    {
        $yaml = <<<EOF
guesser:
    order:
        - geo
        - locale
EOF;
        $parser = new Parser();

        return  $parser->parse($yaml);
    }

    private function assertAlias($value, $key)
    {
        $this->assertEquals($value, (string) $this->configuration->getAlias($key), sprintf('%s alias is correct', $key));
    }

    private function assertParameter($value, $key)
    {
        $this->assertEquals($value, $this->configuration->getParameter($key), sprintf('%s parameter is correct', $key));
    }

    private function assertHasDefinition($id)
    {
        $this->assertTrue(($this->configuration->hasDefinition($id) ?: $this->configuration->hasAlias($id)));
    }

    private function assertNotHasDefinition($id)
    {
        $this->assertFalse(($this->configuration->hasDefinition($id) ?: $this->configuration->hasAlias($id)));
    }

    protected function tearDown()
    {
        unset($this->configuration);
    }
}
