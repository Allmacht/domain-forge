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
        // Publicar archivos de configuración
        $this->publishes([
            __DIR__ . '/../../config/domain-forge.php' => config_path('domain-forge.php'),
        ], 'config');

        // Registrar comandos
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
        // Fusionar configuraciones
        $this->mergeConfigFrom(
            __DIR__.'/../../config/domain-forge.php', 'domain-forge'
        );
    }
}