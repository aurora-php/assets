<?php

/*
 * This file is part of the 'octris/assets' package.
 *
 * (c) Harald Lapp <harald@octris.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Octris\Assets;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package;
use Composer\Package\PackageInterface;
use Composer\Installer\PackageEvent;

/**
 * Assets installer.
 *
 * @copyright   copyright (c) 2017 by Harald Lapp
 * @author      Harald Lapp <harald@octris.org>
 */
class Installer
{
    /**
     * Log levels.
     */
    const LOG_INFO = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;
    const LOG_CUSTOM = 4;

    /**
     * Namespace of extra field of composer.json for asset configuration.
     *
     * @type    string
     */
    const NS_EXTRA = 'octris/assets';

    /**
     * Default assets namespace.
     *
     * @type    string
     */
    const NS_ASSETS = 'assets';

    /**
     * Instance of composer io channel.
     *
     * @type    \Composer\IO\IOInterface;
     */
    protected $io;

    /**
     * Assets directories.
     *
     * @type    array
     */
    protected $assets_dirs = array();

    /**
     * Path to root package.
     *
     * @type    string
     */
    protected $root_path;

    /**
     * Constructor.
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->io = $io;
        $this->root_path = realpath(dirname(\Composer\Factory::getComposerFile()));

        $package = $composer->getPackage();
        $extra = $package->getExtra();

        $this->assets_dirs = $this->getDirectories('target', $extra);
    }

    /**
     * Extract and normalize array of directories from package configuration.
     * 
     * @param   string  $type                   Type of directories.
     * @param   array   $extra                  Package configuration.
     * @return  array                           Extracted directories.
     */
    protected function getDirectories($type, array $extra)
    {
        $dirs = (isset($extra[self::NS_EXTRA]) && isset($extra[self::NS_EXTRA][$type])
                    ? (is_array($extra[self::NS_EXTRA][$type])
                        ? $extra[self::NS_EXTRA][$type]
                        : array(self::NS_ASSETS => $extra[self::NS_EXTRA][$type]))
                    : array());
        
        $dirs = array_filter(
            $dirs,
            function ($dir) {
                return is_string($dir);
            }
        );
        
        return $dirs;
    }

    /**
     * Determine assets to remove.
     * 
     * @param   array   $old_dirs               Initial directories.
     * @param   array   $new_dirs               Updated directories.
     * @return  array                           Directories to remove.
     */
    protected function getRemovableDirectories(array $old_dirs, array $new_dirs = array())
    {
        $dirs = array_filter(
            $old_dirs,
            function ($dir, $ns) {
                return !(isset($new_dirs[$ns]) && $dir === $new_dirs[$ns]);
            },
            ARRAY_FILTER_USE_BOTH
        );
        
        return $dirs;
    }

    /**
     * Handle assets for updated package.
     */
    public function updatePackage(PackageEvent $event)
    {
        $operation = $event->getOperation();

        $old_pkg = $operation->getInitialPackage();
        $new_pkg = $operation->getTargetPackage();

        // determine assets to remove
        $old_dirs = $this->getDirectories('source', $old_pkg->getExtra());
        $new_dirs = $this->getDirectories('source', $new_pkg->getExtra());

        $remove_dirs = $this->getRemovableDirectories($old_dirs, $new_dirs);

        $this->removeAssets($event, $remove_dirs);
        $this->installAssets($event, $new_pkg, $new_dirs);

        return $this;
    }

    /**
     * Handle assets for new installed package.
     */
    public function installPackage(PackageEvent $event)
    {
        $operation = $event->getOperation();

        $package = $operation->getPackage();
        
        $this->installAssets($event, $package, $this->getSourceDirectories($package->getExtra()));

        return $this;
    }

    /**
     * Uninstall assets of a package.
     */
    public function uninstallPackage(PackageEvent $event)
    {
        $operation = $event->getOperation();

        $old_pkg = $operation->getInitialPackage();
        $new_pkg = $operation->getTargetPackage();
    }

