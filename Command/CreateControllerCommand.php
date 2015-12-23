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

class CreateControllerCommand extends GeneratorCommand
{
    /**
     * @see Command
     */
    public function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('controller', '', InputOption::VALUE_REQUIRED, 'The name of the controller to create'),
            ))
            ->setDescription('Generates a controller')
            ->addOption('no-summary', null, InputOption::VALUE_OPTIONAL, 'Disable summary report')
            ->setHelp(<<<EOT
The <info>avoo:generate:controller</info> command helps you generates new controllers
inside bundles.

By default, the command interacts with the developer to tweak the generation.
Any passed option will be used as a default value for the interaction
(<comment>--bundle</comment> and <comment>--controller</comment> are the only
ones needed if you follow the conventions):

<info>php app/console avoo:generate:controller --controller=AcmeBlogBundle:Post</info>

If you want to disable any user interaction, use <comment>--no-interaction</comment>
but don't forget to pass all needed options:

<info>php app/console avoo:generate:controller --controller=AcmeBlogBundle:Post --no-interaction</info>

Every generated file is based on a template. There are default templates but they can
be overriden by placing custom templates in one of the following locations, by order of priority:

<info>BUNDLE_PATH/Resources/ResourceGeneratorBundle/skeleton/controller
APP_PATH/Resources/ResourceGeneratorBundle/skeleton/controller</info>

EOT
            )
            ->setName('avoo:generate:controller')
        ;
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

        if (null === $input->getOption('controller')) {
            throw new \RuntimeException('The controller option must be provided.');
        }

        list($bundle) = $this->parseShortcutNotation($input->getOption('controller'));
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
        $questionHelper->writeSection($output, 'Controller generation');

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);
        $runner($generator->generate($input->getOption('controller')));

        if (!$input->getOption('no-summary')) {
            $questionHelper->writeGeneratorSummary($output, $generator->getErrors());
        }
    }

    public function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if (!$input->getOption('no-summary')) {
            $questionHelper->writeSection($output, 'Welcome to the Sylius controller generator');
            $output->writeln(array(
                '',
                'Every page, and even sections of a page, are rendered by a <comment>controller</comment>.',
                'This command helps you generate them easily.',
                '',
                'First, you need to give the controller name you want to generate.',
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
            $question = new Question($questionHelper->getQuestion('Controller name', $input->getOption('controller')), $input->getOption('controller'));
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateControllerName'));
            $question->setAutocompleterValues($bundleList);

            $controller = $input->getOption('controller');
            if (!$input->getOption('no-summary') || is_null($controller)) {
                $controller = $questionHelper->ask($input, $output, $question);
            }

            list($bundle, $controller) = $this->parseShortcutNotation($controller);

            try {
                $b = $this->getContainer()->get('kernel')->getBundle($bundle);

                if (!file_exists($b->getPath().'/Controller/'.$controller.'Controller.php')) {
                    break;
                }

                $output->writeln(sprintf('<bg=red>Controller "%s:%s" already exists.</>', $bundle, $controller));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }

            $input->setOption('no-summary', null);
        }

        $input->setOption('controller', $bundle.':'.$controller);

        // summary
        if (!$input->getOption('no-summary')) {
            $output->writeln(array(
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg-white', true),
                '',
                sprintf('You are going to generate a "<info>%s:%s</info>" controller', $bundle, $controller),
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
        return $this->getContainer()->get('avoo.resource_generator')->getTemplate('controller');
    }
}
