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

* /**
 * Assets installer.
 *
 * @copyright   copyright (c) 2017 by Harald Lapp
 * @author      Harald Lapp <harald@octris.org>
 */
class Installer implements PluginInterface
{
    const LABEL_ASSETS = 'octris:assets';
    
    /**
     * Constructor.
     */
    public function __construct($composer, $io)
    {
    }
}
