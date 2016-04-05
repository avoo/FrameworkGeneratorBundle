<?php

namespace Avoo\Bundle\GeneratorBundle\Generator\Template;

use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Crud
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class Crud extends Template
{
    /**
     * @var array $configuration
     */
    protected $configuration = array(
        'backend_bundle'  => null,
        'backend_crud'    => true,
        'actions'         => array('index', 'show', 'create', 'update', 'delete'),
    );

    /**
     * {@inheritdoc}
     */
    public function buildDefaultConfiguration($resourceName = null, OutputInterface $output = null)
    {
        $this->setBundle($resourceName);
        $this->output = $output;

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

        if ($this->configuration['backend_crud']) {
            $result = $this->patchBackend($this->configuration['backend_bundle']);

            if ($result) {
                $this->addError($result);
            }

            $this->patchRules();
        }
    }

    /**
     * Add routing for backend
     *
     * @param string $bundle
     *
     * @return array
     */
    private function patchBackend($bundle)
    {
        $b = $this->validateBundle($bundle);
        $this->patchRouting($b);
        $this->generateCrud($b);
    }

    /**
     * Patch rules
     *
     * @return bool
     */
    private function patchRules()
    {
        $bundleDir = $this->bundle->getPath();
        $bundlePrefix = $this->getBundlePrefix();
        $fileName = $bundlePrefix . '.yml';
        $configurationFile = $bundleDir . '/Resources/config/app/' . $fileName;
        $resource = strtolower($this->model);

        $ref = <<<EOF
sylius_rbac:
    permissions:\n
EOF;

        if (!$this->refExist($configurationFile, $ref)) {
            $this->addError(sprintf('Cannot patch configuration file injection "%s"', $fileName));

            return false;
        }

        $rules = '        ' . $bundlePrefix . '.manage.' . $resource . ': Manage ' . $resource . "\n";
        foreach ($this->configuration['actions'] as $action) {
            $rules .= sprintf('        %s.%s.%s: %s %s', $bundlePrefix, $resource, $action, $action, $resource) . "\n";
        }

        $nodeDeclaration = <<<EOF
$rules
EOF;

        $this->dumpFile($configurationFile, $nodeDeclaration . "\n", $ref);

        $ref = <<<EOF
    permissions_hierarchy:\n
EOF;

        $rules = array();
        foreach ($this->configuration['actions'] as $action) {
            $rules[] = sprintf('%s.%s.%s', $bundlePrefix, $resource, $action);
        }

        $rules = implode(', ', $rules);
        $nodeDeclaration = <<<EOF
        $bundlePrefix.manage.$resource: [$rules]
EOF;

        $this->dumpFile($configurationFile, $nodeDeclaration . "\n", $ref);

        $ref = <<<EOF
    roles:\n
EOF;

        $nodeDeclaration = <<<EOF
        $resource:
            name: $this->model
            description: $this->model management
            permissions: [$bundlePrefix.manage.$resource]
            security_roles: [ROLE_ADMINISTRATION_ACCESS]
EOF;

        $this->dumpFile($configurationFile, $nodeDeclaration . "\n", $ref);

        $ref = <<<EOF
    roles_hierarchy:
        administrator: [
EOF;

        $nodeDeclaration = <<<EOF
$resource,
EOF;

        $this->dumpFile($configurationFile, $nodeDeclaration, $ref);
    }

    /**
     * Validate bundle
     *
     * @param $bundle
     *
     * @return AbstractResourceBundle
     */
    private function validateBundle($bundle)
    {
        Validators::validateBundleName($bundle);

        try {
            $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);
        } catch (\Exception $e) {
            throw new \RuntimeException('Cannot patch "%s", bundle not found.', $bundle);
        }

        if (!$bundle instanceof AbstractResourceBundle) {
            throw new \RuntimeException('You need to implement AbstractResourceBundle for "%s"', $bundle->getName());
        }

        return $bundle;
    }

    /**
     * Generate CRUD views
     *
     * @param AbstractResourceBundle $bundle
     */
    protected function generateCrud(AbstractResourceBundle $bundle)
    {
        $summary = array();
        $this->generateMacrosView($bundle);

        if (in_array('index', $this->configuration['actions'])) {
            $this->generateIndexView($bundle, $summary);
        }

        if (in_array('show', $this->configuration['actions'])) {
            $this->generateShowView($bundle, $summary);
        }

        if (in_array('create', $this->configuration['actions'])) {
            $this->generateCreateView($bundle, $summary);
        }

        if (in_array('update', $this->configuration['actions'])) {
            $this->generateUpdateView($bundle, $summary);
        }

        $this->writeOutput($summary);
    }

    /**
     * Generate index view
     *
     * @param AbstractResourceBundle $bundle
     * @param array                  $summary
     *
     * @return array
     */
    protected function generateIndexView(AbstractResourceBundle $bundle, &$summary)
    {
        $path = $bundle->getPath() . '/Resources/views/' . $this->model . '/index.html.twig';

        if (file_exists($path)) {
            $summary[] = '';
            $summary[] = '<bg=red>Index view already exist.</>';

            return;
        }

        $this->renderFile('crud/index.html.twig.twig',
            $path,
            array(
                'bundle'  => $bundle->getName(),
                'model'   => $this->model,
                'prefix'  => $this->getBundlePrefix($bundle),
                'vars'    => strtolower(Inflector::pluralize($this->model)),
                'actions' => array(
                    'add' => strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_create')
                )
            )
        );

        $summary[] = '';
        $summary[] = '<bg=green>Index view created.</>';
    }

    /**
     * Generate show view
     *
     * @param AbstractResourceBundle $bundle
     */
    protected function generateShowView(AbstractResourceBundle $bundle, &$summary)
    {
        $path = $bundle->getPath() . '/Resources/views/' . $this->model . '/show.html.twig';
        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;
        $identifier = $this->getContainer()->get('doctrine')
            ->getManager()
            ->getClassMetadata($entityClass)
            ->getIdentifier();

        if (file_exists($path)) {
            $summary[] = '';
            $summary[] = '<bg=red>Show view already exist.</>';

            return;
        }

        $this->renderFile('crud/show.html.twig.twig',
            $path,
            array(
                'bundle'     => $bundle->getName(),
                'model'      => $this->model,
                'identifier' => $identifier[0],
                'prefix'     => $this->getBundlePrefix($bundle),
                'cancel'     => strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_index'),
                'actions'    => array(
                    'edit' => strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_update')
                )
            )
        );

        $summary[] = '';
        $summary[] = '<bg=green>Show view created.</>';
    }

    /**
     * Generate create view
     *
     * @param AbstractResourceBundle $bundle
     */
    protected function generateCreateView(AbstractResourceBundle $bundle, &$summary)
    {
        $this->generateFormView($bundle);
        $path = $bundle->getPath() . '/Resources/views/' . $this->model . '/create.html.twig';

        if (file_exists($path)) {
            $summary[] = '';
            $summary[] = '<bg=red>Create view already exist.</>';

            return;
        }

        $this->renderFile('crud/create.html.twig.twig',
            $path,
            array(
                'bundle'  => $bundle->getName(),
                'model'   => $this->model,
                'prefix'  => $this->getBundlePrefix($bundle),
                'action'  => strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_create'),
                'cancel'  => strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_index'),
            )
        );

        $summary[] = '';
        $summary[] = '<bg=green>Create view created.</>';
    }

    /**
     * Generate update view
     *
     * @param AbstractResourceBundle $bundle
     */
    protected function generateUpdateView(AbstractResourceBundle $bundle, &$summary)
    {
        $this->generateFormView($bundle);
        $path = $bundle->getPath() . '/Resources/views/' . $this->model . '/update.html.twig';
        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;
        $identifier = $this->getContainer()->get('doctrine')
            ->getManager()
            ->getClassMetadata($entityClass)
            ->getIdentifier();

        if (file_exists($path)) {
            $summary[] = '';
            $summary[] = '<bg=red>Update view already exist.</>';

            return;
        }

        $this->renderFile('crud/update.html.twig.twig',
            $path,
            array(
                'bundle'     => $bundle->getName(),
                'model'      => $this->model,
                'identifier' => $identifier[0],
                'prefix'     => $this->getBundlePrefix($bundle),
                'action'     => strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_update'),
                'cancel'     => strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_index'),
            )
        );

        $summary[] = '';
        $summary[] = '<bg=green>Update view created.</>';
    }

    /**
     * Generate macros view
     *
     * @param AbstractResourceBundle $bundle
     */
    protected function generateMacrosView(AbstractResourceBundle $bundle)
    {
        $path = $bundle->getPath() . '/Resources/views/' . $this->model . '/macros.html.twig';

        if (!file_exists($path)) {
            $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;
            $metadata = $this->getEntityMetadata($entityClass);
            $identifier = $this->getContainer()->get('doctrine')
                ->getManager()
                ->getClassMetadata($entityClass)
                ->getIdentifier();

            $actions = array();
            array_map(function($key) use ($bundle, &$actions) {
                if (in_array($key, array('show', 'update'))) {
                    $actions[$key] = strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_' . $key);
                }
            }, $this->configuration['actions']);

            $actions['delete'] = strtolower($this->getBundlePrefix($bundle) . '_' . $this->model . '_delete');

            $this->renderFile('crud/macros.html.twig.twig',
                $bundle->getPath() . '/Resources/views/' . $this->model . '/macros.html.twig',
                array(
                    'bundle'  => $bundle->getName(),
                    'model'   => $this->model,
                    'prefix'  => $this->getBundlePrefix($bundle),
                    'vars'    => strtolower(Inflector::pluralize($this->model)),
                    'identifier' => $identifier[0],
                    'fields'  => $this->getFieldsFromMetadata($metadata[0]),
                    'actions' => $actions
                )
            );
        }
    }

    /**
     * Generate form view
     *
     * @param AbstractResourceBundle $bundle
     */
    protected function generateFormView(AbstractResourceBundle $bundle)
    {
        $path = $bundle->getPath() . '/Resources/views/' . $this->model . '/_form.html.twig';

        if (!file_exists($path)) {
            $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;
            $metadata = $this->getEntityMetadata($entityClass);

            $this->renderFile('crud/_form.html.twig.twig',
                $bundle->getPath() . '/Resources/views/' . $this->model . '/_form.html.twig',
                array(
                    'fields'  => $this->getFieldsFromMetadata($metadata[0]),
                )
            );
        }
    }

    /**
     * Path routing
     *
     * @param AbstractResourceBundle $bundle
     *
     * @return null
     */
    private function patchRouting(AbstractResourceBundle $bundle)
    {
        $routingPath = $bundle->getPath() . '/Resources/config/routing/';
        $prefix = $this->getBundlePrefix();
        $namePrefix = strtolower($this->getBundlePrefix($bundle) . '_' . $this->model);
        $bundleName = $bundle->getName();
        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($this->bundle->getName()) . '\\' . $this->model;

        $identifier = $this->getContainer()->get('doctrine')
            ->getManager()
            ->getClassMetadata($entityClass)
            ->getIdentifier();

        $this->renderFile('controller/routing/routing.yml.twig',
            $routingPath . strtolower($this->model) . '.yml',
            array(
                'route_name_prefix' => $namePrefix,
                'bundle' => $prefix,
                'entity' => $this->model,
                'identifier' => $identifier[0],
                'resource' => $bundle->getName(),
                'actions' => $this->configuration['actions']
            )
        );

        $path = $routingPath . 'main.yml';
        if (!file_exists($path)) {
            $path = $this->getContainer()->get('kernel')->getRootDir() . '/config/routing.yml';
        }

        $prefixName = strtolower($this->model);
        $nodeDeclaration = <<<EOF
$namePrefix:
    resource: @$bundleName/Resources/config/routing/$prefixName.yml
    prefix: /$prefixName
EOF;

        if (!$this->refExist($path, $namePrefix)) {
            $this->dumpFile($path, "\n" . $nodeDeclaration, false, false);
        }

        return null;
    }
}
