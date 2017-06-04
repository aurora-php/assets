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
use Composer\Plugin\PluginInterface;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Assets installer plugin.
 *
 * @copyright   copyright (c) 2017 by Harald Lapp
 * @author      Harald Lapp <harald@octris.org>
 */
class InstallerPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Instance of assets installer.
     */
    protected $installer;

    /**
     * Activate plugin.
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->installer = new Installer($composer, $io);
    }

    /**
     * Subscribe installer to required events.
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_PACKAGE_UPDATE => array(
                array('onPostPackageUpdate', 0)
            ),
            ScriptEvents::POST_PACKAGE_INSTALL => array(
                array('onPostPackageInstall', 0)
            ),
            ScriptEvents::POST_PACKAGE_UNINSTALL => array(
                array('onPostPackageUninstall', 0)
            ),
            ScriptEvents::POST_UPDATE_CMD => array(
                array('onPostInstall', 0)
            ),
            ScriptEvents::POST_INSTALL_CMD => array(
                array('onPostInstall', 0)
            )
        );
    }

    public function onPostPackageUpdate(PackageEvent $event)
    {
        $this->installer->updatePackage($event);
    }

    public function onPostPackageInstall(PackageEvent $event)
    {
        $this->installer->installPackage($event);
    }

    /**
     * Execute uninstaller.
     */
    public function onPostPackageUninstall(PackageEvent $event)
    {
        $this->installer->uninstall($event);
    }

    /**
     * Execute cleanup.
     */
    public function onPostInstall(Event $event)
    {
        $this->installer->cleanup();
    }
}
