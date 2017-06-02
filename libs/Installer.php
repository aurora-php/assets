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
use Composer\Json\JsonFile;
use Composer\IO\IOInterface;
use Composer\Package;

/**
 * Assets installer.
 *
 * @copyright   copyright (c) 2017 by Harald Lapp
 * @author      Harald Lapp <harald@octris.org>
 */
class Installer implements PluginInterface
{
    /**
     * Namespace of extra field of composer.json for asset configuration.
     */
    const NS_EXTRA = 'octris/assets';

    /**
     * Default assets namespace.
     */
    const NS_ASSETS = 'assets';

    /**
     * Assets directories.
     */
    protected $assets_dirs = array();

    /**
     * Constructor.
     */
    public function __construct($composer, $io)
    {
        $this->package = $composer->getPackage();
        $extra = $this->package->getExtra();

        if (isset($extra[self::NS_EXTRA]) && isset($extra[self::NS_EXTRA]['target'])) {
            $this->assets_dir = (is_array($extra[self::NS_EXTRA]['target'])
                                    ? $extra[self::NS_EXTRA]['target']
                                    : array(self::NS_ASSETS => $extra[self::NS_EXTRA]['target']));
        }
    }
}
