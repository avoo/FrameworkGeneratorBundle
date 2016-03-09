<?php

namespace Avoo\Bundle\GeneratorBundle\Command;

use Avoo\Bundle\GeneratorBundle\Generator\ResourceGeneratorInterface;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class CreateResource
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class CreateResourceCommand extends ContainerAwareCommand
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var ResourceGeneratorInterface $generator
     */
    protected $generator;

    /**
     * @var array $commands
     */
    protected $commands;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('avoo:generate:resource')
            ->setDescription('Create a resource.')
            ->setDefinition(array(
                new InputOption('resource', null, InputOption::VALUE_REQUIRED, 'The resource name.'),
            ));
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $progress = $this->createProgressBar($output, count($this->commands));

        foreach ($this->commands as $c) {
            $command = array_shift($c);
            $parameters = $c;

            $this->runCommand($command, $parameters, $output);
            $progress->advance();
            $output->writeln('');

            sleep(1);
        }

        $progress->finish();
    }

    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Sylius resource generator');

        // namespace
        $output->writeln(array(
            '',
            'This command helps you generate Sylius resource.',
            '',
            'First, you need to give the entity name you want to generate.',
            'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
            '',
        ));

        $bundleNames = $this->getContainer()->get('kernel')->getBundles();
        $bundleList = array();

        foreach ($bundleNames as $bundle) {
            if ($bundle instanceof AbstractResourceBundle) {
                $bundleList[] = $bundle->getName();
            }
        }

        while (true) {
            $question = new Question($questionHelper->getQuestion('The Entity shortcut name', $input->getOption('resource')), $input->getOption('resource'));
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
            $question->setAutocompleterValues($bundleList);
            $resource = $questionHelper->ask($input, $output, $question);

            $resource = Validators::validateEntityName($resource);
            list($bundle, $entity) = $this->parseShortcutNotation($resource);

            $bundle = Validators::validateBundleName($bundle);

            try {
                $this->getContainer()->get('kernel')->getBundle($bundle);
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
                continue;
            }

            if (in_array($entity, Validators::getReservedWords())) {
                $output->writeln(sprintf('<bg=red> "%s" is a reserved word</>.', $entity));
                continue;
            }

            break;
        }

        $input->setOption('resource', $resource);
        $dialog = $this->getHelper('dialog');

        if ($dialog->askConfirmation($output, '<question>Would you add form? (y/N)</question>')) {
            $this->commands[] = array(
                'avoo:generate:form',
                '--entity' => $resource,
                '--no-summary' => true,
            );

            if ($dialog->askConfirmation($output, '<question>Would you add controller? (y/N)</question>')) {
                $this->commands[] = array(
                    'avoo:generate:controller',
                    '--controller' => $resource,
                    '--no-summary' => true,
                );

                if ($dialog->askConfirmation($output, '<question>Would you add CRUD? (y/N)</question>')) {
                    $backend = $dialog->askConfirmation($output, '<question>With backend? (y/N)</question>');
                    $crud = array();

                    if (!$backend) {
                        $crud['--no-backend'] = true;
                    } else {
                        while (true) {
                            $question = new Question($questionHelper->getQuestion('Backend bundle', null));
                            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName'));
                            $question->setAutocompleterValues($bundleList);
                            $backendBundle = $questionHelper->ask($input, $output, $question);

                            try {
                                $this->getContainer()->get('kernel')->getBundle($backendBundle);

                                break;
                            } catch (\Exception $e) {
                                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $backendBundle));
                                $input->setOption('no-summary', null);

                                continue;
                            }
                        }

                        $crud['--backend'] = $backendBundle;
                    }

                    $this->commands[] = array_merge(array(
                        'avoo:generate:crud',
                        '--entity' => $resource,
                        '--no-summary' => true,
                    ), $crud);
                }
            }

            $this->commands[] = array(
                'cache:clear',
                '--no-warmup' => true
            );
        }
    }

    /**
     * Run command
     *
     * @param string          $command
     * @param array           $parameters
     * @param OutputInterface $output
     *
     * @throws \RuntimeException
     * @throws \Exception
     *
     * @return $this
     */
    public function runCommand($command, $parameters = array(), OutputInterface $output = null)
    {
        $parameters = array_merge(
            array('command' => $command),
            $this->getDefaultParameters(),
            $parameters
        );

        $this->getApplication()->setAutoExit(false);
        $exitCode = $this->getApplication()->run(new ArrayInput($parameters), $output ?: new NullOutput());

        if (1 === $exitCode) {
            throw new \RuntimeException('This command terminated with a permission error');
        }

        if (0 !== $exitCode) {
            $this->getApplication()->setAutoExit(true);

            $errorMessage = sprintf('The command terminated with an error code: %u.', $exitCode);
            $this->output->writeln("<error>$errorMessage</error>");
            $exception = new \Exception($errorMessage, $exitCode);

            throw $exception;
        }

        return $this;
    }

    /**
     * Get default parameters.
     *
     * @return array
     */
    protected function getDefaultParameters()
    {
        $defaultParameters = array('--no-debug' => true);

        if ($this->input->hasOption('env')) {
            $defaultParameters['--env'] = $this->input->hasOption('env') ? $this->input->getOption('env') : 'dev';
        }

        if ($this->input->hasOption('no-interaction') && true === $this->input->getOption('no-interaction')) {
            $defaultParameters['--no-interaction'] = true;
        }

        if ($this->input->hasOption('verbose') && true === $this->input->getOption('verbose')) {
            $defaultParameters['--verbose'] = true;
        }

        return $defaultParameters;
    }

    /**
     * Create progress bar
     *
     * @param OutputInterface $output
     * @param int $length
     *
     * @return ProgressHelper
     */
    protected function createProgressBar(OutputInterface $output, $length = 10)
    {
        $progress = $this->getHelper('progress');
        $progress->setBarCharacter('<info>|</info>');
        $progress->setEmptyBarCharacter(' ');
        $progress->setProgressCharacter('|');

        $progress->start($output, $length);

        return $progress;
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
            throw new \InvalidArgumentException(sprintf('The resource name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Post)', $entity));
        }

        return array(substr($entity, 0, $pos), substr($entity, $pos + 1));
    }

    /**
     * Validate template name
     *
     * @param string $template
     * @param array  $templateList
     *
     * @throws \Exception
     */
    private function validateTemplate($template, $templateList)
    {
        if (empty($template)) {
            throw new \Exception('The template name is required.');
        }

        if (!in_array($template, $templateList)) {
            throw new \InvalidArgumentException(
                sprintf('The template "%s" does not exist, use: %s', $template, implode(', ', $templateList))
            );
        }
    }

    /**
     * Get question helper
     *
     * @return QuestionHelper
     */
    protected function getQuestionHelper()
    {
        $question = $this->getHelperSet()->get('question');
        if (!$question || get_class($question) !== 'Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper') {
            $this->getHelperSet()->set($question = new QuestionHelper());
        }

        return $question;
    }
}
