<?php

namespace Lucidity\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;

class Plugin implements PluginInterface {
    const MODULE_DIRECTORY_ENV = 'COMPOSER_MODULE_DIRECTORY';
    const DISABLE_LOCAL_MODULES_ENV = 'COMPOSER_DISABLE_LOCAL_MODULES';
    const FEATURE_BRANCH = 'COMPOSER_FEATURE_BRANCH';
    const FEATURE_BRANCH_FALLBACK = 'COMPOSER_FEATURE_BRANCH_FALLBACK';
	

    protected $versionParser;
    protected $composer;
    protected $io;
    protected $featureBranch;
    protected $featureBranchRepositories;
    protected $featureBranchFallbacks;

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
        $extra = $this->composer->getPackage()->getExtra();
        $this->featureBranchRepositories = isset($extra['feature-branch-repositories']) ? $extra['feature-branch-repositories'] : [];
        $this->featureBranchFallbacks = isset($extra['feature-branch-fallbacks']) ? $extra['feature-branch-fallbacks'] : [];
        if ($this->checkFeatureBranch()) {
            $this->updateFeatureBranch();
        }

        if ($this->$featureBranchFallbacks()) {
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

    /* Checking Feature Branch Fallback
     *
     * @return Boolean
     */
    private function checkFeatureBranch() {
        $this->featureBranch = getenv(self::FEATURE_BRANCH_FALLBACK) ? getenv(self::FEATURE_BRANCH_FALLBACK) : null;
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
                if ($this->hasFeatureBranch($require, $featureBranchConstraint)) {
                    $requires[$key] = new Link($require->getSource(), $require->getTarget(), $featureBranchConstraint, 'requires', $featureBranchConstraint->getPrettyString());
                }
				else {
                    $fallbackBranch = $this->getFallbackBranch($require);
                    if ($fallbackBranch !== false) {
                        $fallbackConstraint = new Constraint('=', $this->versionParser->normalize($fallbackBranch));
                        $fallbackConstraint->setPrettyString($fallbackBranch);
                        $requires[$key] = new Link($require->getSource(), $require->getTarget(), $fallbackConstraint, 'requires', $fallbackConstraint->getPrettyString());
                    }
                }
                $this->io->write('');
            }
            $package->setRequires($requires);
        }
        $this->composer->setPackage($package);
    }
    /* Checking Feature Branch repository
    *
    * @return Boolean
    */
    private function isFeatureBranchRepository(Link $require){
        return in_array($require->getTarget(), $this->featureBranchRepositories);
    }

    /* Checking fature  Branch  fallback repository
    *
    *@return Boolean
    */
	private function hasFallbackBranch(Link $require){
        return isset($this->featureBranchFallbacks[$require->getTarget()]);
    }

    /** Check Repository has Feature Branch
     *
     * @return Boolean
     */
    private function hasFeatureBranch(Link $require, Constraint $requiredConstraint) {
        if ($this->isFeatureBranchRepository($require)) {
            $this->io->write(sprintf('<info>%s</info>', $require->getTarget()), false);
            $package = $this->composer->getRepositoryManager()->findPackage($require->getTarget(), $requiredConstraint);
            if ($package) {
                $this->io->write(" - <info>Switching to branch</info>", false);
                return true;
            } else {
                $this->io->write(" - <warning>Branch not found</warning>", false);
            }
        }
        return false;
    }

    private function getFallbackBranch(Link $require){
        if ($this->isFeatureBranchRepository($require)) {
            if ($this->hasFallbackBranch($require)) {
                $fallbackBranch = $this->featureBranchFallbacks[$require->getTarget()];
                $this->io->write(sprintf("<info> - falling back to %s</info>", $fallbackBranch), false);
                return $fallbackBranch;
            }
        }
        return false;
    }
}