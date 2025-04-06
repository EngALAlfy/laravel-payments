<?php

namespace EngAlalfy\LaravelPayments;

use EngAlalfy\LaravelPayments\Commands\LaravelPaymentsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPaymentsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-payments')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_payments_table')
            ->hasCommand(LaravelPaymentsCommand::class);
    }
}
