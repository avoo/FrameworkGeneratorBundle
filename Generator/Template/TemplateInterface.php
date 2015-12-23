<?php

namespace Avoo\Bundle\GeneratorBundle\Generator\Template;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface TemplateInterface
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
interface TemplateInterface
{
    /**
     * Set configuration property
     *
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function setConfiguration($key, $value);

    /**
     * Build default configuration
     *
     * @param mixed                $parameters
     * @param OutputInterface|null $output
     *
     * @return $this
     */
    public function buildDefaultConfiguration($parameters = null, OutputInterface $output = null);

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfiguration();

    /**
     * Validate resource name
     *
     * @param string $resourceName
     */
    public function validateResourceName($resourceName);

    /**
     * Generate template
     *
     * @param string          $resourceName
     * @param OutputInterface $output
     *
     * @return $this
     */
    public function generate($resourceName, OutputInterface $output = null);

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors();
}
