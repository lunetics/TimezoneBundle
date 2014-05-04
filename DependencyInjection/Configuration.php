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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $availableTimezones = \DateTimeZone::listIdentifiers();
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('lunetics_timezone');

        $rootNode
            ->children()
                ->scalarNode('session_var')
                    ->defaultValue('lunetics_timezone')
                ->end()
                ->scalarNode('default_timezone')
                    ->defaultValue('UTC')
                    ->validate()
                    ->ifNotInArray($availableTimezones)
                        ->thenInvalid('Invalid timezone "%s"')
                    ->end()
                ->end()
                ->arrayNode('guesser')
                    ->children()
                        ->arrayNode('manager')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('class')
                                    ->defaultValue('Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserManager')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('listener')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('class')
                                    ->defaultValue('Lunetics\TimezoneBundle\EventListener\TimezoneListener')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('order')
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                        ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        $this->addServiceSection($rootNode);

        return $treeBuilder;
    }

    private function addServiceSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('service')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('geo')
                        ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('class')->defaultValue('Lunetics\TimezoneBundle\TimezoneGuesser\GeoTimezoneGuesser')->end()
                            ->end()
                        ->end()
                        ->arrayNode('locale_mapper')
                        ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('class')->defaultValue('Lunetics\TimezoneBundle\TimezoneGuesser\LocalemapperTimezoneGuesser')->end()
                            ->end()
                        ->end()
                        ->arrayNode('locale')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('class')->defaultValue('Lunetics\TimezoneBundle\TimezoneGuesser\LocaleTimezoneGuesser')->end()
                            ->end()
                       ->end()
                    ->end()
                ->end()
            ->end();
    }
}
