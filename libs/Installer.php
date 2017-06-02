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

        if (isset($extra[self::NS_EXTRA]) && isset($extra[self::NS_EXTRA]['target'])) {
            $this->assets_dirs = (is_array($extra[self::NS_EXTRA]['target'])
                                    ? $extra[self::NS_EXTRA]['target']
                                    : array(self::NS_ASSETS => $extra[self::NS_EXTRA]['target']));
        }

        var_dump([$target_dir, $this->assets_dirs]);
    }
    
    /**
     * Install assets for a package.
     */
    public function install(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        $extra = $package->getExtra();
        
        if (isset($extra[self::NS_EXTRA]) && isset($extra[self::NS_EXTRA]['source'])) {
            // installed/updated package has assets to install
            $package_path = $event->getComposer()->getInstallationManager()->getInstallPath($package);
            $package_name = $package->getName();

            $source_dirs = (is_array($extra[self::NS_EXTRA]['source'])
                                ? $extra[self::NS_EXTRA]['source']
                                : array(self::NS_ASSETS => $extra[self::NS_EXTRA]['source']));

            $source_dirs = array_filter($source_dirs, function($dir, $ns) use ($package_path) {
                if (!($return = isset($this->assets_dirs[$ns]))) {
                    $this->log(self::LOG_WARNING, sprintf('%s: namespace not defined in root package "%s".', $package_name, $ns));
                } elseif (!($return = is_dir($package_path . '/' . $dir))) {
                    $this->log(self::LOG_WARNING, sprintf('%s: asset directory does not exist "%s".', $package_name, $dir));
                }
                
                return $return;
            }, ARRAY_FILTER_USE_BOTH);

            foreach ($source_dirs as $ns => $dir) {
                // $target_dir =
                //
                // if (!mkdir()
            }

            var_dump($source_dirs);
        }
        
        return $this;
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
        }
    }
}
