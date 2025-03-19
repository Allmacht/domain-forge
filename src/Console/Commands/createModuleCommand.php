<?php

namespace Yntech\DomainForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class createModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain-forge:domain {name} {--props=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new domain module';

    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = Str::studly($this->argument('name'));

        $props = $this->option('props');

        $result_props = null;

        if (! is_null($props)) {

            $propsArray = explode(',', $props);
            $result_props = [];

            foreach ($propsArray as $prop) {
                [$key, $type] = explode(':', $prop);
                $result_props[$key] = $type;
            }
        }

        $paths = [
            "src/Core/{$name}/Application",
            "src/Core/{$name}/Application/DTOs",
            "src/Core/{$name}/Application/Services",
            "src/Core/{$name}/Application/UseCases",
            "src/Core/{$name}/Domain",
            "src/Core/{$name}/Domain/Entities",
            "src/Core/{$name}/Domain/Contracts",
            "src/Core/{$name}/Domain/ValueObjects",
            "src/Core/{$name}/Domain/ValueObjects/{$name}",
            "src/Core/{$name}/Infrastructure",
            "src/Core/{$name}/Infrastructure/Http",
            "src/Core/{$name}/Infrastructure/Http/Controllers",
            "src/Core/{$name}/Infrastructure/Http/Requests",
            "src/Core/{$name}/Infrastructure/Http/Resources",
            "src/Core/{$name}/Infrastructure/Http/Routes",
            "src/Core/{$name}/Infrastructure/Persistence",
            "src/Core/{$name}/Infrastructure/Persistence/Repositories",
            "src/Core/{$name}/Infrastructure/Persistence/Repositories/Eloquent",
        ];

        $this->info("Creating domain module: {$name}");

        $this->createLaravelModel(name: $name);

        foreach ($paths as $path) {
            $this->filesystem->makeDirectory($path, 0755, true, true);
            $this->info("Created directory: {$path}");
        }

        if (!is_null($props)) {

            foreach ($result_props as $propName => $propType) {

                $valueObjectPath = "src/Core/{$name}/Domain/ValueObjects/{$name}/{$name}" . Str::studly($propName) . ".php";
    
                $this->filesystem->put($valueObjectPath, $this->valueObjectStub($name, $propName, $propType));
    
                $this->info("Created ValueObject: {$valueObjectPath}");
            }
        }

        $this->filesystem->put("{$paths[5]}/{$name}.php", $this->domainStub(name: $name, result_props: $result_props));
        
        $this->filesystem->put("{$paths[6]}/{$name}RepositoryContract.php", $this->interfaceStub(name: $name));

        $this->filesystem->put("{$paths[17]}/{$name}Repository.php", $this->RepositoryStub(name: $name));

        $this->registerRepository(name: $name);

        $this->newLine(2);
        
