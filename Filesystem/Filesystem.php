<?php

namespace Avoo\Bundle\GeneratorBundle\Filesystem;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as BaseFileSystem;

/**
 * Class Filesystem
 *
 * @author Jérémy Jégou <jejeavo@gmail.com>
 */
class Filesystem extends BaseFilesystem
{
    /**
     * @var $twig
     */
    protected $twigEnvironment;

    /**
     * @var array $parameters;
     */
    protected $parameters;

    /**
     * Filesystem constructor.
     *
     * @param \Twig_Environment|null $twigEnvironment
     * @param array                  $parameters
     */
    public function __construct(\Twig_Environment $twigEnvironment = null, $parameters = array())
    {
        if (!is_null($twigEnvironment)) {
            $this->twigEnvironment = $twigEnvironment;
        }

        $this->parameters = $parameters;
    }

    public function setTwigEnvironment(\Twig_Environment $twigEnvironment)
    {
        $this->twigEnvironment = $twigEnvironment;

        return $this;
    }

    /**
     * Set twig rendering parameters
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Get twig rendering parameters
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($originFile, $targetFile, $override = false)
    {
        if (stream_is_local($originFile) && !is_file($originFile)) {
            throw new FileNotFoundException(sprintf('Failed to copy "%s" because file does not exist.', $originFile), 0, null, $originFile);
        }

        $this->mkdir(dirname($targetFile));

        $doCopy = true;
        if (!$override && null === parse_url($originFile, PHP_URL_HOST) && is_file($targetFile)) {
            $doCopy = filemtime($originFile) > filemtime($targetFile);
        }

        if ($doCopy) {
            if (!is_null($this->twigEnvironment)) {
                if (isset($this->parameters['rename'][$originFile->getFilename()])) {
                    $targetFile = str_replace($originFile->getFilename(), $this->parameters['rename'][$originFile->getFilename()], $targetFile);
                }

                foreach ($this->twigEnvironment->getLoader()->getPaths() as $path) {
                    if (false !== strpos($originFile->getPathname(), $path)) {
                        $originFile = str_replace($path . '/', '', $originFile);
                        break;
                    }
                }

                return file_put_contents($targetFile, $this->twigEnvironment->render($originFile, $this->parameters));
            }

            // https://bugs.php.net/bug.php?id=64634
            if (false === $source = @fopen($originFile, 'r')) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s" because source file could not be opened for reading.', $originFile, $targetFile), 0, null, $originFile);
            }

            // Stream context created to allow files overwrite when using FTP stream wrapper - disabled by default
            if (false === $target = @fopen($targetFile, 'w', null, stream_context_create(array('ftp' => array('overwrite' => true))))) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s" because target file could not be opened for writing.', $originFile, $targetFile), 0, null, $originFile);
            }

            $bytesCopied = stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            unset($source, $target);

            if (!is_file($targetFile)) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s".', $originFile, $targetFile), 0, null, $originFile);
            }

            // Like `cp`, preserve executable permission bits
            @chmod($targetFile, fileperms($targetFile) | (fileperms($originFile) & 0111));

            if (stream_is_local($originFile) && $bytesCopied !== ($bytesOrigin = filesize($originFile))) {
                throw new IOException(sprintf('Failed to copy the whole content of "%s" to "%s" (%g of %g bytes copied).', $originFile, $targetFile, $bytesCopied, $bytesOrigin), 0, null, $originFile);
            }
        }
    }
}
