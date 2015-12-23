<?php

namespace Avoo\Bundle\GeneratorBundle\Generator\Template;

use Doctrine\ORM\Configuration;
use Symfony\Component\ClassLoader\ClassLoader;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Form
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class Form extends Template
{
    /**
     * @var array $configuration
     */
    protected $configuration = array(
        'directory' => null,
        'namespace' => null,
        'path'      => null
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
            $this->configuration['directory'] = $this->bundle->getPath() . '/Form/Type';
        }

        //Model namespace
        if (!$this->configuration['namespace']) {
            $this->configuration['namespace'] = $this->bundle->getNamespace() . '\\Form\\Type';
        }

        if (!$this->configuration['path']) {
            $loader = new ClassLoader();
            $loader->setUseIncludePath(true);
            $namespace = $this->registry->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;
            $test = new $namespace();
            $reflector = new \ReflectionClass(get_class($test));
            $entity = $loader->findFile(dirname($reflector->getFileName()) . '\\' . $this->model);
            $this->configuration['path'] = $entity;
        }

        $this->setSkeletonDirs($this->getSkeletonDirs($this->bundle));

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

        $registry = $this->getContainer()->get('doctrine');
        /** @var Configuration $config */
        $config = $registry->getManager(null)->getConfiguration();

        $config->setEntityNamespaces(array_merge(
            array($this->bundle->getName() => $this->getContainer()->get('doctrine')->getAliasNamespace($this->bundle->getName())),
            $config->getEntityNamespaces()
        ));

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;
        $metadata = $this->getEntityMetadata($entityClass, $this->configuration['path']);

        $parts       = explode('\\', $this->model);
        $entityClass = array_pop($parts);

        $className = $entityClass.'Type';
        $dirPath = $this->bundle->getPath().'/Form/Type';
        $classPath = $dirPath.'/'.str_replace('\\', '/', $this->model).'Type.php';

        if (file_exists($classPath)) {
            $this->addError(sprintf('Unable to generate the %s form class as it already exists under the %s file', $className, $classPath));

            return false;
        }

        if (count($metadata[0]->identifier) > 1) {
            $this->addError('The form generator does not support entity classes with multiple primary keys.');

            return false;
        }

        $parts = explode('\\', $this->model);
        array_pop($parts);

        $matches = preg_split('/(?=[A-Z])/',$this->bundle->getName(), -1, PREG_SPLIT_NO_EMPTY);
        array_pop($matches);
        $formTypeName = strtolower(implode('_', $matches) . '_' . $this->model);

        $this->renderFile('form/FormType.php.twig', $classPath, array(
            'fields'           => $this->getFieldsFromMetadata($metadata[0]),
            'namespace'        => $this->configuration['namespace'],
            'form_class'       => $className,
            'form_type_name'   => $formTypeName
        ));

        $this->addFormDeclaration($formTypeName);
        $this->patchDependencyInjection();
    }

    /**
     * Add service form declaration
     *
     * @param string $formTypeName
     */
    private function addFormDeclaration($formTypeName)
    {
        $name = strtolower($this->model);
        $extension = $matches = preg_split('/(?=[A-Z])/', $this->bundle->getName(), -1, PREG_SPLIT_NO_EMPTY);
        $bundleName = $this->getBundlePrefix();
        array_pop($extension);
        $path = $this->bundle->getPath() . '/Resources/config/forms.xml';

        if (!file_exists($path)) {
            $this->renderFile('form/ServicesForms.xml.twig', $path, array());
            $ref = 'protected $configFiles = array(';

            $this->dumpFile(
                $this->bundle->getPath() . '/DependencyInjection/' . implode('', $extension) . 'Extension.php',
                "\n        'forms',",
                $ref
            );die;
        }

        $this->addService($path, $bundleName, $name, $formTypeName);
        $this->addParameter($path, $bundleName, $name);
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
        $formName = $this->configuration['namespace'] . '\\' . $this->model . 'Type';

        $ref = <<<EOF
                        ->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
                                ->end()
                                ->scalarNode('controller')->end()
                                ->scalarNode('form')
EOF;

        if (!$this->refExist($configurationFile, $ref)) {
            $this->addError(sprintf('Cannot patch dependency injection "%s"', $configurationFile));

            return false;
        } elseif ($this->refExist($configurationFile, $ref . "\n                                    ->defaultValue('")) {
            $this->addError(sprintf(
                    'A default value is already define for "%s" form in dependency injection "%s"',
                    $this->model,
                    $configurationFile
                )
            );

            return false;
        }

        $nodeDeclaration = <<<EOF
                                    ->defaultValue('$formName')
EOF;

        $this->dumpFile($configurationFile, "\n" . $nodeDeclaration . "\n                                ", $ref);
    }

    /**
     * Add service node
     *
     * @param string $path
     * @param string $bundleName
     * @param string $name
     * @param string $formTypeName
     */
    private function addService($path, $bundleName, $name, $formTypeName)
    {
        $ref = '<services>';
        $replaceBefore = true;
        $group = $bundleName . '_' . $name;

        $declaration = <<<EOF
        <service id="$bundleName.form.type.$name" class="%$bundleName.form.type.$name.class%">
            <argument>%$bundleName.model.$name.class%</argument>
            <argument type="collection">
                <argument>$group</argument>
            </argument>
            <tag name="form.type" alias="$formTypeName" />
        </service>
EOF;

        if ($this->refExist($path, $declaration)) {
            return;
        }

        if (!$this->refExist($path, $ref)) {
            $ref = '</container>';
            $replaceBefore = false;
            $declaration = <<<EOF
    <services>
$declaration
    </services>
EOF;
        }

        $this->dumpFile($path, "\n" . $declaration . "\n", $ref, $replaceBefore);
    }

    /**
     * Add parameter node
     *
     * @param string $path
     * @param string $bundleName
     * @param string $name
     */
    private function addParameter($path, $bundleName, $name)
    {
        $formPath = $this->configuration['namespace'] . '\\' . $this->model . 'Type';
        $ref = '<parameters>';
        $replaceBefore = true;

        $declaration = <<<EOF
        <parameter key="$bundleName.form.type.$name.class">$formPath</parameter>
EOF;

        if ($this->refExist($path, $declaration)) {
            return;
        }

        if (!$this->refExist($path, $ref)) {
            $ref = '    <services>';
            $replaceBefore = false;
            $declaration = <<<EOF
    <parameters>
$declaration
    </parameters>

EOF;
        }

        $this->dumpFile($path, "\n" . $declaration, $ref, $replaceBefore);
    }
}