        $this->info("Domain {$name} created successfully.");
    }

    protected function createLaravelModel(string $name): void
    {
        Artisan::call('make:model '.$name.' -m');
    }

    protected function domainStub(string $name, array|null $result_props): string
    {
        if (is_null($result_props)) {
            return sprintf(
                <<<'STUB'
                <?php
    
                namespace Src\Core\%s\Domain\Entities;
    
                final class %s
                {
                    public function __construct() {}
    
                    public static function create(): static {
                        return new self();
                    }
                }
                STUB,
                $name,
                $name
            );
        }
        
        $imports = array_map(
            fn($prop) => "use Src\\Core\\{$name}\\Domain\\ValueObjects\\{$name}\\{$name}" . Str::studly($prop) . ";",
            array_keys($result_props)
        );
    
        $constructorProps = array_map(
            fn($prop, $type) => "        public readonly {$name}" . Str::studly($prop) . " \${$prop},",
            array_keys($result_props),
            $result_props
        );

        return sprintf(
            <<<'STUB'
            <?php
    
            namespace Src\Core\%s\Domain\Entities;
    
            %s
    
            final class %s
            {
                private function __construct(
            %s
                ) {}
    
                public static function create(
            %s
                ): static {
                    return new self(
            %s
                    );
                }
            }
            STUB,
            $name,
            implode("\n", $imports),
            $name,
            implode("\n", $constructorProps),
            implode("\n", str_replace('public readonly ', '', $constructorProps)),
            implode("\n", array_map(fn($prop) => "          {$prop}: \${$prop},", array_keys($result_props)))
        );
    }

    protected function interfaceStub(string $name): string
    {
        return sprintf(
            <<<'STUB'
            <?php
    
            namespace Src\Core\%s\Domain\Contracts;
    
            interface %sRepositoryContract
            {
                //
            }
            STUB,
            $name,
            $name
        );
    }

    protected function RepositoryStub(string $name): string
    {
        return sprintf(
            <<<'STUB'
            <?php
    
            namespace Src\Core\%s\Infrastructure\Persistence\Repositories\Eloquent;
    
            use Src\Core\%s\Domain\Contracts\%sRepositoryContract;
            use Src\Core\%s\Domain\Entities\%s;
    
            class %sRepository implements %sRepositoryContract
            {
                
            }
            STUB,
            $name,
            $name,
            $name,
            $name,
            $name,
            $name,
            $name,
            $name
        );
    }

    protected function valueObjectStub(string $name, string $propName, string $propType): string
    {
        return sprintf(
            <<<'STUB'
            <?php
    
            namespace Src\Core\%s\Domain\ValueObjects\%s;
    
            final class %s
            {
                public function __construct(private %s $value)
                {
                    $this->validate(value: $value);
                }

                private function validate(%s $value): void
                {
                    
                }

                public function value(): %s
                {
                    return $this->value;
                }
            }
            STUB,
            $name,
            $name,
            $name.Str::studly($propName),
            $propType,
            $propType,
            $propType
        );
    }

    protected function registerRepository(string $name)
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->error('The provider file does not exist.');
            return 1;
        }

        $content = File::get($providerPath);

        $contractImport = "use Src\\Core\\{$name}\\Domain\\Contracts\\{$name}RepositoryContract;";
        $repositoryImport = "use Src\\Core\\{$name}\\Infrastructure\\Persistence\\Repositories\\Eloquent\\{$name}Repository;";

        $hasInterfaceUse = str_contains($content, $contractImport);
        $hasImplementationUse = str_contains($content, $repositoryImport);

        if (!$hasInterfaceUse || !$hasImplementationUse) {
            if (preg_match('/namespace App\\\\Providers;(\s*)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1] + strlen($matches[0][0]);
                
                $useStatements = "\n";
                if (!$hasInterfaceUse) {
                    $useStatements .= $contractImport . "\n";
                }
                if (!$hasImplementationUse) {
                    $useStatements .= $repositoryImport . "\n";
                }
                
                $content = substr_replace($content, $useStatements, $position, 0);
            }
        }
        

        $bindCode = "\$this->app->bind({$name}RepositoryContract::class, {$name}Repository::class);";

        if (!str_contains($content, $bindCode)) {
            
            if (preg_match('/public function register\(\): void\s+{\s+(.+?)\s+}/s', $content, $matches)) {
                
                $currentContent = $matches[1];
                $newContent = trim($currentContent) . "\n        {$bindCode}";
                $content = preg_replace(
                    '/public function register\(\): void\s+{\s+(.+?)\s+}/s',
                    "public function register(): void\n    {\n        {$newContent}\n    }",
                    $content
                );
            } else {
                
                $content = preg_replace(
                    '/public function register\(\): void\s+{\s+\/\/\s+}/s',
                    "public function register(): void\n    {\n        {$bindCode}\n    }",
                    $content
                );
                
                
                if (!str_contains($content, $bindCode)) {
                    $content = preg_replace(
                        '/public function register\(\): void\s+{\s*}/s',
                        "public function register(): void\n    {\n        {$bindCode}\n    }",
                        $content
                    );
                }
            }
        }

        File::put($providerPath, $content);

        $this->info("Repository {$name} registered successfully.");
        return 0;
    }
}
