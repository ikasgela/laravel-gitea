<?php

namespace Ikasgela\Gitea;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ikasgela\Gitea\Commands\GiteaCommand;

class GiteaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-gitea')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-gitea_table')
            ->hasCommand(GiteaCommand::class);
    }
}
