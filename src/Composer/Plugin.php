<?php
namespace Lucidity\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    const MODULE_DIRECTORY_ENV = 'COMPOSER_MODULE_DIRECTORY';
    const DISABLE_LOCAL_MODULES_ENV = 'COMPOSER_DISABLE_LOCAL_MODULES';

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $module = new ModuleInstaller(
            $io,
            $composer,
            'lucidity-module',
            function ($packageName) {
                return 'modules/' . $packageName;
            }
        );
        $enableLocalModules = getenv(self::DISABLE_LOCAL_MODULES_ENV) === false;
        $module->setLocalModuleDirectory(getenv(self::MODULE_DIRECTORY_ENV) ?: null)
            ->setLocalInstallsAllowed($enableLocalModules);
        $composer->getInstallationManager()->addInstaller($module);
    }
}
