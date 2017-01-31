<?php
namespace Lucidity\Composer;

use CallbackFilterIterator;
use Closure;
use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\Loader\JsonLoader;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use DirectoryIterator;
use InvalidArgumentException;

class ModuleInstaller extends LibraryInstaller
{
    /**
     * @var null|string
     */
    protected $localModuleDirectory = null;

    /**
     * @var bool
     */
    protected $localInstallsAllowed = true;

    /**
     * @var string
     */
    protected $supportedPackageType;

    /**
     * @var Closure
     */
    protected $installPathResolver;

    /**
     * @var JsonLoader
     */
    protected $composerLoader;

    /**
     * ModuleInstaller constructor.
     *
     * @param IOInterface $io
     * @param Composer    $composer
     * @param string      $supportedPackageType
     * @param Closure     $installPathResolver
     */
    public function __construct(IOInterface $io, Composer $composer, $supportedPackageType, Closure $installPathResolver)
    {
        $this->supportedPackageType = $supportedPackageType;
        $this->installPathResolver = $installPathResolver;
        parent::__construct($io, $composer, 'library', new SymlinkFilesystem());
        $this->setLocalModuleDirectory();
        $this->composerLoader = new JsonLoader(new LaxArrayLoader());
    }

    /**
     * @param null $directory
     *
     * @return $this
     */
    public function setLocalModuleDirectory($directory = null)
    {
        if ($directory !== null) {
            $this->localModuleDirectory = $directory;
        } else {
            $this->localModuleDirectory = realpath($this->composer->getConfig()->get('vendor-dir') . '/../../');
        }
        return $this;
    }

    /**
     * @param $allowed
     *
     * @return $this
     */
    public function setLocalInstallsAllowed($allowed)
    {
        $this->localInstallsAllowed = $allowed;
        return $this;
    }

    /**
     * @param string $packageType
     *
     * @return bool
     */
    public function supports($packageType)
    {
        return $packageType === $this->supportedPackageType;
    }

    /**
     * @param PackageInterface $package
     *
     * @return mixed
     */
    public function getInstallPath(PackageInterface $package)
    {
        return call_user_func($this->installPathResolver, $this->getPackageName($package));
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($this->localPackageExists($package)) {
            $this->filesystem->removeDirectory($this->getInstallPath($package));
            $this->filesystem->ensureSymlinkExists($this->localPackagePath($package), $this->getInstallPath($package));
            $this->io->writeError(' - Linking <info>' . $package->getName() . '</info> from <info>' . $this->localPackagePath($package) . '</info>');
            if (!$repo->hasPackage($package)) {
                $repo->addPackage(clone $package);
            }
        } else {
            parent::install($repo, $package);
        }
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $initial
     * @param PackageInterface             $target
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        if ($this->localPackageExists($target)) {
            $this->install($repo, $initial);
        } else {
            parent::update($repo, $initial, $target);
        }
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($this->localPackageExists($package)) {
            return $this->localPackageInstalled($package);
        }
        return parent::isInstalled($repo, $package);
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($this->localPackageExists($package) && $this->localPackageInstalled($package)) {
            $this->filesystem->removeDirectory($this->getInstallPath($package));
        } else {
            parent::uninstall($repo, $package);
        }
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool
     */
    private function localPackageInstalled(PackageInterface $package)
    {
        return $this->filesystem->isSymlinkedDirectory($this->getInstallPath($package));
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool
     */
    private function localPackageExists(PackageInterface $package)
    {
        return $this->localInstallsAllowed && is_dir($this->localPackagePath($package));
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     */
    private function getPackageName(PackageInterface $package)
    {
        return substr($package->getPrettyName(), strpos($package->getPrettyName(), '/') + 1);
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool|mixed
     */
    private function localPackagePath(PackageInterface $package)
    {
        $matchingPackages = new CallbackFilterIterator(
            new DirectoryIterator($this->localModuleDirectory),
            function (DirectoryIterator $fileInfo) use ($package) {
                if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                    return $package->getName() === $this->getLocalPackage($fileInfo->getRealPath())->getName();
                }
                return false;
            }
        );
        $numberOfMatches = iterator_count($matchingPackages);
        if ($numberOfMatches > 1) {
            throw new InvalidArgumentException("More than 1 instance of package {$package->getName()} found within {$this->localModuleDirectory}");
        } else if ($numberOfMatches === 1) {
            $matchingPackages->rewind();
            return $matchingPackages->current()->getRealPath();
        }
        return false;
    }

    /**
     * @param $path
     *
     * @return PackageInterface
     */
    private function getLocalPackage($path)
    {
        $composerJson = $path . "/composer.json";
        if (file_exists($composerJson)) {
            return $this->composerLoader->load($composerJson);
        }
        return new Package('', '', '0.0.1');
    }
}
