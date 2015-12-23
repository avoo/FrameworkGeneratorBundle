<?php

namespace Avoo\Bundle\GeneratorBundle\Command;

use Doctrine\DBAL\Types\Type;
use Avoo\Bundle\GeneratorBundle\Generator\Template\TemplateInterface;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class CreateModelCommand
 */
class CreateModelCommand extends GenerateDoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('avoo:generate:model')
            ->setDescription('Generates a new Sylius entity inside a bundle')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'The fields to create with the new entity')
            ->addOption('no-interface', null, InputOption::VALUE_NONE, 'Generate entity class without interface')
            ->addOption('no-summary', null, InputOption::VALUE_OPTIONAL, 'Disable summary report')
            ->setHelp(<<<EOT
The <info>avoo:model:create</info> task generates a new Doctrine
entity inside a bundle:

<info>php app/console doctrine:generate:entity --entity=AcmeBlogBundle:Blog/Post</info>

The above command would initialize a new entity in the following entity
namespace <info>Acme\BlogBundle\Entity\Blog\Post</info>.

You can also optionally specify the fields you want to generate in the new
entity:

<info>php app/console doctrine:generate:entity --entity=AcmeBlogBundle:Blog/Post --fields="title:string(255) body:text"</info>

The command can also generate the corresponding entity repository class with the
<comment>--with-repository</comment> option:

<info>php app/console doctrine:generate:entity --entity=AcmeBlogBundle:Blog/Post --with-repository</info>

<info>php app/console doctrine:generate:entity --entity=AcmeBlogBundle:Blog/Post</info>

To deactivate the interaction mode, simply use the `--no-interaction` option
without forgetting to pass all needed options:

<info>php app/console doctrine:generate:entity --entity=AcmeBlogBundle:Blog/Post --fields="title:string(255) body:text" --no-interaction</info>
EOT
        );
    }

    /**
     * @throws \InvalidArgumentException When the bundle doesn't end with Bundle (Example: "Bundle/MySampleBundle")
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        $fields = $this->parseFields($input->getOption('fields'));

        if (!$input->getOption('no-summary')) {
            $questionHelper->writeSection($output, 'Entity generation');
        }

        /** @var TemplateInterface $generator */
        $generator = $this->getGenerator();
        $generator->setConfiguration('fields', $fields);

        if ($input->getOption('no-interface')) {
            $generator->setConfiguration('with_interface', false);
        }

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);
        $runner($generator->generate($entity));

        if (!$input->getOption('no-summary')) {
            $questionHelper->writeGeneratorSummary($output, $generator->getErrors());
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if (!$input->getOption('no-summary')) {
            $questionHelper->writeSection($output, 'Welcome to the Sylius entity generator');
            $output->writeln(array(
                '',
                'This command helps you generate Sylius entities.',
                '',
                'First, you need to give the entity name you want to generate.',
                'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
                '',
            ));
        }

        $bundleNames = $this->getContainer()->get('kernel')->getBundles();
        $bundleList = array();
        $excludeBundle = array('AvooCoreBundle', 'AvooBackendBundle', 'SyliusRbacBundle');
        foreach ($bundleNames as $bundle) {
            if ($bundle instanceof AbstractResourceBundle) {
                $bundleList[] = $bundle->getName();
            }
        }

        $bundleList = array_diff($bundleList, $excludeBundle);

        while (true) {
            $question = new Question($questionHelper->getQuestion('The Entity shortcut name', $input->getOption('entity')), $input->getOption('entity'));
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
            $question->setAutocompleterValues($bundleList);

            $entity = $input->getOption('entity');
            if (!$input->getOption('no-summary') || is_null($entity)) {
                $entity = $questionHelper->ask($input, $output, $question);
            }

            list($bundle, $entity) = $this->parseShortcutNotation($entity);

            // check reserved words
            if ($this->getGenerator()->isReservedKeyword($entity)) {
                $output->writeln(sprintf('<bg=red> "%s" is a reserved word</>.', $entity));
                $input->setOption('no-summary', null);

                continue;
            }

            try {
                $b = $this->getContainer()->get('kernel')->getBundle($bundle);
                $class = $this->getContainer()->get('doctrine')->getAliasNamespace($b->getName()) . '\\' . str_replace('\\', '/', $entity);

                if (!class_exists($class)) {
                    break;
                }

                $output->writeln(sprintf('<bg=red>Entity "%s:%s" already exists</>.', $bundle, $entity));
            } catch (\Exception $e) {
                $output->writeln('<bg=red>' . $e->getMessage() . '</>');
            }

            $input->setOption('no-summary', null);
        }

        $input->setOption('entity', $bundle.':'.$entity);

        // fields
        $input->setOption('fields', $this->addFields($input, $output, $questionHelper));

        // summary
        if (!$input->getOption('no-summary')) {
            $output->writeln(array(
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
                '',
                sprintf("You are going to generate a \"<info>%s:%s</info>\" Sylius model", $bundle, $entity),
                sprintf("using the \"<info>%s</info>\" format.", 'xml'),
                '',
            ));
        }
    }

    private function parseFields($input)
    {
        if (is_array($input)) {
            return $input;
        }

        $fields = array();
        foreach (explode(' ', $input) as $value) {
            $elements = explode(':', $value);
            $name = $elements[0];
            if (strlen($name)) {
                $type = isset($elements[1]) ? $elements[1] : 'string';
                preg_match_all('/(.*)\((.*)\)/', $type, $matches);
                $type = isset($matches[1][0]) ? $matches[1][0] : $type;
                $length = isset($matches[2][0]) ? $matches[2][0] : null;

                $fields[$name] = array('fieldName' => $name, 'type' => $type, 'length' => $length);
            }
        }

        return $fields;
    }

    private function addFields(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        $fields = $this->parseFields($input->getOption('fields'));
        $output->writeln(array(
            '',
            'Instead of starting with a blank entity, you can add some fields now.',
            'Note that the primary key will be added automatically (named <comment>id</comment>).',
            '',
        ));
        $output->write('<info>Available types:</info> ');

        $types = array_keys(Type::getTypesMap());
        $count = 20;
        foreach ($types as $i => $type) {
            if ($count > 50) {
                $count = 0;
                $output->writeln('');
            }
            $count += strlen($type);
            $output->write(sprintf('<comment>%s</comment>', $type));
            if (count($types) != $i + 1) {
                $output->write(', ');
            } else {
                $output->write('.');
            }
        }
        $output->writeln('');

        $fieldValidator = function ($type) use ($types) {
            // FIXME: take into account user-defined field types
            if (!in_array($type, $types)) {
                throw new \InvalidArgumentException(sprintf('Invalid type "%s".', $type));
            }

            return $type;
        };

        $lengthValidator = function ($length) {
            if (!$length) {
                return $length;
            }

            $result = filter_var($length, FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 1),
            ));

            if (false === $result) {
                throw new \InvalidArgumentException(sprintf('Invalid length "%s".', $length));
            }

            return $length;
        };

        while (true) {
            $output->writeln('');
            $generator = $this->getGenerator();
            $question = new Question($questionHelper->getQuestion('New field name (press <return> to stop adding fields)', null), null);
            $question->setValidator(function ($name) use ($fields, $generator) {
                if (isset($fields[$name]) || 'id' == $name) {
                    throw new \InvalidArgumentException(sprintf('Field "%s" is already defined.', $name));
                }

                // check reserved words
                if ($generator->isReservedKeyword($name)) {
                    throw new \InvalidArgumentException(sprintf('Name "%s" is a reserved word.', $name));
                }

                return $name;
            });

            $columnName = $questionHelper->ask($input, $output, $question);
            if (!$columnName) {
                break;
            }

            $defaultType = 'string';

            // try to guess the type by the column name prefix/suffix
            if (substr($columnName, -3) == '_at') {
                $defaultType = 'datetime';
            } elseif (substr($columnName, -3) == '_id') {
                $defaultType = 'integer';
            } elseif (substr($columnName, 0, 3) == 'is_') {
                $defaultType = 'boolean';
            } elseif (substr($columnName, 0, 4) == 'has_') {
                $defaultType = 'boolean';
            }

            $question = new Question($questionHelper->getQuestion('Field type', $defaultType), $defaultType);
            $question->setValidator($fieldValidator);
            $question->setAutocompleterValues($types);
            $type = $questionHelper->ask($input, $output, $question);

            $data = array('columnName' => $columnName, 'fieldName' => lcfirst(Container::camelize($columnName)), 'type' => $type);

            if ($type == 'string') {
                $question = new Question($questionHelper->getQuestion('Field length', 255), 255);
                $question->setValidator($lengthValidator);
                $data['length'] = $questionHelper->ask($input, $output, $question);
            }

            $fields[$columnName] = $data;
        }

        return $fields;
    }

    protected function createGenerator()
    {
        return $this->getContainer()->get('avoo.resource_generator')->getTemplate('model');
    }
}
