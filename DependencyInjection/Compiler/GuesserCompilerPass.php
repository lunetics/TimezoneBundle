<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compilerpass Class
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class GuesserCompilerPass implements CompilerPassInterface
{
    /**
     * Compilerpass for Timezone Guessers
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('lunetics_timezone.guesser_manager')) {
            return;
        }

        $definition = $container->getDefinition('lunetics_timezone.guesser_manager');

        foreach ($container->findTaggedServiceIds('lunetics_timezone.guesser') as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $definition->addMethodCall('addGuesser', array(new Reference($id), $attributes["alias"]));
            }
        }
    }
}

