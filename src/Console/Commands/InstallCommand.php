<?php

namespace Yntech\DomainForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Filesystem\Filesystem;
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

	protected $filesystem;

	public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

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
        $this->createLaravelTransactionManager();
        $this->registerTransactionManager();
        $this->info('Domain Forge installed successfully.');
        $this->info('You can generate a new domain using: php artisan domain-forge:domain {domain_name} --props={props}');

        return Command::SUCCESS;
    }

    protected function createSrcDirectory()
    {
        $paths = [
            'src',
            'src/Core',
            'src/Shared',
            'src/Shared/Application',
            'src/Shared/Domain',
            'src/Shared/Domain/Contracts',
            'src/Shared/Infrastructure',
            'src/Shared/Infrastructure/Persistence',
            'src/Shared/Infrastructure/Persistence/Eloquent',
        ];

        if (! File::isDirectory(base_path('src'))) {

            foreach ($paths as $path) {
                File::makeDirectory(base_path($path), 0755, true);
            }

            $this->info('Directory Src created successfully.');

        } else {
            $this->warn('Directory Src already exists.');
        }
    }

	protected function createLaravelTransactionManager()
    {
        $files = [
            "src/Shared/Domain/Contracts/TransactionManagerInterface.php" => $this->interfaceStub(),
            "src/Shared/Infrastructure/Persistence/Eloquent/LaravelTransactionManager.php" => $this->laravelTransactionManagerStub()
        ];

        foreach ($files as $path => $stub) {
            $this->filesystem->put($path, $stub);
        }
    }

    protected function publishConfigFile()
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

    protected function createRepositoryServiceProvider()
    {
        $providerPath = base_path('app/Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            Artisan::call('make:provider RepositoryServiceProvider');
            $this->info('RepositoryServiceProvider created successfully.');
        } else {
            $this->warn('RepositoryServiceProvider already exists.');
        }
    }

    protected function registerTransactionManager()
    {
        $providerPath = base_path('app/Providers/RepositoryServiceProvider.php');

        if (! File::exists($providerPath)) {
            $this->warn('RepositoryServiceProvider.php does not exist. Skipping binding.');

            return;
        }

        $content = File::get($providerPath);

        $namespacePattern = '/^namespace\s+([a-zA-Z0-9\\\\]+);/m';

        $useStatementInterface = 'use Src\\Shared\\Domain\\Contracts\\TransactionManagerInterface;';
        $useStatementImplementation = 'use Src\\Shared\\Infrastructure\\Persistence\\Eloquent\\LaravelTransactionManager;';

        $needsInterfaceUse = ! str_contains($content, $useStatementInterface);
        $needsImplementationUse = ! str_contains($content, $useStatementImplementation);

        if ($needsInterfaceUse || $needsImplementationUse) {
            $content = preg_replace_callback($namespacePattern, function ($matches) use ($useStatementInterface, $useStatementImplementation, $needsInterfaceUse, $needsImplementationUse) {
                $inserts = $matches[0]."\n\n";
                if ($needsInterfaceUse) {
                    $inserts .= $useStatementInterface."\n";
                }
                if ($needsImplementationUse) {
                    $inserts .= $useStatementImplementation."\n";
                }

                return $inserts;
            }, $content);
        }

        $registerPattern = '/public function register\(\): void\s*\{\n/';
        $bindStatement = "        \$this->app->bind(TransactionManagerInterface::class, LaravelTransactionManager::class);\n";

        if (! str_contains($content, 'bind(TransactionManagerInterface::class')) {
            $content = preg_replace($registerPattern, "$0$bindStatement", $content, 1);
        }

        $content = preg_replace('/\s*\/\/\s*$/', '', $content);

        File::put($providerPath, $content);

        $this->info('TransactionManager registered successfully in RepositoryServiceProvider.');
    }

    protected function interfaceStub()
    {
        return sprintf(
            <<<'STUB'
            <?php

            namespace Src\Shared\Domain\Contracts;

            interface TransactionManagerInterface
            {
                public function beginTransaction(): void;

                public function commit(): void;

                public function rollback(): void;

                public function transaction(callable $callback): mixed;
            }
            STUB,
        );
    }

    protected function laravelTransactionManagerStub()
    {
        return sprintf(
            <<<'STUB'
            <?php

            namespace Src\Shared\Infrastructure\Persistence\Eloquent;

            use Illuminate\Support\Facades\DB;
            use Src\Shared\Domain\Contracts\TransactionManagerInterface;

            class LaravelTransactionManager implements TransactionManagerInterface
            {
                public function beginTransaction(): void
                {
                    DB::beginTransaction();
                }

                public function commit(): void
                {
                    DB::commit();
                }

                public function rollback(): void
                {
                    DB::rollBack();
                }

                public function transaction(callable $callback): mixed
                {
                    return DB::transaction($callback);
                }
            }
            STUB
        );
    }
}