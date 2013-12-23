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
        $order = $container->getParameter('lunetics_timezone.guesser.order');
        $extGeoip = extension_loaded('geoip');
        if (!$extGeoip && in_array('geo', $order)) {
            throw new \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException('Cannot load the "geo" guesser without the pecl-geoip extension.');
        }
        if (in_array('locale', $order)) {
            if (!$extGeoip) {
                throw new \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException('Cannot load the "locale" guesser without the pecl-geoip extension.');
            }
            $geoipInfo = geoip_db_get_all_info();
            if (!geoip_db_avail(GEOIP_CITY_EDITION_REV0) OR !geoip_db_avail(GEOIP_CITY_EDITION_REV1)) {
                $error = 'Could not find "'.$geoipInfo[GEOIP_CITY_EDITION_REV0]['description'].'" ('.$geoipInfo[GEOIP_CITY_EDITION_REV0]['filename'].') or ';
                $error .= '"'.$geoipInfo[GEOIP_CITY_EDITION_REV1]['description'].'" ('.$geoipInfo[GEOIP_CITY_EDITION_REV1]['filename'].')';
                throw new \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException($error);
            }
        }
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
