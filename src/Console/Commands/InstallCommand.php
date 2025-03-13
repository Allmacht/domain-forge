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
    protected $description = 'Instala la estructura básica para domain Forge';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Instalando Domain Forge...');
        $this->info('1. Crear directorio Src si no existe');
        $this->createSrcDirectory();
        $this->info('2. Copiar archivo de configuración');
        $this->publishConfigFile();
        $this->info('3. Crear RepositoryServiceProvider');
        $this->createRepositoryServiceProvider();
        $this->info('Domain Forge ha sido instalado correctamente.');
        $this->info('Puedes generar un nuevo dominio usando: php artisan domain-forge:domain {nombre_dominio}');

        return Command::SUCCESS;
    }

    private function createSrcDirectory()
    {
        if (!File::isDirectory(base_path('src'))) {
            File::makeDirectory(base_path('src'), 0755, true);
            File::makeDirectory(base_path('src/Core'), 0755, true);
            File::makeDirectory(base_path('src/Shared'), 0755, true);
            $this->info('Directorio Src creado correctamente.');
        } else {
            $this->warn('El directorio Src ya existe.');
        }
    }

    private function publishConfigFile()
    {
        if (!File::exists(config_path('domain-forge.php'))) {
            $this->call('vendor:publish', [
                '--provider' => 'Yntech\DomainForge\Providers\DomainForgeServiceProvider',
                '--tag' => 'config'
            ]);
            $this->info('Archivo de configuración publicado correctamente.');
        } else {
            $this->warn('El archivo de configuración ya existe.');
        }
    }

    private function createRepositoryServiceProvider()
    {
        $providerPath = base_path('app/Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            Artisan::call('make:provider RepositoryServiceProvider');
            $this->info('RepositoryServiceProvider creado correctamente.');
        } else {
            $this->warn('RepositoryServiceProvider ya existe.');
        }
    }
}