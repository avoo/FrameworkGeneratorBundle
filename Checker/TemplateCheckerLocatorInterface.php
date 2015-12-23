<?php

namespace Avoo\Bundle\GeneratorBundle\Checker;

use Avoo\Bundle\GeneratorBundle\Generator\Template\TemplateInterface;

/**
 * Interface TemplateCheckerLocatorInterface
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
interface TemplateCheckerLocatorInterface
{
    /**
     * Get Known checker types
     *
     * @return string[]
     */
    public function getTypes();

    /**
     * Is template checker of type $type known?
     *
     * @param string $type
     *
     * @return Boolean
     */
    public function has($type);

    /**
     * Get requested template Checker
     *
     * @param string $type
     *
     * @return TemplateInterface
     */
    public function get($type);
}
