<?php
namespace Lucidity\Composer;

use Composer\Package\Loader\ArrayLoader;

class LaxArrayLoader extends ArrayLoader
{
    public function load(array $config, $class = 'Composer\Package\CompletePackage')
    {
        //Prevent exceptions on missing package information
        $config['name'] = isset($config['name']) ? $config['name'] : 'unknown-package';
        $config['version'] = isset($config['version']) ? $config['version'] : '0.0.1';
        return parent::load($config, $class);
    }
}
