<?php
namespace Lucidity\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Plugin\PluginInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;

class Plugin implements PluginInterface
{
    const MODULE_DIRECTORY_ENV = 'COMPOSER_MODULE_DIRECTORY';
    const DISABLE_LOCAL_MODULES_ENV = 'COMPOSER_DISABLE_LOCAL_MODULES';
    const FEATURE_BRANCH = 'COMPOSER_FEATURE_BRANCH';
    const FEATURE_BRANCH_FALLBACK = 'COMPOSER_FEATURE_BRANCH_FALLBACK';
    const INSTALL_ARTIFACT_PATH = 'COMPOSER_INSTALL_ARTIFACT_PATH';

    protected $versionParser;

    protected $composer;

    protected $io;

    protected $featureBranch;

    protected $featureBranchRepositories;

    protected $featureBranchFallbacks;

    protected $extra = [];



    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->versionParser = new VersionParser();
        $this->composer = $composer;
        $this->io = $io;
        $this->extra = $this->composer->getPackage()->getExtra();
        $this->featureBranch = $this->getConfig(self::FEATURE_BRANCH);
        $this->featureBranchRepositories = $this->getConfig('feature-branch-repositories', []);
        $this->featureBranchFallbacks = $this->getConfig('feature-branch-fallbacks', []);
        $this->featureBranchFallbacks['*'] = $this->getConfig('*', false);
        $this->featureBranchFallbacks['*'] = $this->getConfig(self::FEATURE_BRANCH_FALLBACK, $this->featureBranchFallbacks['*']);
        $this->updateFeatureBranchDependencies();
        $this->registerModuleInstaller();
    }

    private function registerModuleInstaller()
    {
        $module = new ModuleInstaller($this->io, $this->composer, 'lucidity-module', function ($packageName) {
            return 'modules/' . $packageName;
        });
        $enableLocalModules = $this->getConfig(self::DISABLE_LOCAL_MODULES_ENV, false) === false;
        $module->setLocalModuleDirectory($this->getConfig(self::MODULE_DIRECTORY_ENV))
            ->setLocalInstallsAllowed($enableLocalModules)
            ->setInstallArtifactPath($this->getConfig(self::INSTALL_ARTIFACT_PATH));
        $this->composer
            ->getInstallationManager()
            ->addInstaller($module);
    }

    private function updateFeatureBranchDependencies()
    {
        $package = $this->composer->getPackage();
        if ($package->isDev() && $this->featureBranch) {
            $featureBranchConstraint = new Constraint('=', $this->versionParser->normalize($this->featureBranch));
            $featureBranchConstraint->setPrettyString($package->getVersion());
            $requires = $package->getRequires();
            $this->io->write(sprintf("<info>Checking for feature branch '%s' on:</info>\n", $this->featureBranch));
            foreach ($requires as $key => $require) {
                if ($this->isFeatureBranchRepository($require)) {
                    if ($this->hasFeatureBranch($require, $featureBranchConstraint)) {
                        $requires[$key] = new Link(
                            $require->getSource(),
                            $require->getTarget(),
                            $featureBranchConstraint,
                            'requires',
                            $featureBranchConstraint->getPrettyString()
                        );
                    } elseif ($fallbackBranch = $this->getFallbackBranch($require)) {
                        $fallbackConstraint = new Constraint('=', $this->versionParser->normalize($fallbackBranch));
                        $fallbackConstraint->setPrettyString($fallbackBranch);
                        $requires[$key] = new Link(
                            $require->getSource(),
                            $require->getTarget(),
                            $fallbackConstraint,
                            'requires',
                            $fallbackConstraint->getPrettyString()
                        );
                    }
                }
            }
            $package->setRequires($requires);
        }
        $this->composer->setPackage($package);
    }

    private function isFeatureBranchRepository(Link $require)
    {
        return in_array($require->getTarget(), $this->featureBranchRepositories);
    }

    private function hasFeatureBranch(Link $require, Constraint $requiredConstraint)
    {
        if ($this->isFeatureBranchRepository($require)) {
            $this->io->write(sprintf('  - <info>%s</info>', $require->getTarget()), false);
            $package = $this->composer->getRepositoryManager()->findPackage($require->getTarget(), $requiredConstraint);
            if ($package) {
                $this->io->write(" … <info>switching to branch</info>");
                return true;
            } else {
                $this->io->write(" … <warning>branch not found</warning>");
            }
        }
        return false;
    }

    private function getFallbackBranch(Link $require)
    {
        $fallbackBranch = isset($this->featureBranchFallbacks[$require->getTarget()]) ? $this->featureBranchFallbacks[$require->getTarget()] : $this->featureBranchFallbacks['*'];
        if ($this->isFeatureBranchRepository($require) && $fallbackBranch) {
            $this->io->write(sprintf("  - <info>falling back to %s</info>", $fallbackBranch), false);
            $this->io->write('');
            return $fallbackBranch;
        }
        return false;
    }

    private function getConfig($var, $default = null)
    {
        $configKey = strtolower(str_replace(['COMPOSER_', '_'], ['', '-'],$var));
        return getenv($var) ?: (isset($this->extra[$configKey]) ? $this->extra[$configKey] : $default);
    }
}
