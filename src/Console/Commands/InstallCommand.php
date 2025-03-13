<?php

namespace Yntech\DomainForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain-forge:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Domain Forge';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Installing Domain Forge...');
        $this->info('Creating Src directory...');
        $this->createSrcDirectory();
        $this->info('Creating config file...');
        $this->publishConfigFile();
        $this->info('Creating Repository Service Provider...');
        $this->createRepositoryServiceProvider();
        $this->info('Domain Forge installed successfully.');
        $this->info('You can generate a new domain using: php artisan domain-forge:domain {domain_name}');

        return Command::SUCCESS;
    }

    private function createSrcDirectory()
    {
        if (!File::isDirectory(base_path('src'))) {
            File::makeDirectory(base_path('src'), 0755, true);
            File::makeDirectory(base_path('src/Core'), 0755, true);
            File::makeDirectory(base_path('src/Shared'), 0755, true);
            $this->info('Directory Src created successfully.');
        } else {
            $this->warn('Directory Src already exists.');
        }
    }

    private function publishConfigFile()
    {
        if (!File::exists(config_path('domain-forge.php'))) {
            $this->call('vendor:publish', [
                '--provider' => 'Yntech\DomainForge\Providers\DomainForgeServiceProvider',
                '--tag' => 'config'
            ]);
            $this->info('Config file created successfully.');
        } else {
            $this->warn('Config file already exists.');
        }
    }

    private function createRepositoryServiceProvider()
    {
        $providerPath = base_path('app/Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            Artisan::call('make:provider RepositoryServiceProvider');
            $this->info('RepositoryServiceProvider created successfully.');
        } else {
            $this->warn('RepositoryServiceProvider already exists.');
        }
    }
}