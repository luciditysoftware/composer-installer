# composer-installer

## Usage
The composer-installer plugin will run automatically when setup within a module project.

At present there is one supported type, lucidity-module. In future there may be additional module types 

### lucidity-module
lucidity-module modules are installed within the top-level of your project, within the `modules` folder. 

To setup up your module to be installed using the lucidity-module format, the following details need to be added to your `composer.json`

```
{
    "type": "lucidity-module",
    "require": {
        "luciditysoftware/lucidity-composer-installer": "1.0.0"
     }
}

```

## Local Development
Out of the box the plugin allows local development. By default, composer-installer will look for matching packages in the directory above your working directory (e.g. /workspace/web would scan the /workspace directory for matching packages).

If you wish to use a different directory, you can supply that using the environment variable `COMPOSER_MODULE_DIRECTORY`, either within your .bash_profile or on the cli at runtime. Additionally, you can disable by setting the `COMPOSER_DISABLE_LOCAL_MODULES=true` environment variable.

## Feature Branches
Feature branches can be specified at runtime. This is useful in situations such as continuous integration / testing where you might want to install dependencies for feature branches. 

The desired feature branch can be specified at runtime using `COMPOSER_FEATURE_BRANCH=feature-branch-name`. The composer-installer plugin will install that version of lucidity-module dependencies where found, or fallback to the locked version in the composer.json.
To enable the feature branch functionality, the applicable dependencies must be specified within the `"extra"` section of the composer.json file, under `feature-branch-repositories`

A fallback branch can be specified using the env variable `COMPOSER_FEATURE_BRANCH_FALLBACK=dev-fallback`. Additionally, fallback branches can also be specified within the `"extra"` section of the composer.json file under `feature-branch-fallbacks`. 

### Example composer.json


```
{
    "extra": {
        "feature-branch-repositories": [
            "luciditysoftware/module-a",
            "luciditysoftware/module-b",
            "luciditysoftware/module-c"
        ],
        "feature-branch-fallbacks": {
            "*": "dev-master", //Wildcard to set default fallback branch
            "luciditysoftware/module-c": "dev-different-branch"
        }
      
    }
}

```