    /**
     * Remove assets of a package.
     * 
     * @param   \Composer\Installer\PackageEvent    $event      Event.
     * @param   \Composer\Package\PackageInterface  $package    Removed package.
     * @param   array                               $dirs       Assets directories.
     */
    protected function removeAssets(PackageEvent $event, PackageInterface $package, array $dirs)
    {
        $package_path = $event->getComposer()->getInstallationManager()->getInstallPath($package);
        $package_name = $package->getName();

        foreach ($dirs as $ns => $dir) {
            if (!isset($this->assets_dirs[$ns])) {
                $this->log(self::LOG_WARNING, 'Namespace not defined in root package "%s".', $ns);
            } else {
                $source_path = $package_path . '/' . $dir;
                $target_path = $this->root_path . '/' . $this->assets_dirs[$ns] . '/' . $package_name;
                $target_dir = dirname($target_path);
                
                if (!is_dir($target_dir)) {
                    continue;
                }
                
                if (is_link($target_path)) {
                    if (!unlink($target_path)) {
                        $this->log(self::LOG_ERROR, 'Unable to remove link to asset "%s" -> "%s".', $source_path, $target_path);
                    }
                }                
            }
        }
    }

    /**
     * Install assets for a package.
     * 
     * @param   \Composer\Installer\PackageEvent    $event      Event.
     * @param   \Composer\Package\PackageInterface  $package    Installed package.
     * @param   array                               $dirs       Assets directories.
     */
    protected function installAssets(PackageEvent $event, PackageInterface $package, array $dirs)
    {
        $package_path = $event->getComposer()->getInstallationManager()->getInstallPath($package);
        $package_name = $package->getName();

        foreach ($dirs as $ns => $dir) {
            $source_path = $package_path . '/' . $dir;

            if (!isset($this->assets_dirs[$ns])) {
                $this->log(self::LOG_WARNING, 'Namespace not defined in root package "%s".', $ns);
            } elseif (!is_dir($source_path)) {
                $this->log(self::LOG_WARNING, 'Asset directory does not exist "%s".', $dir);
            } else {
                $target_path = $this->root_path . '/' . $this->assets_dirs[$ns] . '/' . $package_name;
                $target_dir = dirname($target_path);
                
                $this->log(self::LOG_CUSTOM, 'Installing asset <info>%s/%s</info>', $package_name, $dir);

                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0777, true)) {
                        $this->log(self::LOG_ERROR, 'Unable to create target directory "%s".', $target_dir);
                        continue;
                    }
                }
                
                if (is_link($target_path)) {
                    $link_path = readlink($target_path);

                    if ($link_path == $target_path) {
                        continue;
                    }

                    if (!unlink($target_path)) {
                        $this->log(self::LOG_ERROR, 'Unable to update link to asset "%s" -> "%s".', $source_path, $target_path);
                        continue;
                    }

                    if (!symlink($source_path, $target_path)) {
                        $this->log(self::LOG_ERROR, 'Unable to create link to asset "%s" -> "%s".', $source_path, $target_path);
                    }
                }                
            }
        }
    }

    /**
     * Cleanup asset directory, remove links pointing nowhere.
     */
    public function cleanup()
    {
        $this->log(self::LOG_INFO, 'Cleanup asset directories');

        foreach ($this->assets_dirs as $ns => $dir) {
            if (!is_dir($this->root_path . '/' . $dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->root_path . '/' . $dir,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $object) {
                if ($object->isLink()) {
                    $target_path = (string)$object;
                    $link_path = readlink($target_path);

                    if (!file_exists($link_path)) {
                        $this->log(self::LOG_CUSTOM, 'Removing unresolved path <info>%s</info>', $target_path);
                        
                        if (!unlink($target_path)) {
                            $this->log(self::LOG_ERROR, 'Unable to remove unresolved path "%s" -> "%s".', $link_path, $target_path);
                        }
                    }
                } elseif ($object->isDir()) {
                    if (count(glob('{,.}*', GLOB_BRACE | GLOB_NOSORT)) == 0) {
                        if (!rmdir((string)$object)) {
                            $this->log(self::LOG_WARNING, 'Unable to remove empty directory "%s".', (string)$object);
                        }
                    }
                }
            }
        }
    }

    /**
     * Log status messages.
     */
    protected function log($type, $message, ...$args)
    {
        switch ($type) {
            case self::LOG_INFO:
                $this->io->write(array('<info>  - ' . sprintf($message, ...$args) . '</info>'));
                break;
            case self::LOG_WARNING:
                $this->io->write(array('<warning>  ! ' . sprintf($message, ...$args) . '</warning>'));
                break;
            case self::LOG_ERROR:
                $this->io->write(array('<error>  ! ' . sprintf($message, ...$args) . '</error>'));
                break;
            case self::LOG_CUSTOM:
                $this->io->write(array('  - ' . sprintf($message, ...$args)));
                break;
        }
    }
}
