<?php

namespace Avoo\Bundle\GeneratorBundle\Twig\Extension;

/**
 * Class TextExtension
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class TextFormatterExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('ucfirst', 'ucfirst'),
        );
    }

    public function getName()
    {
        return 'text_formatter_extension';
    }
}
