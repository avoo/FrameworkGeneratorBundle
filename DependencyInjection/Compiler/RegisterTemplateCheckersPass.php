<?php

namespace Avoo\Bundle\GeneratorBundle\DependencyInjection\Compiler;

/**
 * Class RegisterCompilerCheckers
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class RegisterTemplateCheckersPass
 */
class RegisterTemplateCheckersPass implements CompilerPassInterface
{
    /**
     * Process
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $checkers = array();
        foreach ($container->findTaggedServiceIds('avoo.resource_generator.template') as $id => $attributes) {
            $checkers[$attributes[0]['type']] = $id;
        }

        $container->setParameter('avoo.resource_generator.templates', $checkers);
    }
}

