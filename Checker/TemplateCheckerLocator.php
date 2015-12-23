<?php

namespace Avoo\Bundle\GeneratorBundle\Checker;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class TemplateCheckerLocator
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class TemplateCheckerLocator extends ContainerAware implements TemplateCheckerLocatorInterface
{
    /**
     * @var array $checkers
     */
    private $checkers;

    /**
     * Construct
     *
     * @param array $checkers
     */
    public function __construct(array $checkers)
    {
        $this->checkers = $checkers;
    }

    /**
     * Get checkers list
     *
     * @return array $checkers
     */
    public function getCheckers()
    {
        return $this->checkers;
    }

    /**
     * {@inheritdoc}
     */
    public function has($type)
    {
        if (!isset($this->checkers[$type])) {
            return false;
        }

        return $this->container->has($this->checkers[$type]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($type)
    {
        if (!$this->has($type)) {
            throw new NotFoundHttpException(sprintf('The template "%s" does not exist.', $type));
        }

        return $this->container->get($this->checkers[$type]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes()
    {
        return array_keys($this->checkers);
    }
}
