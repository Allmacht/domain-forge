<?php

namespace Yntech\DomainForge\Providers;

use Illuminate\Support\ServiceProvider;
use Yntech\DomainForge\Console\Commands\InstallCommand;
use Yntech\DomainForge\Console\Commands\createModuleCommand;

class DomainForgeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/domain-forge.php' => config_path('domain-forge.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                createModuleCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/domain-forge.php', 'domain-forge'
        );
    }
}