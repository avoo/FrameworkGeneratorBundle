<?php

namespace Avoo\Bundle\GeneratorBundle\Generator;
use Avoo\Bundle\GeneratorBundle\Generator\Template\TemplateInterface;

/**
 * Interface ResourceGeneratorInterface
 */
interface ResourceGeneratorInterface
{
    /**
     * Set template
     *
     * @param string $template
     *
     * @return $this
     */
    public function setTemplate($template);

    /**
     * Get current template
     *
     * @param string $template
     *
     * @return TemplateInterface $template
     */
    public function getTemplate($template = null);

    /**
     * Generate resource
     *
     * @param string $resourceName
     *
     * @return $this
     */
    public function generate($resourceName);
}
