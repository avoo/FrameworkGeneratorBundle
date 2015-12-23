<?php

namespace Avoo\Bundle\GeneratorBundle\Command;

use Avoo\Bundle\GeneratorBundle\Generator\Template\TemplateInterface;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateFormCommand extends GenerateDoctrineCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The path where to generate entities when it cannot be guessed')
            ->addOption('no-summary', null, InputOption::VALUE_OPTIONAL, 'Disable summary report')
            ->setDescription('Generates a form type class based on a Doctrine entity')
            ->setHelp(<<<EOT
The <info>avoo:generate:form</info> command generates a form class based on a Doctrine entity.

<info>php app/console avoo:generate:form AcmeBlogBundle:Post</info>

Every generated file is based on a template. There are default templates but they can be overriden by placing custom templates in one of the following locations, by order of priority:

<info>BUNDLE_PATH/Resources/ResourceGeneratorBundle/skeleton/form
ResourceGeneratorBundle/Resources/skeleton/form</info>

EOT
            )
            ->setName('avoo:generate:form');
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $entity = Validators::validateEntityName($input->getOption('entity'));

        /** @var TemplateInterface $generator */
        $generator = $this->getGenerator();

        if ($input->getOption('path')) {
            $generator->setConfiguration('path', $input->getOption('path'));
        }

        $questionHelper->writeSection($output, 'Form generation');

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
            $questionHelper->writeSection($output, 'Welcome to the Sylius form generator');

            $output->writeln(array(
                '',
                'This command helps you generate Sylius forms.',
                '',
                'First, you need to give the entity name to generate form.',
                'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
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

                if (class_exists($class)) {
                    break;
                }

                $output->writeln(sprintf('<bg=red>Entity "%s" does not exist.</>.', $entity));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }

            $input->setOption('no-summary', null);
        }

        $input->setOption('entity', $bundle.':'.$entity);
    }

    protected function createGenerator()
    {
        return $this->getContainer()->get('avoo.resource_generator')->getTemplate('form');
    }
}
