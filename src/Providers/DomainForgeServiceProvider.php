<?php

namespace Yntech\DomainForge\Providers;

use Illuminate\Support\ServiceProvider;
use Yntech\DomainForge\Console\Commands\CreateModuleCommand;
use Yntech\DomainForge\Console\Commands\InstallCommand;
use Yntech\DomainForge\Generators\DirectoryStructureGenerator;
use Yntech\DomainForge\Generators\EntityGenerator;
use Yntech\DomainForge\Generators\EnumGenerator;
use Yntech\DomainForge\Generators\MapperGenerator;
use Yntech\DomainForge\Generators\ModuleGenerator;
use Yntech\DomainForge\Generators\RepositoryGenerator;
use Yntech\DomainForge\Generators\RoutesGenerator;
use Yntech\DomainForge\Generators\ServiceProviderGenerator;
use Yntech\DomainForge\Generators\ValueObjectGenerator;
use Yntech\DomainForge\Support\PropertyParser;
use Yntech\DomainForge\Generators\MigrationGenerator;
use Yntech\DomainForge\Generators\ModelCustomizer;

class DomainForgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/domain-forge.php',
            'domain-forge'
        );

        // Support
        $this->app->singleton(PropertyParser::class);

        // Generators
        $this->app->singleton(DirectoryStructureGenerator::class);
        $this->app->singleton(EnumGenerator::class);
        $this->app->singleton(ValueObjectGenerator::class);
        $this->app->singleton(EntityGenerator::class);
        $this->app->singleton(MapperGenerator::class);
        $this->app->singleton(RepositoryGenerator::class);
        $this->app->singleton(ServiceProviderGenerator::class);
        $this->app->singleton(RoutesGenerator::class);
        $this->app->singleton(MigrationGenerator::class);
        $this->app->singleton(ModelCustomizer::class);

        // Orchestrator
        $this->app->singleton(ModuleGenerator::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/domain-forge.php' => config_path('domain-forge.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreateModuleCommand::class,
            ]);
        }
    }
}
