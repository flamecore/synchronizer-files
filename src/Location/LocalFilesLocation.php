<?php
/**
 * Synchronizer Library
 * Copyright (C) 2014 IceFlame.net
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 *
 * @package  FlameCore\Synchronizer
 * @version  0.1-dev
 * @link     http://www.flamecore.org
 * @license  http://opensource.org/licenses/ISC ISC License
 */

namespace FlameCore\Synchronizer\Files\Location;

use Symfony\Component\Filesystem\Filesystem;

/**
 * The LocalFilesLocation class
 *
 * @author   Christian Neff <christian.neff@gmail.com>
 */
class LocalFilesLocation implements FilesSourceInterface, FilesTargetInterface
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * {@inheritdoc}
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function __construct(array $settings, Filesystem $filesystem = null)
    {
        if (!isset($settings['dir']) || !is_string($settings['dir'])) {
            throw new \InvalidArgumentException(sprintf('The %s does not define "dir" setting.', get_class($this)));
        }

        if ($this->isAbsolutePath($settings['dir'])) {
            $path = $settings['dir'];

            if (!is_dir($path)) {
                throw new \LogicException(sprintf('The path "%s" does not exist.', $path));
            }
        } else {
            $path = $this->toAbsolutePath($settings['dir']);

            if (!$path) {
                throw new \LogicException(sprintf('The absolute path for "%s" could not be determined.', $settings['dir']));
            }
        }

        $this->path = $path;
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    /**
     * @return string
     */
    public function getFilesPath()
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function get($file)
    {
        return file_get_contents($this->getRealPathName($file));
    }

    /**
     * {@inheritdoc}
     */
    public function put($file, $content, $mode)
    {
        $filename = $this->getRealPathName($file);

        try {
            $this->filesystem->dumpFile($filename, $content, $mode);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($file, $mode)
    {
        try {
            $this->filesystem->chmod($this->getRealPathName($file), $mode);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove($file)
    {
        try {
            $this->filesystem->remove($this->getRealPathName($file));

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($name, $mode = 0777)
    {
        try {
            $this->filesystem->mkdir($this->getRealPathName($name), $mode);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeDir($name)
    {
        try {
            $this->filesystem->remove($this->getRealPathName($name));

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFilesList($exclude = false)
    {
        $fileslist = array();
        $iterator = new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS);

        if ((is_string($exclude) || is_array($exclude)) && !empty($exclude)) {
            $iterator = new \RecursiveCallbackFilterIterator($iterator, function ($current) use ($exclude) {
                if ($current->isDir()) {
                    return true;
                }

                $subpath = substr($current->getPathName(), strlen($this->path) + 1);

                foreach ((array) $exclude as $pattern) {
                    if ($pattern[0] == '!' ? !fnmatch(substr($pattern, 1), $subpath) : fnmatch($pattern, $subpath)) {
                        return false;
                    }
                }

                return true;
            });
        }

        $iterator = new \RecursiveIteratorIterator($iterator);
        foreach ($iterator as $file) {
            $pathname = substr($file->getPathName(), strlen($this->path));
            $filename = $file->getBasename();
            $dirname = dirname($pathname);

            $fileslist[$dirname][$filename] = substr($pathname, 1);
        }

        return $fileslist;
    }

    /**
     * {@inheritdoc}
     */
    public function getRealPathName($file)
    {
        return $this->path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    }

    /**
     * {@inheritdoc}
     */
    public function getFileMode($file)
    {
        $filename = $this->getRealPathName($file);

        return file_exists($filename) ? fileperms($filename) & 0777 : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileHash($file)
    {
        $filename = $this->getRealPathName($file);

        return is_readable($filename) ? hash_file('crc32b', $filename) : false;
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function isAbsolutePath($path)
    {
        return $path[0] === DIRECTORY_SEPARATOR || preg_match('#^(?:/|\\\\|[A-Za-z]:\\\\|[A-Za-z]:/)#', $path);
    }

    /**
     * @param string $path
     * @return string|bool
     */
    protected function toAbsolutePath($path)
    {
        return realpath(getcwd() . DIRECTORY_SEPARATOR . $path);
    }
}
