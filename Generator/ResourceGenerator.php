<?php

namespace Avoo\Bundle\GeneratorBundle\Generator;

use Avoo\Bundle\GeneratorBundle\Checker\TemplateCheckerLocatorInterface;
use Avoo\Bundle\GeneratorBundle\Generator\Template\TemplateInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class ResourceGenerator
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class ResourceGenerator implements ResourceGeneratorInterface
{
    /**
     * @var TemplateInterface $template
     */
    protected $checkers;

    /**
     * @var KernelInterface $kernel
     */
    protected $kernel;

    /**
     * @var string $resource
     */
    protected $resource;

    /**
     * @var TemplateInterface $template
     */
    protected $template;

    /**
     * Construct
     *
     * @param TemplateCheckerLocatorInterface $checkers
     */
    public function __construct(TemplateCheckerLocatorInterface $checkers, KernelInterface $kernel)
    {
        $this->checkers = $checkers;
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function setTemplate($template)
    {
        $this->template = $this->checkers->get($template);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate($template = null)
    {
        if(!is_null($template)) {
            return $this->checkers->get($template);
        }

        return $this->template;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($resourceName)
    {
        return $this->getTemplate()->generate($resourceName);
    }
}
