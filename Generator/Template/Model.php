<?php

namespace Avoo\Bundle\GeneratorBundle\Generator\Template;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use Avoo\Bundle\GeneratorBundle\Tool\EntityGenerator;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Model
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class Model extends Template
{
    /**
     * @var array $configuration
     */
    protected $configuration = array(
        'directory' => null,
        'namespace' => null,
        'format' => 'xml',
        'with_interface' => null,
        'fields' => array()
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
            $this->configuration['directory'] = $this->bundle->getPath() . '/../../Component/Core/Model';
        }

        //Model namespace
        if (!$this->configuration['namespace']) {
            $this->configuration['namespace'] = $this->registry->getAliasNamespace($this->bundle->getName());
        }

        //With interface
        if (is_null($this->configuration['with_interface'])) {
            $this->configuration['with_interface'] = true;
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

        $entityClass = $this->configuration['namespace'] . '\\' . $this->model;
        $modelPath = $this->configuration['directory'] . '/' . $this->model . '.php';

        if (file_exists($modelPath)) {
            $this->addError(sprintf('Model "%s" already exist.', $modelPath));

            return false;
        }

        $class = new ClassMetadataInfo($entityClass);
        $class->isMappedSuperclass = true;
        $class->setPrimaryTable(array(
            'name' => strtolower($this->model)
        ));
        $class->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
        $class->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);

        foreach ($this->configuration['fields'] as $field) {
            $class->mapField($field);
        }

        $entityGenerator = $this->getEntityGenerator();

        if ($this->configuration['with_interface']) {
            $fields = $this->getFieldsFromMetadata($class);

            $this->renderFile('model/ModelInterface.php.twig',
                $this->configuration['directory'] . '/' . $this->model . 'Interface.php',
                array(
                    'fields' => $fields,
                    'namespace' => $this->configuration['namespace'],
                    'class_name' => $this->model . 'Interface'
                )
            );
        }

        $cme = new ClassMetadataExporter();
        $exporter = $cme->getExporter('yml' == $this->configuration['format'] ? 'yaml' : $this->configuration['format']);
        $mappingPath = $this->bundle->getPath() .
            '/Resources/config/doctrine/model/' . $this->model . '.orm.' . $this->configuration['format'];

        if (file_exists($mappingPath)) {
            $this->addError(sprintf('Cannot generate model when mapping "%s" already exists.', $mappingPath));

            return false;
        }

        $mappingCode = $exporter->exportClassMetadata($class);
        $entityGenerator->setGenerateAnnotations(false);
        $entityCode = $entityGenerator->generateEntityClass($class);

        file_put_contents($modelPath, $entityCode);

        if ($mappingPath) {
            $this->fileSystem->mkdir(dirname($mappingPath));
            file_put_contents($mappingPath, $mappingCode);
        }

        $this->renderFile('model/Repository.php.twig',
            $this->bundle->getPath() . '/Doctrine/ORM/' . $this->model . 'Repository.php',
            array(
                'namespace' => $this->bundle->getNamespace() . '\\Doctrine\\ORM',
                'class_name' => $this->model . 'Repository'
            )
        );

        $this->patchDependencyInjection();
    }

    /**
     * get entity generator
     *
     * @return EntityGenerator
     */
    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();
        $entityGenerator->setFieldVisibility(EntityGenerator::FIELD_VISIBLE_PROTECTED);
        $entityGenerator->setGenerateAnnotations(false);
        $entityGenerator->setGenerateStubMethods(true);
        $entityGenerator->setRegenerateEntityIfExists(false);
        $entityGenerator->setUpdateEntityIfExists(true);
        $entityGenerator->setNumSpaces(4);
        $entityGenerator->setAnnotationPrefix('ORM\\');

        if ($this->configuration['with_interface']) {
            $entityGenerator->setClassToInterface($this->configuration['namespace'] . '\\' . $this->model.'Interface');
        }

        return $entityGenerator;
    }

    /**
     * Build dependency configuration
     */
    private function patchDependencyInjection()
    {
        $bundleDir = $this->bundle->getPath();
        $configurationFile = $bundleDir . '/DependencyInjection/Configuration.php';
        $resourceName = strtolower($this->model);
        $modelName = $this->configuration['namespace'] . '\\' . $this->model;
        $repositoryName = $this->bundle->getNamespace() . '\\Doctrine\\ORM\\' . $this->model . 'Repository';

        $ref = "->arrayNode('$resourceName')";

        if ($this->refExist($configurationFile, $ref)) {
            $this->addError(sprintf('The resource "%s" already exist.', $resourceName));

            return false;
        }

        $ref = "->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()";

        $nodeDeclaration = <<<EOF
                        ->arrayNode('$resourceName')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')
                                    ->defaultValue('$modelName')
                                ->end()
                                ->scalarNode('controller')->end()
                                ->scalarNode('form')->end()
                                ->scalarNode('repository')
                                    ->defaultValue('$repositoryName')
                                ->end()
                            ->end()
                        ->end()
EOF;

        $this->dumpFile($configurationFile, "\n" . $nodeDeclaration, $ref);
    }
}
