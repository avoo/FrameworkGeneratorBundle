<?php

namespace Avoo\Bundle\GeneratorBundle\Generator\Template;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Template
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class Controller extends Template
{
    /**
     * @var array $configuration
     */
    protected $configuration = array(
        'directory' => null,
        'namespace' => null
    );

    /**
     * {@inheritdoc}
     */
    public function buildDefaultConfiguration($resourceName = null, OutputInterface $output = null)
    {
        $this->setBundle($resourceName);
        $this->output = $output;

        //Model directory
        if (!$this->configuration['directory']) {
            $this->configuration['directory'] = $this->bundle->getPath() . '/Controller';
        }

        //Model namespace
        if (!$this->configuration['namespace']) {
            $this->configuration['namespace'] = $this->bundle->getNamespace() . '\\Controller';
        }

        $this->_initialized = true;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($resourceName, OutputInterface $output = null)
    {
        if (!$this->_initialized) {
            $this->buildDefaultConfiguration($resourceName);
        }

        $this->renderFile('controller/Controller.php.twig',
            $this->configuration['directory'] . '/' . $this->model . 'Controller.php',
            array(
                'namespace' => $this->configuration['namespace'],
                'controller' => $this->model
            )
        );

        $this->patchDependencyInjection();
    }

    /**
     * Build dependency configuration
     */
    private function patchDependencyInjection()
    {
        $bundleDir = $this->bundle->getPath();
        $configurationFile = $bundleDir . '/DependencyInjection/Configuration.php';
        $resourceName = strtolower($this->model);
        $modelName = $this->registry->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;
        $controllerName = $this->configuration['namespace'] . '\\' . $this->model . 'Controller';

        $ref = <<<EOF
                        ->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
                                ->end()
                                ->scalarNode('controller')
EOF;

        if (!$this->refExist($configurationFile, $ref)) {
            $this->addError(sprintf('Cannot patch dependency injection "%s"', $configurationFile));

            return false;
        } elseif ($this->refExist($configurationFile, $ref . "\n                                    ->defaultValue('")) {
            $this->addError(sprintf(
                    'A default value is already define for "%s" controller in dependency injection "%s"',
                    $this->model,
                    $configurationFile
            ));

            return false;
        }

        $nodeDeclaration = <<<EOF
                                    ->defaultValue('$controllerName')
EOF;

        $this->dumpFile($configurationFile, "\n" . $nodeDeclaration . "\n                                ", $ref);
    }
}
