<?php

namespace Avoo\Bundle\GeneratorBundle\Command;

use Avoo\Bundle\GeneratorBundle\Generator\Template\TemplateInterface;
use Sensio\Bundle\GeneratorBundle\Command\GeneratorCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateCrudCommand extends GeneratorCommand
{
    /**
     * @see Command
     */
    public function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The name of the entity to create CRUD'),
                new InputOption('backend', '', InputOption::VALUE_OPTIONAL, 'The name of backend bundle'),
                new InputOption('frontend', '', InputOption::VALUE_OPTIONAL, 'The name of backend bundle'),
            ))
            ->setDescription('Generates a crud from existing entity')
            ->addOption('actions','', InputOption::VALUE_REQUIRED, 'The actions in the controller')
            ->addOption('no-frontend', null, InputOption::VALUE_NONE, 'Disable basic CRUD generate routing for frontend bundle')
            ->addOption('no-backend', null, InputOption::VALUE_NONE, 'Disable basic CRUD generate routing for backend bundle')
            ->addOption('no-summary', null, InputOption::VALUE_OPTIONAL, 'Disable summary report')
            ->setHelp(<<<EOT
The <info>avoo:generate:crud</info> command helps you generates new crud from existing entity
inside bundles.

By default, the command interacts with the developer to tweak the generation.
Any passed option will be used as a default value for the interaction
(<comment>--bundle</comment> and <comment>--entity</comment> are the only
ones needed if you follow the conventions):

<info>php app/console avoo:generate:crud --entity=AcmeBlogBundle:Post</info>

If you want to disable any user interaction, use <comment>--no-interaction</comment>
but don't forget to pass all needed options:

<info>php app/console avoo:generate:crud --entity=AcmeBlogBundle:Post --no-interaction</info>

Every generated file is based on a template. There are default templates but they can
be overriden by placing custom templates in one of the following locations, by order of priority:

<info>BUNDLE_PATH/Resources/ResourceGeneratorBundle/skeleton/crud
APP_PATH/Resources/ResourceGeneratorBundle/skeleton/crud</info>

EOT
            )
            ->setName('avoo:generate:crud');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive() && !$input->getOption('no-summary')) {
            $question = new Question($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        if (null === $input->getOption('entity')) {
            throw new \RuntimeException('The entity option must be provided.');
        }

        list($bundle) = $this->parseShortcutNotation($input->getOption('entity'));
        if (is_string($bundle)) {
            $bundle = Validators::validateBundleName($bundle);

            try {
                $this->getContainer()->get('kernel')->getBundle($bundle);
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }
        }

        /** @var TemplateInterface $generator */
        $generator = $this->getGenerator();

        if ($input->getOption('no-backend')) {
            $generator->setConfiguration('backend_crud', false);
        }

        if ($input->getOption('no-frontend')) {
            $generator->setConfiguration('frontend_crud', false);
        }

        if ($input->getOption('actions')) {
            $generator->setConfiguration('actions', explode(',', $input->getOption('actions')));
        }

        $questionHelper->writeSection($output, 'CRUD generation');
        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        if (!$input->getOption('no-backend')) {
             $generator->setConfiguration('backend_bundle', $input->getOption('backend'));
        }

        if (!$input->getOption('no-frontend')) {
            $generator->setConfiguration('frontend_bundle', $input->getOption('frontend'));
        }

        $runner($generator->generate($input->getOption('entity'), $output));

        if (!$input->getOption('no-backend')) {
            $questionHelper->writeSection($output, "Don't forget to run sylius:rbac:initialize command");
        }

        if (!$input->getOption('no-summary')) {
            $questionHelper->writeGeneratorSummary($output, $generator->getErrors());
        }
    }

    public function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if (!$input->getOption('no-summary')) {
            $questionHelper->writeSection($output, 'Welcome to the Sylius CRUD generator');
            $output->writeln(array(
                '',
                'Every page, and even sections of a page, are rendered by a <comment>CRUD</comment>.',
                'This command helps you generate them easily.',
                '',
                'First, you need to give the entity name.',
                'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>',
                '',
            ));
        }

        $bundleNames = $this->getContainer()->get('kernel')->getBundles();
        $bundleList = array();

        foreach ($bundleNames as $bundle) {
            if ($bundle instanceof AbstractResourceBundle) {
                $bundleList[] = $bundle->getName();
            }
        }

        while (true) {
            $question = new Question($questionHelper->getQuestion('Entity name', $input->getOption('entity')), $input->getOption('entity'));
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateControllerName'));
            $question->setAutocompleterValues($bundleList);

            $entity = $input->getOption('entity');
            if (!$input->getOption('no-summary') || is_null($entity)) {
                $entity = $questionHelper->ask($input, $output, $question);
            }

            $message = array();

            list($bundle, $controller) = $this->parseShortcutNotation($entity);

            try {
                $b = $this->getContainer()->get('kernel')->getBundle($bundle);

                if (!file_exists($b->getPath().'/Controller/'.$controller.'Controller.php')) {
                    $output->writeln(sprintf('<bg=red>Controller "%s:%s" does not exist</>.', $bundle, $controller));
                    $input->setOption('no-summary', null);

                    continue;
                }
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }

            if (!$input->getOption('no-summary')) {
                $dialog = $this->getHelper('dialog');

                if (!$input->getOption('backend')) {
                    $backend = $dialog->askConfirmation($output, '<question>With backend? (y/N)</question>');

                    if (!$backend) {
                        $input->setOption('no-backend', true);
                    }
                }

                if (!$input->getOption('frontend')) {
                    $frontend = $dialog->askConfirmation($output, '<question>With frontend? (y/N)</question>');

                    if (!$frontend) {
                        $input->setOption('no-frontend', true);
                    }
                }
            }

            if (!$input->getOption('no-backend')) {
                $question = new Question($questionHelper->getQuestion('Backend bundle', $input->getOption('backend')), $input->getOption('backend'));
                $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName'));
                $question->setAutocompleterValues($bundleList);

                $backendBundle = $input->getOption('backend');
                if (!$input->getOption('no-summary')) {
                    $backendBundle = $questionHelper->ask($input, $output, $question);
                }

                try {
                    $this->getContainer()->get('kernel')->getBundle($backendBundle);
                } catch (\Exception $e) {
                    $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $backendBundle));
                    $input->setOption('no-summary', null);

                    continue;
                }

                $message[] = 'backend CRUD in <info>' . $backendBundle . '</info>';
                $input->setOption('backend', $backendBundle);
            }

            if (!$input->getOption('no-frontend')) {
                $question = new Question($questionHelper->getQuestion('Frontend bundle', $input->getOption('frontend')), $input->getOption('frontend'));
                $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName'));
                $question->setValidator(function ($answer) use ($input) {
                    if (!$input->getOption('no-backend') && $input->getOption('backend') == $answer) {
                        throw new \RuntimeException('The frontend and backend bundle must be different.');
                    }

                    return $answer;
                });

                $question->setAutocompleterValues($bundleList);

                $frontendBundle = $input->getOption('frontend');
                if (!$input->getOption('no-summary')) {
                    $frontendBundle = $questionHelper->ask($input, $output, $question);
                }

                try {
                    $this->getContainer()->get('kernel')->getBundle($frontendBundle);
                } catch (\Exception $e) {
                    $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $frontendBundle));
                    $input->setOption('no-summary', null);

                    continue;
                }

                $message[] = 'frontend CRUD in <info>' . $frontendBundle . '</info>';
                $input->setOption('frontend', $frontendBundle);
            }

            break;
        }

        $input->setOption('entity', $entity);

        // summary
        $entity = $input->getOption('entity');
        if (!$input->getOption('no-summary')) {
            $output->writeln(array(
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg-white', true),
                '',
                sprintf(
                    'You are going to generate a %s %s',
                    implode(' and ', $message),
                    'for <info>' . $entity . '</info> entity'
                ),
            ));
        }
    }

    /**
     * Parse shortcut notation
     *
     * @param string $shortcut
     *
     * @return array
     */
    public function parseShortcutNotation($shortcut)
    {
        $entity = str_replace('/', '\\', $shortcut);

        if (false === $pos = strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The controller name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Post)', $entity));
        }

        return array(substr($entity, 0, $pos), substr($entity, $pos + 1));
    }

    /**
     * Create generator
     *
     * @return TemplateInterface
     */
    protected function createGenerator()
    {
        return $this->getContainer()->get('avoo.resource_generator')->getTemplate('crud');
    }
}
