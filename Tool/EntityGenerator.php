<?php

namespace Avoo\Bundle\GeneratorBundle\Tool;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\EntityGenerator as BaseEntitygenerator;

/**
 * Class EntityGenerator
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class EntityGenerator extends BaseEntitygenerator
{
    /**
     * The class all generated entities should interface.
     *
     * @var string
     */
    protected $classToInterface;

    /**
     * @var string
     */
    protected static $classTemplate =
        '<?php

<namespace>

<entityAnnotation>
<entityClassName>
{
<entityBody>
}
';

    /**
     * {@inheritdoc}
     */
    public function generateEntityClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<entityAnnotation>',
            '<entityClassName>',
            '<entityBody>'
        );

        $replacements = array(
            $this->generateEntityNamespace($metadata),
            $this->generateEntityDocBlock($metadata),
            $this->generateEntityClassName($metadata),
            $this->generateEntityBody($metadata)
        );

        $code = str_replace($placeHolders, $replacements, self::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityClassName(ClassMetadataInfo $metadata)
    {
        return 'class ' . $this->getClassName($metadata) .
        ($this->extendsClass() ? ' extends ' . $this->getClassToExtendName() : null) .
        ($this->interfaceClass() ? ' implements ' . $this->getClassToInterfaceName() : null);
    }

    /**
     * Sets the name of the class the generated classes should interface from.
     *
     * @param string $classToInterface
     *
     * @return void
     */
    public function setClassToInterface($classToInterface)
    {
        $this->classToInterface = $classToInterface;
    }

    /**
     * @return bool
     */
    protected function interfaceClass()
    {
        return $this->classToInterface ? true : false;
    }

    /**
     * @return string
     */
    protected function getClassToInterface()
    {
        return $this->classToInterface;
    }

    /**
     * @return string
     */
    protected function getClassToInterfaceName()
    {
        $reflection = new \ReflectionClass($this->getClassToInterface());

        return $reflection->getShortName();
    }
}
