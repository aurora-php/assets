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

        $this->assets_dir = $this->getDirectories('target');
    }

    /**
     * Get configured directories for specified configuration key.
     */
    protected function getDirectories(PackageInterface $package, $key)
    {
        $extra = $package->getExtra();

        $dirs = (isset($extra[self::NS_EXTRA]) && isset($extra[self::NS_EXTRA]['target'])
                    ? (is_array($extra[self::NS_EXTRA]['target'])
                        ? $extra[self::NS_EXTRA]['target']
                        : array(self::NS_ASSETS => $extra[self::NS_EXTRA]['target']))
                    : array());

        $dirs = array_filter(
            $dirs,
            function($dir) {
                return is_string($dir);
            }
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

        // remove assets that not longer required
        $old_dirs = $this->getDirectories($old_pkg, 'source');
        $new_dirs = $this->getDirectories($new_pkg, 'source');

        $remove_dirs = array_filter(
            $old_dirs,
            function($dir, $ns) use ($new_dirs, $old_pkg) {
                if (!($return = isset($this->assets_dirs[$ns]))) {
                    $this->log(self::LOG_WARNING, sprintf('%s: namespace not defined in root package "%s".', $old_pkg->getName(), $ns));
                } else {
                    $return = (array_search($dir, $new_dirs) === false);
                }

                return $return;
            },
            ARRAY_FILTER_USE_BOTH
        );

        foreach ($remove_dirs as $ns => $dir) {

        }
    }

    /**
     * Handle assets for installed package.
     */
    public function installPackage(PackageEvent $event)
    {
        $operation = $event->getOperation();

        $package = $operation->getPackage();
    }

    /**
     * Install assets for a package.
     */
    public function install(PackageEvent $event)
    {
        $extra = $package->getExtra();

        $source_dirs = $this->getDirectories('source');

        if (count($source_dirs) > 0) {
            // installed/updated package has assets to install
            $package_path = $event->getComposer()->getInstallationManager()->getInstallPath($package);
            $package_name = $package->getName();

            $source_dirs = array_filter($source_dirs, function($dir, $ns) use ($package_path, $package_name) {
                if (!($return = isset($this->assets_dirs[$ns]))) {
                    $this->log(self::LOG_WARNING, sprintf('%s: namespace not defined in root package "%s".', $package_name, $ns));
                } elseif (!($return = is_dir($package_path . '/' . $dir))) {
                    $this->log(self::LOG_WARNING, sprintf('%s: asset directory does not exist "%s".', $package_name, $dir));
                }

                return $return;
            }, ARRAY_FILTER_USE_BOTH);

            foreach ($source_dirs as $ns => $dir) {
                $target_path = $this->root_path . '/' . $this->assets_dirs[$ns] . '/' . $package_name;
                $target_dir = dirname($target_path);

                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0777, true)) {
                        $this->log(self::LOG_ERROR, sprintf('%s: unable to create target directory "%s".', $package_name, $target_dir));
                        continue;
                    }
                }

                $source_path = $package_path . '/' . $dir;

                $this->log(self::LOG_CUSTOM, sprintf('  - Installing asset <info>%s/%s</info>', $package_name, $dir));

                if (is_link($target_path)) {
                    $link_path = readlink($target_path);

                    if ($link_path == $target_path) {
                        continue;
                    }

                    if (!unlink($target_path)) {
                        $this->log(self::LOG_ERROR, '%s: unable to update link to asset "%s" -> "%s".', $package_name, $source_path, $target_path);
                        continue;
                    }
                }

                if (!symlink($source_path, $target_path)) {
                    $this->log(self::LOG_ERROR, sprintf('%s: unable to create link to asset "%s" -> "%s".', $package_name, $source_path, $target_path));
                }
            }
        }

        return $this;
    }

    /**
     * Uninstall assets of a package.
     */
    public function uninstall(PackageEvent $event)
    {
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

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root_path . '/' . $dir));

            foreach ($iterator as $object) {
                if ($object->isLink()) {
                    $target_path = (string)$object;
                    $link_path = readlink($target_path);

                    if (!file_exists($link_path)) {
                        $this->log(self::LOG_CUSTOM, sprintf('  - Removing unresolved path <info>%s</info>', $target_path));
                        if (!unlink($target_path)) {
                            $this->log(self::LOG_ERROR, 'Unable to remove unresolved path "%s" -> "%s".', $link_path, $target_path);
                        }
                    }
                }
            }
        }
    }

    /**
     * Log status messages.
     */
    protected function log($type, $message)
    {
        switch ($type) {
            case self::LOG_INFO:
                $this->io->write(array('<info>' . $message . '</info>'));
                break;
            case self::LOG_WARNING:
                $this->io->write(array('<warning>' . $message . '</warning>'));
                break;
            case self::LOG_ERROR:
                $this->io->write(array('<error>' . $message . '</error>'));
                break;
            case self::LOG_CUSTOM:
                $this->io->write(array($message));
                break;
        }
    }
}
