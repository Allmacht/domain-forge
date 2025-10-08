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
                if (strpos($prop, ':') === false) {
                    $this->warn("Propiedad '{$prop}' no tiene el formato correcto (debe ser nombre:tipo).");
                    continue;
                }
                [$key, $type] = explode(':', $prop, 2);
                $result_props[$key] = $type;
            }
        }

        $paths = [
            "src/Contexts/{$name}/Application", #0
            "src/Contexts/{$name}/Application/Commands", #1
            "src/Contexts/{$name}/Application/Handlers", #2
            "src/Contexts/{$name}/Application/DTOs", #3
            "src/Contexts/{$name}/Application/Services", #4
            "src/Contexts/{$name}/Application/UseCases", #5

            "src/Contexts/{$name}/Domain", #6
            "src/Contexts/{$name}/Domain/Entities", #7
            "src/Contexts/{$name}/Domain/Contracts", #8
            "src/Contexts/{$name}/Domain/Exceptions", #9
            "src/Contexts/{$name}/Domain/ValueObjects", #10
            
            "src/Contexts/{$name}/Infrastructure", #11
            "src/Contexts/{$name}/Infrastructure/Http", #12
            "src/Contexts/{$name}/Infrastructure/Http/Controllers", #13
            "src/Contexts/{$name}/Infrastructure/Http/Requests", #14
            "src/Contexts/{$name}/Infrastructure/Http/Resources", #15
            "src/Contexts/{$name}/Infrastructure/Http/Routes", #16
            "src/Contexts/{$name}/Infrastructure/Persistence", #17
            "src/Contexts/{$name}/Infrastructure/Persistence/Mappers", #18
            "src/Contexts/{$name}/Infrastructure/Persistence/Repositories", #19
            "src/Contexts/{$name}/Infrastructure/Persistence/Repositories/Eloquent", #20
        ];

        $this->info("Creating domain module: {$name}");

        $this->createLaravelModel(name: $name);

        foreach ($paths as $path) {
            $this->filesystem->makeDirectory($path, 0755, true, true);
            $this->info("Created directory: {$path}");
        }

        if (! is_null($props)) {

            foreach ($result_props as $propName => $propType) {

                $valueObjectPath = "src/Contexts/{$name}/Domain/ValueObjects/{$name}" . Str::studly($propName) . ".php";
    
                $this->filesystem->put($valueObjectPath, $this->valueObjectStub($name, $propName, $propType));
    
                $this->info("Created ValueObject: {$valueObjectPath}");
            }
        }

        $this->filesystem->put("{$paths[7]}/{$name}.php", $this->domainStub(name: $name, result_props: $result_props));
        
        $this->filesystem->put("{$paths[8]}/{$name}RepositoryContract.php", $this->interfaceStub(name: $name));

        $this->filesystem->put("{$paths[20]}/{$name}Repository.php", $this->RepositoryStub(name: $name));

        $this->filesystem->put("{$paths[16]}/{$name}.php", $this->routeStub(name: $name));

        $this->filesystem->put("{$paths[11]}/{$name}ServiceProvider.php", $this->providerStub(name: $name));

        $this->registerProvider(name: $name);

        $this->newLine(2);
        
        $this->info("Domain {$name} created successfully.");
    }

    protected function createLaravelModel(string $name): void
    {
        $modelPath = app_path("Models/{$name}.php");

        if (!file_exists($modelPath)) {

            $this->info("Creating model: {$name}");
            Artisan::call('make:model '.$name.' -m');

        } else {
            $this->warn("Model {$name} already exists.");
        }
    }

    protected function domainStub(string $name, array|null $result_props): string
    {
        if (is_null($result_props)) {
            return sprintf(
                <<<'STUB'
                <?php
    
                namespace Src\Contexts\%s\Domain\Entities;
    
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
            fn($prop) => "use Src\\Contexts\\{$name}\\Domain\\ValueObjects\\{$name}" . Str::studly($prop) . ";",
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
    
            namespace Src\Contexts\%s\Domain\Entities;
    
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
    
            namespace Src\Contexts\%s\Domain\Contracts;
    
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
    
            namespace Src\Contexts\%s\Infrastructure\Persistence\Repositories\Eloquent;
    
            use Src\Contexts\%s\Domain\Contracts\%sRepositoryContract;
            use Src\Contexts\%s\Domain\Entities\%s;
    
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
            $name
        );
    }

    protected function valueObjectStub(string $name, string $propName, string $propType): string
    {
        return sprintf(
            <<<'STUB'
            <?php
    
            namespace Src\Contexts\%s\Domain\ValueObjects;
    
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
            $name.Str::studly($propName),
            $propType,
            $propType,
            $propType
        );
    }

    protected function registerProvider(string $name): void
    {
        $providerClass = "Src\\Contexts\\{$name}\\Infrastructure\\{$name}ServiceProvider::class";
        $providersFile = base_path('bootstrap/providers.php');

        if (! $this->filesystem->exists($providersFile)) {
            $this->warn("Provider file not found at {$providersFile}");
            return;
        }

        $content = $this->filesystem->get($providersFile);

        if (str_contains($content, $providerClass)) {
            $this->info("Service provider {$providerClass} is already registered.");
            return;
        }

        if (! str_starts_with(trim($content), '<?php')) {
            $content = "<?php\n\n" . ltrim($content);
        }

        $pos = strrpos($content, '];');
        if ($pos === false) {
            $this->error("Could not find the array closing tag (];) in {$providersFile}");
            return;
        }

        $before = substr($content, 0, $pos);
        $after = substr($content, $pos);

        $before = rtrim($before);
        if (! str_ends_with($before, ',')) {
            $before .= ',';
        }

        $before .= "\n    {$providerClass}";

        $newContent = $before . "\n" . $after;

        $this->filesystem->put($providersFile, $newContent);

        $this->info("✅ Service provider {$providerClass} registered successfully.");
    }


    protected function providerStub(string $name): string
    {
        return sprintf(
            <<<'STUB'
            <?php

            namespace Src\Contexts\%s\Infrastructure;

            use Illuminate\Support\ServiceProvider;
            use Src\Contexts\%s\Domain\Contracts\%sRepositoryContract;
            use Src\Contexts\%s\Infrastructure\Persistence\Repositories\Eloquent\%sRepository;

            class %sServiceProvider extends ServiceProvider
            {
                public function register(): void
                {
                    $this->app->bind(%sRepositoryContract::class, %sRepository::class);
                }

                public function boot(): void
                {
                    $this->loadRoutesFrom(__DIR__.'/Http/Routes/%s.php');
                }
            }
            STUB,
            $name,
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

    protected function routeStub(string $name): string
    {
        return sprintf(
            <<<'STUB'
            <?php

            use Illuminate\Support\Facades\Route;
            STUB
        );
    }
}
