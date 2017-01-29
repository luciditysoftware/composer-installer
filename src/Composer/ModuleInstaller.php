<?php
namespace Lucidity\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use InvalidArgumentException;

class ModuleInstaller extends LibraryInstaller
{
    public function supports($packageType)
    {
        return $packageType === 'lucidity-module';
    }

    public function getInstallPath(PackageInterface $package)
    {
        $prefix = substr($package->getPrettyName(), 0, 17);
        if ('lucidity/module-' !== $prefix) {
            throw new InvalidArgumentException("Not a valid Lucidity module; package must be prefixed with 'lucidity/module-'");
        }
        return 'modules/' . substr($package->getPrettyName(), 23);
    }
}
