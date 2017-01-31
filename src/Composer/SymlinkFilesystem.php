<?php
namespace Lucidity\Composer;

use Composer\Util\Filesystem;

class SymlinkFilesystem extends Filesystem
{
    /**
     * @param $sourcePath
     * @param $symlinkPath
     *
     * @return bool
     */
    public function ensureSymlinkExists($sourcePath, $symlinkPath)
    {
        if (!is_link($symlinkPath)) {
            $this->ensureDirectoryExists(dirname($symlinkPath));

            return symlink($sourcePath, $symlinkPath);
        }

        return false;
    }





}
