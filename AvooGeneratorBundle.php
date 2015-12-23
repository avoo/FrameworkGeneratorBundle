<?php

namespace Avoo\Bundle\GeneratorBundle;

use Avoo\Bundle\GeneratorBundle\DependencyInjection\Compiler\RegisterTemplateCheckersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AvooGeneratorBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterTemplateCheckersPass());
    }
}
