<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Yaml\Parser;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class LuneticsTimezoneExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $this->bindParameters($container, $this->getAlias(), $config);
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
        $this->loadLocaleMapper($container);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias()
    {
        return 'lunetics_timezone';
    }

    public function loadLocaleMapper(ContainerBuilder $container)
    {
        $localeMapper = new Parser();
        $file = new FileLocator(__DIR__ . '/../Resources/config');
        $container->setParameter('lunetics_timezone.service.locale_mapper.data', $localeMapper->parse(file_get_contents($file->locate('LocaleMapper.yml'))));
    }
    /**
     * Binds the config Parameters to the container
     *
     * @param ContainerBuilder $container Containter
     * @param string           $name      Name
     * @param array            $config    Configuration
     *
     * @author Christophe Willemsen <willemsen.christophe@gmail.com>
     */
    public function bindParameters(ContainerBuilder $container, $name, $config)
    {
        if (is_array($config) && empty($config[0])) {
            foreach ($config as $key => $value) {
                $this->bindParameters($container, $name . '.' . $key, $value);
            }
        } else {
            $container->setParameter($name, $config);
        }
    }
}
