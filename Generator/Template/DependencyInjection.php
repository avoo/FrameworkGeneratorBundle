<?php

namespace Avoo\Bundle\GeneratorBundle\Generator\Template;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Model
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class DependencyInjection extends Template
{
    /**
     * @var array $configuration
     */
    protected $configuration = array(
        'model' => null,
        'controller' => null,
        'form' => null,
    );

    /**
     * {@inheritdoc}
     */
    public function buildDefaultConfiguration($resourceName = null, OutputInterface $output = null)
    {
        $this->setBundle($resourceName);
        $this->output = $output;

        //Model
        if (!$this->configuration['model']) {
            $this->configuration['model'] = $this->registry->getAliasNamespace($this->bundle->getName()) .
                '\\' . $this->model;
        }

        //Controller
        if (!$this->configuration['controller']) {
            $this->configuration['controller'] = 'Sylius\Bundle\ResourceBundle\Controller\ResourceController';
        }

        //Form
        if (!$this->configuration['form']) {
            $this->configuration['form'] = $this->bundle->getNamespace() . '\\Form\\Type\\' . $this->model . 'Type';
        }

        $this->_initialized = true;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($resourceName, OutputInterface $output = null)
    {
        if (!$this->_initialized) {
            $this->buildDefaultConfiguration($resourceName, $output);
        }

        $bundleDir = $this->bundle->getPath();
        $configurationFile = $bundleDir . '/DependencyInjection/Configuration.php';

        $this->patchResource($configurationFile);
        $this->patchModel($configurationFile);
        $this->patchController($configurationFile);
        $this->patchForm($configurationFile);
    }

    /**
     * Patch resource node
     *
     * @param string $path
     */
    protected function patchResource($path)
    {
        $resourceName = strtolower($this->model);
        $ref = "->arrayNode('$resourceName')";

        if ($this->refExist($path, $ref)) {
            return;
        }

        $ref = "->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()";

        $nodeDeclaration = <<<EOF
                        ->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                ->end()
                                ->scalarNode('controller')
                                ->end()
                                ->scalarNode('form')
                                ->end()
                            ->end()
                        ->end()
EOF;

        $this->dumpFile($path, "\n" . $nodeDeclaration, $ref);
    }

    /**
     * Patch model declaration
     *
     * @param string $path
     */
    protected function patchModel($path)
    {
        $resourceName = strtolower($this->model);
        $modelName = $this->configuration['model'];

        $ref = <<<EOF
                        ->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
EOF;

        if ($this->refExist($path, $ref)) {
            return;
        }

        $ref = "->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')";

        $nodeDeclaration = <<<EOF
                                    ->defaultValue('$modelName')
EOF;

        $this->dumpFile($path, "\n" . $nodeDeclaration, $ref);
    }

    /**
     * Patch controller declaration
     *
     * @param string $path
     */
    protected function patchController($path)
    {
        $resourceName = strtolower($this->model);
        $modelName = $this->configuration['model'];
        $controllerName = $this->configuration['controller'];

        $ref = <<<EOF
                        ->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
                                ->end()
                                ->scalarNode('controller')
                                    ->defaultValue('$controllerName')
EOF;

        if ($this->refExist($path, $ref)) {
            return;
        }

        $ref = "->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
                                ->end()
                                ->scalarNode('controller')";

        $nodeDeclaration = <<<EOF
                                    ->defaultValue('$controllerName')
EOF;

        $this->dumpFile($path, "\n" . $nodeDeclaration, $ref);
    }

    /**
     * Patch form declaration
     *
     * @param string $path
     */
    protected function patchForm($path)
    {
        $resourceName = strtolower($this->model);
        $modelName = $this->configuration['model'];
        $controllerName = $this->configuration['controller'];
        $formName = $this->configuration['form'];

        $ref = <<<EOF
                        ->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
                                ->end()
                                ->scalarNode('controller')
                                    ->defaultValue('$controllerName')
                                ->end()
                                ->scalarNode('form')
                                    ->defaultValue('$formName')
EOF;

        if ($this->refExist($path, $ref)) {
            return;
        }

        $ref = "->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
                                ->end()
                                ->scalarNode('controller')
                                    ->defaultValue('$controllerName')
                                ->end()
                                ->scalarNode('form')";

        $nodeDeclaration = <<<EOF
                                    ->defaultValue('$formName')
EOF;

        $this->dumpFile($path, "\n" . $nodeDeclaration, $ref);
    }
}
