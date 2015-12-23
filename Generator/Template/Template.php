<?php

namespace Avoo\Bundle\GeneratorBundle\Generator\Template;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Avoo\Bundle\GeneratorBundle\Filesystem\Filesystem;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class Template
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
abstract class Template extends ContainerAware implements TemplateInterface
{
    /**
     * @var array $configuration
     */
    protected $configuration = array();

    /**
     * @var string $applicationName
     */
    protected $applicationName;

    /**
     * @var Filesystem $filesystem
     */
    protected $fileSystem;

    /**
     * @var RegistryInterface $registry
     */
    protected $registry;

    /**
     * @var BundleInterface
     */
    protected $bundle;

    /**
     * @var string $model
     */
    protected $model;

    /**
     * @var bool $_initialized
     */
    protected $_initialized = false;

    /**
     * @var string $skeletonDirs
     */
    protected $skeletonDirs;

    /**
     * @var OutputInterface|null $output
     */
    protected $output;

    /**
     * @var array $errors
     */
    protected $errors;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->fileSystem = new Filesystem();
    }

    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        $this->registry = $this->container->get('doctrine');
    }

    /**
     * {@inheritdoc}
     */
    public function setConfiguration($key, $value)
    {
        if (empty($this->configuration)) {
            throw new NotFoundHttpException('Configuration not found for this template.');
        }

        if (!in_array($key, array_keys($this->configuration))) {
            throw new NotFoundHttpException(
                sprintf('Key "%s" not found, try to use: %s', $key, implode(', ', array_keys($this->configuration)))
            );
        }

        $this->configuration[$key] = $value;

        return $this;
    }

    /**
     * Set bundle
     *
     * @param string $resourceName
     *
     * @throws ConflictHttpException
     * @return $this
     */
    public function setBundle($resourceName)
    {
        Validators::validateEntityName($resourceName);
        list($bundle, $model) = $this->parseShortcutNotation($resourceName);

        $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);

        if (!$bundle instanceof AbstractResourceBundle) {
            throw new ConflictHttpException(sprintf('You need to implement AbstractResourceBundle for "%s"', $bundle->getName()));
        }

        $this->setSkeletonDirs($this->getSkeletonDirs($bundle));
        $this->fileSystem->setTwigEnvironment($this->getTwigEnvironment());

        $this->bundle = $bundle;
        $this->model = $model;

        return $this;
    }

    /**
     * Return the prefix of the bundle.
     *
     * @param AbstractResourceBundle $bundle
     *
     * @return string
     */
    public function getBundlePrefix(AbstractResourceBundle $bundle = null)
    {
        if (is_null($bundle)) {
            $bundle = $this->bundle;
        }

        $containerExtension = new \ReflectionClass($bundle->getContainerExtension());
        $applicationName = $containerExtension->getProperty('applicationName');
        $applicationName->setAccessible(true);
        $extension = $bundle->getContainerExtension();

        return $applicationName->getValue(new $extension());
    }

    /**
     * Sets an array of directories to look for templates.
     *
     * The directories must be sorted from the most specific to the most
     * directory.
     *
     * @param array $skeletonDirs An array of skeleton dirs
     */
    public function setSkeletonDirs($skeletonDirs)
    {
        $this->skeletonDirs = is_array($skeletonDirs) ? $skeletonDirs : array($skeletonDirs);
    }

    /**
     * Get skeleton dirs by bundle name
     *
     * @param BundleInterface $bundle
     *
     * @return array
     */
    protected function getSkeletonDirs(BundleInterface $bundle = null)
    {
        $skeletonDirs = array();

        if (isset($bundle) && is_dir($dir = $bundle->getPath().'/Resources/' . $bundle->getName() . '/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        if (is_dir($dir = $this->getContainer()->get('kernel')->getRootdir().'/Resources/' . $bundle->getName() . '/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        $skeletonDirs[] = __DIR__.'/../../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../../Resources';

        return $skeletonDirs;
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function isReservedKeyword($keyword)
    {
        return $this->getContainer()->get('doctrine')->getConnection()
            ->getDatabasePlatform()
            ->getReservedKeywordsList()
            ->isKeyword($keyword);
    }

    /**
     * Get container
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get entity metadata
     *
     * @param string $entity
     * @param string $path
     *
     * @return array
     * @throws MappingException
     */
    protected function getEntityMetadata($entity, $path = null)
    {
        $factory = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));

        return $factory->getClassMetadata($entity, $path)->getMetadata();
    }

    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param  ClassMetadataInfo $metadata
     * @return array             $fields
     */
    protected function getFieldsFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }

    /**
     * Dump file
     *
     * @param string  $path
     * @param string  $declaration
     * @param string  $ref
     * @param boolean $replaceBefore
     */
    protected function dumpFile($path, $declaration, $ref = null, $replaceBefore = true)
    {
        $fileSystem = new Filesystem();

        if (is_null($ref)) {
            $fileSystem->dumpFile($path, $declaration);

            return;
        }

        $content = file_get_contents($path);

        if (false === strpos($content, $path)) {
            $replace = $replaceBefore ? $ref . $declaration : $declaration . $ref;

            if (false === $ref) {
                $updatedContent = $replaceBefore ? $declaration . $content : $content . $declaration;
            } else {
                $updatedContent = str_replace($ref, $replace, $content);
            }

            if ($content === $updatedContent) {
                throw new \RuntimeException('Unable to patch %s.', $path);
            }

            $fileSystem->dumpFile($path, $updatedContent);
        }
    }

    /**
     * Check if the reference exist in path
     *
     * @param string $path
     * @param string $ref
     *
     * @return bool
     */
    protected function refExist($path, $ref)
    {
        $content = file_get_contents($path);

        if (false === strpos($content, $ref)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function validateResourceName($resourceName)
    {
        Validators::validateEntityName($resourceName);
    }

    /**
     * Get routing prefix
     *
     * @param string $entity
     *
     * @return string
     */
    public function getRoutePrefix($entity)
    {
        $prefix = strtolower(str_replace(array('\\', '/'), '_', $entity));

        if ($prefix && '/' === $prefix[0]) {
            $prefix = substr($prefix, 1);
        }

        return str_replace('/', '_', $prefix);
    }

    /**
     * Parse shortcut notation
     *
     * @param string $shortcut
     *
     * @return array
     */
    protected function parseShortcutNotation($shortcut)
    {
        $entity = str_replace('/', '\\', $shortcut);

        if (false === $pos = strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The entity name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Blog/Post)', $entity));
        }

        return array(substr($entity, 0, $pos), substr($entity, $pos + 1));
    }


    /**
     * Get the twig environment that will render skeletons
     *
     * @return \Twig_Environment
     */
    protected function getTwigEnvironment()
    {
        $twigEnvironment = new \Twig_Environment(new \Twig_Loader_Filesystem($this->skeletonDirs), array(
            'debug'            => true,
            'cache'            => false,
            'strict_variables' => true,
            'autoescape'       => false,
        ));

        $twigEnvironment->addExtension($this->container->get('twig.extension.text_formatter'));

        return $twigEnvironment;
    }

    /**
     * @param string $template
     * @param string $target
     * @param array  $parameters
     *
     * @return integer
     */
    protected function renderFile($template, $target, $parameters)
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        return file_put_contents($target, $this->render($template, $parameters));
    }

    /**
     * Render
     *
     * @param string $template
     * @param array  $parameters
     *
     * @return string
     */
    protected function render($template, $parameters)
    {
        $twig = $this->getTwigEnvironment();

        return $twig->render($template, $parameters);
    }

    /**
     * Add error
     *
     * @param string $error
     *
     * @return $this
     */
    protected function addError($error)
    {
        $this->errors[] = $error;
        $this->errors[] = '';

        return $this;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Write output message
     *
     * @param array|string $message
     *
     * @return $this|bool
     */
    protected function writeOutput($message)
    {
        if (is_null($this->output)) {
            return false;
        }

        $this->output->writeln($message);

        return $this;
    }
}
