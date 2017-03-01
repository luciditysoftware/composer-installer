<?php

namespace Lucidity\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Plugin\PluginInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;

class Plugin implements PluginInterface {

    const MODULE_DIRECTORY_ENV = 'COMPOSER_MODULE_DIRECTORY';
    const DISABLE_LOCAL_MODULES_ENV = 'COMPOSER_DISABLE_LOCAL_MODULES';
    const FEATURE_BRANCH = 'COMPOSER_FEATURE_BRANCH';

    protected $versionParser;
    protected $composer;
    protected $io;
    protected $featureBranch;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io) {
        $this->versionParser = new VersionParser();
        $this->composer = $composer;
        $this->io = $io;
        if ($this->checkFeatureBranch()) {
            $this->updateFeatureBranch();
        }
        $module = new ModuleInstaller($this->io, $this->composer, 'lucidity-module', function ($packageName) {
            return 'modules/' . $packageName;
        });
        $enableLocalModules = getenv(self::DISABLE_LOCAL_MODULES_ENV) === false;
        $module->setLocalModuleDirectory(getenv(self::MODULE_DIRECTORY_ENV) ? : null)
                ->setLocalInstallsAllowed($enableLocalModules);
        $this->composer->getInstallationManager()->addInstaller($module);
    }

    /* Checking Feature Branch 
     * 
     * @return Boolean
     */

    private function checkFeatureBranch() {
        $this->featureBranch = getenv(self::FEATURE_BRANCH) ? getenv(self::FEATURE_BRANCH) : null;
        return !is_null($this->featureBranch);
    }

    /* Updating Feature Branch */

    private function updateFeatureBranch() {
        $package = $this->composer->getPackage();
        if ($package->isDev()) {
            $featureBranchConstraint = new Constraint('=', $this->versionParser->normalize($this->featureBranch));
            $featureBranchConstraint->setPrettyString($package->getVersion());
            $requires = $package->getRequires();
            $this->io->write(sprintf("<info>Checking for feature branch '%s'</info>", $this->featureBranch));
            foreach ($requires as $key => $require) {
				if (strpos($require->getTarget(), 'luciditysoftware') !== false) {
					if ($this->hasFeatureBranch($require, $featureBranchConstraint)) {
						$requires[$key] = new Link($require->getSource(), $require->getTarget(), $featureBranchConstraint, 'requires', $featureBranchConstraint->getPrettyString());
					}
				}
				$this->io->write('');
            }
            $package->setRequires($requires);
        }
        $this->composer->setPackage($package);
    }

    /** Check Repository has Feature Branch 
     * 
     * @return Boolean
     */
    private function hasFeatureBranch(Link $require, Constraint $requiredConstraint) {
        $this->io->write(sprintf('<info>%s</info>', $require->getTarget()), false);
        $package = $this->composer->getRepositoryManager()->findPackage($require->getTarget(), $requiredConstraint);
        if ($package) {
            $this->io->write(" - <info>switching to branch</info>", false);
            return true;
        } else {
            $this->io->write(" - <warning>branch not found</warning>", false);
            return false;
        }
    }

}