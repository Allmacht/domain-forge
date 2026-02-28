<?php

namespace Yntech\DomainForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Exception;

class CreateModuleCommand extends Command
{
    protected $signature = 'domain-forge:domain {name} {--props=} {--skip-model : Skip Laravel model creation}';
    protected $description = 'Create a new domain module';

    private const BASE_PATH = 'src/Contexts';

    private array $createdFiles = [];
    private array $createdDirectories = [];
    private array $enumProperties = [];

    public function __construct(
        private readonly Filesystem $filesystem
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $name = Str::studly($this->argument('name'));

            // Validaciones de seguridad
            if (!$this->validateModuleName($name)) {
                return self::FAILURE;
            }

            if (!$this->checkWritePermissions()) {
                return self::FAILURE;
            }

            $props = $this->parseProperties($this->option('props'));

            $this->info("🚀 Creating domain module: {$name}");
            $this->newLine();

            // Generar migration si no se omite el modelo
            $generateMigration = !$this->option('skip-model');

            if (!$this->option('skip-model')) {
                $this->createLaravelModel($name);
            } else {
                $this->line("⏭️  Skipping Laravel model creation");
            }

            $this->createDirectoryStructure($name);
            $this->createEnums($name, $this->enumProperties);
            $this->createValueObjects($name, $props);
            $this->createDomainFiles($name, $props, $generateMigration);
            $this->registerServiceProvider($name);

            $this->newLine();
            $this->showCreationSummary();

            $this->newLine();
            $this->info("✅ Domain {$name} created successfully!");

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            $this->warn("🔄 Rolling back changes...");

            $this->rollback();

            return self::FAILURE;
        }
    }

    private function validateModuleName(string $name): bool
    {
        if (empty($name)) {
            $this->error("❌ Module name cannot be empty.");
            return false;
        }

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error("❌ Invalid module name format.");
            $this->line("   Module name must:");
            $this->line("   - Start with an uppercase letter");
            $this->line("   - Contain only alphanumeric characters");
            $this->line("   - Not contain spaces, special characters or underscores");
            return false;
        }

        if (strlen($name) > 50) {
            $this->error("❌ Module name is too long (max 50 characters).");
            return false;
        }

        return true;
    }

    private function checkWritePermissions(): bool
    {
        $basePath = base_path(self::BASE_PATH);

        if (!$this->filesystem->exists($basePath)) {
            try {
                $this->filesystem->makeDirectory($basePath, 0755, true);
                $this->line("📁 Created base directory: {$basePath}");
            } catch (Exception $e) {
                $this->error("❌ Cannot create base directory: {$basePath}");
                $this->error("   Error: {$e->getMessage()}");
                return false;
            }
        }

        if (!is_writable($basePath)) {
            $this->error("❌ No write permissions for: {$basePath}");
            $this->line("   Run: chmod -R 755 {$basePath}");
            return false;
        }

        $providersFile = base_path('bootstrap/providers.php');
        if ($this->filesystem->exists($providersFile) && !is_writable($providersFile)) {
            $this->error("❌ No write permissions for: {$providersFile}");
            return false;
        }

        return true;
    }

    private function parseProperties(?string $propsString): array
    {
        if (empty($propsString)) {
            return [];
        }

        $propsArray = explode(',', $propsString);
        $result = [];

        foreach ($propsArray as $prop) {
            $prop = trim($prop);

            if (!str_contains($prop, ':')) {
                $this->warn("⚠️  Property '{$prop}' doesn't have correct format (should be name:type).");
                continue;
            }

            [$key, $type] = array_map('trim', explode(':', $prop, 2));

            // Sanitización de nombres de propiedades
            if (!preg_match('/^[a-z][a-zA-Z0-9_]*$/', $key)) {
                $this->warn("⚠️  Property name '{$key}' is invalid. Skipping.");
                continue;
            }

            // Detectar si es un enum
            if (preg_match('/^enum\[(.+)\]$/', $type, $matches)) {
                $enumValues = array_map('trim', explode('|', $matches[1]));

                // Validar valores del enum
                foreach ($enumValues as $enumValue) {
                    if (!preg_match('/^[a-z][a-zA-Z0-9_]*$/', $enumValue)) {
                        $this->warn("⚠️  Enum value '{$enumValue}' is invalid. Skipping property '{$key}'.");
                        continue 2;
                    }
                }

                $this->enumProperties[$key] = $enumValues;
                $result[$key] = 'enum'; // Marcar como enum
            } else {
                $result[$key] = $type;
            }
        }

        return $result;
    }

    private function createDirectoryStructure(string $name): void
    {
        $directories = $this->getModuleDirectories($name);

        // Añadir directorio de Enums si hay enums
        if (!empty($this->enumProperties)) {
            $directories[] = self::BASE_PATH . "/{$name}/Domain/Enums";
        }

        foreach ($directories as $path) {
            try {
                $this->filesystem->makeDirectory($path, 0755, true, true);
                $this->createdDirectories[] = $path;
            } catch (Exception $e) {
                throw new Exception("Failed to create directory: {$path}");
            }
        }
    }

    private function getModuleDirectories(string $name): array
    {
        $base = self::BASE_PATH . "/{$name}";

        return [
            // Application Layer
            "{$base}/Application",
            "{$base}/Application/Commands",
            "{$base}/Application/Handlers",
            "{$base}/Application/DTOs",
            "{$base}/Application/Services",
            "{$base}/Application/UseCases",

            // Domain Layer
            "{$base}/Domain",
            "{$base}/Domain/Entities",
            "{$base}/Domain/Contracts",
            "{$base}/Domain/Exceptions",
            "{$base}/Domain/ValueObjects",

            // Infrastructure Layer
            "{$base}/Infrastructure",
            "{$base}/Infrastructure/Http",
            "{$base}/Infrastructure/Http/Controllers",
            "{$base}/Infrastructure/Http/Requests",
            "{$base}/Infrastructure/Http/Resources",
            "{$base}/Infrastructure/Http/Routes",
            "{$base}/Infrastructure/Persistence",
            "{$base}/Infrastructure/Persistence/Mappers",
            "{$base}/Infrastructure/Persistence/Repositories",
            "{$base}/Infrastructure/Persistence/Repositories/Eloquent",
        ];
    }

    private function createEnums(string $name, array $enums): void
    {
        if (empty($enums)) {
            return;
        }

        $basePath = self::BASE_PATH . "/{$name}/Domain/Enums";

        foreach ($enums as $propName => $values) {
            $className = $name . Str::studly($propName);
            $filePath = "{$basePath}/{$className}.php";

            try {
                $content = $this->getStubContent('enum', [
                    'namespace' => "Src\\Contexts\\{$name}\\Domain\\Enums",
                    'className' => $className,
                    'cases' => $this->generateEnumCases($values),
                    'allMethod' => $this->generateEnumAllMethod($values),
                    'fromStringMethod' => $this->generateEnumFromStringMethod($values),
                ]);

                $this->filesystem->put($filePath, $content);
                $this->createdFiles[] = $filePath;
            } catch (Exception $e) {
                throw new Exception("Failed to create Enum: {$className}");
            }
        }
    }

    private function generateEnumCases(array $values): string
    {
        return collect($values)
            ->map(fn($value) => "    case " . strtoupper($value) . " = '{$value}';")
            ->implode("\n");
    }

    private function generateEnumAllMethod(array $values): string
    {
        $cases = collect($values)
            ->map(fn($value) => "            self::" . strtoupper($value) . ",")
            ->implode("\n");

        return <<<METHOD
            /**
             * Get all possible enum values
             *
             * @return array<self>
             */
            public static function all(): array
            {
                return [
        {$cases}
                ];
            }
        METHOD;
    }

    private function generateEnumFromStringMethod(array $values): string
    {
        return <<<'METHOD'
            /**
             * Create enum from string value
             *
             * @throws \ValueError
             */
            public static function fromString(string $value): self
            {
                return self::from($value);
            }

            /**
             * Create enum from nullable string value
             */
            public static function fromNullableString(?string $value): ?self
            {
                return $value !== null ? self::from($value) : null;
            }

            /**
             * Get string representation of the enum value
             */
            public function toString(): string
            {
                return $this->value;
            }
        METHOD;
    }

    private function createValueObjects(string $name, array $props): void
    {
        if (empty($props)) {
            return;
        }

        $basePath = self::BASE_PATH . "/{$name}/Domain/ValueObjects";

        foreach ($props as $propName => $propType) {
            // Skip enums, ya fueron creados
            if ($propType === 'enum') {
                continue;
            }

            $className = $name . Str::studly($propName);
            $filePath = "{$basePath}/{$className}.php";

            try {
                $content = $this->generateValueObjectContent($name, $propName, $propType);
                $this->filesystem->put($filePath, $content);
                $this->createdFiles[] = $filePath;
            } catch (Exception $e) {
                throw new Exception("Failed to create ValueObject: {$className}");
            }
        }
    }

    private function createDomainFiles(string $name, array $props, bool $generateMigration): void
    {
        $basePath = self::BASE_PATH . "/{$name}";

        $files = [
            "{$basePath}/Domain/Entities/{$name}.php" =>
                $this->generateEntityContent($name, $props),

            "{$basePath}/Domain/Contracts/{$name}RepositoryContract.php" =>
                $this->getStubContent('repository-contract', [
                    'namespace' => "Src\\Contexts\\{$name}\\Domain\\Contracts",
                    'className' => "{$name}RepositoryContract",
                ]),

            "{$basePath}/Infrastructure/Persistence/Repositories/Eloquent/{$name}Repository.php" =>
                $this->getStubContent('repository', [
                    'namespace' => "Src\\Contexts\\{$name}\\Infrastructure\\Persistence\\Repositories\\Eloquent",
                    'uses' => "use Src\\Contexts\\{$name}\\Domain\\Contracts\\{$name}RepositoryContract;\nuse Src\\Contexts\\{$name}\\Domain\\Entities\\{$name};",
                    'className' => "{$name}Repository",
                    'implements' => "{$name}RepositoryContract",
                ]),

            "{$basePath}/Infrastructure/Http/Routes/{$name}.php" =>
                $this->getStubContent('routes', []),

            "{$basePath}/Infrastructure/{$name}ServiceProvider.php" =>
                $this->getStubContent('service-provider', [
                    'namespace' => "Src\\Contexts\\{$name}\\Infrastructure",
                    'uses' => "use Illuminate\\Support\\ServiceProvider;\nuse Src\\Contexts\\{$name}\\Domain\\Contracts\\{$name}RepositoryContract;\nuse Src\\Contexts\\{$name}\\Infrastructure\\Persistence\\Repositories\\Eloquent\\{$name}Repository;",
                    'className' => "{$name}ServiceProvider",
                    'contractClass' => "{$name}RepositoryContract::class",
                    'repositoryClass' => "{$name}Repository::class",
                    'routesPath' => "__DIR__ . '/Http/Routes/{$name}.php'",
                ]),
        ];

        // Añadir Mapper si se generó migration
        if ($generateMigration && !empty($props)) {
            $files["{$basePath}/Infrastructure/Persistence/Mappers/{$name}Mapper.php"] =
                $this->generateMapperContent($name, $props);
        }

        foreach ($files as $path => $content) {
            try {
                $this->filesystem->put($path, $content);
                $this->createdFiles[] = $path;
            } catch (Exception $e) {
                throw new Exception("Failed to create file: " . basename($path));
            }
        }
    }

    private function createLaravelModel(string $name): void
    {
        $modelPath = app_path("Models/{$name}.php");

        if (file_exists($modelPath)) {
            $this->warn("⚠️  Model {$name} already exists.");
            return;
        }

        try {
            Artisan::call('make:model', [
                'name' => $name,
                '-m' => true
            ]);

            $this->createdFiles[] = $modelPath;
            $migrationPath = database_path('migrations');
            $this->createdFiles[] = $migrationPath;
        } catch (Exception $e) {
            throw new Exception("Failed to create Laravel model: {$name}");
        }
    }

    private function registerServiceProvider(string $name): void
    {
        $providersFile = base_path('bootstrap/providers.php');
        $providerClass = "Src\\Contexts\\{$name}\\Infrastructure\\{$name}ServiceProvider::class";

        if (!$this->filesystem->exists($providersFile)) {
            $this->warn("⚠️  Provider file not found at {$providersFile}");
            return;
        }

        $content = $this->filesystem->get($providersFile);

        if (str_contains($content, $providerClass)) {
            return;
        }

        $newContent = $this->injectProviderIntoFile($content, $providerClass);

        if ($newContent === null) {
            throw new Exception("Could not register service provider.");
        }

        $this->filesystem->put($providersFile, $newContent);
    }

    private function injectProviderIntoFile(string $content, string $providerClass): ?string
    {
        if (!str_starts_with(trim($content), '<?php')) {
            $content = "<?php\n\n" . ltrim($content);
        }

        $pos = strrpos($content, '];');

        if ($pos === false) {
            return null;
        }

        $before = rtrim(substr($content, 0, $pos));
        $after = substr($content, $pos);

        if (!str_ends_with($before, ',')) {
            $before .= ',';
        }

        return $before . "\n    {$providerClass}\n" . $after;
    }

    private function rollback(): void
    {
        $this->newLine();

        // Eliminar archivos creados
        foreach (array_reverse($this->createdFiles) as $file) {
            if ($this->filesystem->exists($file) && is_file($file)) {
                try {
                    $this->filesystem->delete($file);
                    $this->line("   🗑️  Deleted: {$file}");
                } catch (Exception $e) {
                    // Continuar con el siguiente
                }
            }
        }

        // Eliminar directorios creados (en orden inverso)
        foreach (array_reverse($this->createdDirectories) as $dir) {
            if ($this->filesystem->exists($dir) && $this->filesystem->isDirectory($dir)) {
                try {
                    if ($this->isDirectoryEmpty($dir)) {
                        $this->filesystem->deleteDirectory($dir);
                        $this->line("   🗑️  Deleted directory: {$dir}");
                    }
                } catch (Exception $e) {
                    // Continuar con el siguiente
                }
            }
        }

        $this->info("✅ Rollback completed.");
    }

    private function isDirectoryEmpty(string $dir): bool
    {
        $files = $this->filesystem->files($dir);
        $directories = $this->filesystem->directories($dir);

        return empty($files) && empty($directories);
    }

    private function showCreationSummary(): void
    {
        $this->info("📋 Creation Summary:");
        $this->newLine();

        $grouped = [
            'Directories' => $this->createdDirectories,
            'Files' => $this->createdFiles,
        ];

        foreach ($grouped as $type => $items) {
            if (empty($items))
                continue;

            $this->line("<fg=cyan;options=bold>{$type}:</>");

            $rows = [];
            foreach ($items as $item) {
                $relativePath = str_replace(base_path() . '/', '', $item);
                $rows[] = ['  ✅', $relativePath];
            }

            $this->table(['Status', 'Path'], $rows);
        }

        $totalItems = count($this->createdDirectories) + count($this->createdFiles);
        $this->info("📊 Total items created: {$totalItems}");
    }

    // ==================== Stub System ====================

    private function getStubContent(string $stubName, array $replacements = []): string
    {
        $stubPath = $this->getStubPath($stubName);

        if (!$this->filesystem->exists($stubPath)) {
            // Fallback a stub inline si no existe el archivo
            return $this->getInlineStub($stubName, $replacements);
        }

        $content = $this->filesystem->get($stubPath);

        foreach ($replacements as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        return $content;
    }

    private function getStubPath(string $stubName): string
    {
        // Buscar primero en el proyecto (custom stubs)
        $customPath = base_path("stubs/domain-forge/{$stubName}.stub");
        if ($this->filesystem->exists($customPath)) {
            return $customPath;
        }

        // Luego en el paquete
        $packagePath = __DIR__ . "/../../../stubs/{$stubName}.stub";
        if ($this->filesystem->exists($packagePath)) {
            return $packagePath;
        }

        return '';
    }

    private function getInlineStub(string $stubName, array $replacements): string
    {
        return match ($stubName) {
            'enum' => $this->generateEnumStub($replacements),
            'repository-contract' => $this->generateRepositoryContractStub($replacements),
            'repository' => $this->generateRepositoryStub($replacements),
            'routes' => $this->generateRoutesStub(),
            'service-provider' => $this->generateServiceProviderStub($replacements),
            default => '',
        };
    }

    private function generateEnumStub(array $data): string
    {
        return <<<PHP
        <?php

        namespace {$data['namespace']};

        enum {$data['className']}: string
        {
        {$data['cases']}

        {$data['allMethod']}

        {$data['fromStringMethod']}
        }
        PHP;
    }

    private function generateRepositoryContractStub(array $data): string
    {
        return <<<PHP
        <?php

        namespace {$data['namespace']};

        interface {$data['className']}
        {
            //
        }
        PHP;
    }

    private function generateRepositoryStub(array $data): string
    {
        return <<<PHP
        <?php

        namespace {$data['namespace']};

        {$data['uses']}

        class {$data['className']} implements {$data['implements']}
        {
            //
        }
        PHP;
    }

    private function generateRoutesStub(): string
    {
        return <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        // Define your routes here
        PHP;
    }

    private function generateServiceProviderStub(array $data): string
    {
        return <<<PHP
        <?php

        namespace {$data['namespace']};

        {$data['uses']}

        class {$data['className']} extends ServiceProvider
        {
            public function register(): void
            {
                \$this->app->bind(
                    {$data['contractClass']},
                    {$data['repositoryClass']}
                );
            }

            public function boot(): void
            {
                \$this->loadRoutesFrom({$data['routesPath']});
            }
        }
        PHP;
    }

    // ==================== Content Generators ====================

    private function generateEntityContent(string $name, array $props): string
    {
        if (empty($props)) {
            return $this->generateSimpleEntity($name);
        }

        return $this->generateEntityWithProperties($name, $props);
    }

    private function generateSimpleEntity(string $name): string
    {
        return <<<PHP
        <?php

        namespace Src\Contexts\\{$name}\Domain\Entities;

        final readonly class {$name}
        {
            public function __construct() {}

            public static function create(): static 
            {
                return new self();
            }
        }
        PHP;
    }

    private function generateEntityWithProperties(string $name, array $props): string
    {
        $hasId = array_key_exists('id', $props);
        $propsWithoutId = $hasId ? array_diff_key($props, ['id' => '']) : $props;

        $imports = $this->generatePropertyImports($name, $props);
        $constructorParams = $this->generateConstructorParameters($name, $props);
        $getters = $this->generateGetters($name, $props);
        $createParams = $this->generateCreateParameters($name, $propsWithoutId);
        $createArgs = $this->generateCreateArguments($name, $props, $propsWithoutId);
        $fromPrimitivesParams = $this->generateFromPrimitivesParameters($props);
        $fromPrimitivesArgs = $this->generateFromPrimitivesArguments($name, $props);

        return <<<PHP
        <?php

        namespace Src\Contexts\\{$name}\Domain\Entities;

        {$imports}

        final readonly class {$name}
        {
            private function __construct(
        {$constructorParams}
            ) {}

        {$getters}

            public static function create(
        {$createParams}
            ): static {
                return new self(
        {$createArgs}
                );
            }

            public static function fromPrimitives(
        {$fromPrimitivesParams}
            ): static {
                return new self(
        {$fromPrimitivesArgs}
                );
            }
        }
        PHP;
    }

    private function generatePropertyImports(string $name, array $props): string
    {
        return collect($props)
            ->map(function ($type, $prop) use ($name) {
                if ($type === 'enum') {
                    return "use Src\\Contexts\\{$name}\\Domain\\Enums\\{$name}" . Str::studly($prop) . ";";
                }
                return "use Src\\Contexts\\{$name}\\Domain\\ValueObjects\\{$name}" . Str::studly($prop) . ";";
            })
            ->implode("\n");
    }

    private function generateConstructorParameters(string $name, array $props): string
    {
        return collect($props)
            ->map(function ($type, $prop) use ($name) {
                $className = $name . Str::studly($prop);
                $nullable = $type === 'enum' && isset($this->enumProperties[$prop]) ? '' : '';

                if ($type === 'enum') {
                    return "        private {$className} \${$prop},";
                }

                return "        private {$className} \${$prop},";
            })
            ->implode("\n");
    }

    private function generateGetters(string $name, array $props): string
    {
        return collect($props)
            ->map(function ($type, $prop) use ($name) {
                $className = $name . Str::studly($prop);
                return <<<METHOD
                    public function {$prop}(): {$className}
                    {
                        return \$this->{$prop};
                    }
                METHOD;
            })
            ->implode("\n\n");
    }

    private function generateCreateParameters(string $name, array $props): string
    {
        return collect($props)
            ->map(fn($type, $prop) => "        {$name}" . Str::studly($prop) . " \${$prop},")
            ->implode("\n");
    }

    private function generateCreateArguments(string $name, array $allProps, array $propsWithoutId): string
    {
        $hasId = array_key_exists('id', $allProps);

        $args = collect($propsWithoutId)
            ->keys()
            ->map(fn($prop) => "            {$prop}: \${$prop},");

        if ($hasId) {
            $idGeneration = "            id: {$name}Id::generate(),";
            $args->prepend($idGeneration);
        }

        return $args->implode("\n");
    }

    private function generateFromPrimitivesParameters(array $props): string
    {
        return collect($props)
            ->map(function ($type, $prop) {
                if ($type === 'enum') {
                    return "        string \${$prop},";
                }
                return "        {$type} \${$prop},";
            })
            ->implode("\n");
    }

    private function generateFromPrimitivesArguments(string $name, array $props): string
    {
        return collect($props)
            ->map(function ($type, $prop) use ($name) {
                $className = $name . Str::studly($prop);

                if ($type === 'enum') {
                    return "            {$prop}: {$className}::fromString(\${$prop}),";
                }

                $method = $this->inferFromPrimitivesMethod($prop, $type);
                return "            {$prop}: {$className}::{$method}(\${$prop}),";
            })
            ->implode("\n");
    }

    private function inferFromPrimitivesMethod(string $propName, string $type): string
    {
        if (str_contains($propName, 'password')) {
            return 'fromHashed';
        }

        if (str_ends_with($propName, '_at')) {
            $isNullable = str_starts_with($type, '?');
            return $isNullable ? 'fromNullableString' : 'fromString';
        }

        $isNullable = str_starts_with($type, '?');

        if ($isNullable) {
            $cleanType = ltrim($type, '?');
            return match ($cleanType) {
                'int' => 'fromNullableInt',
                'float' => 'fromNullableFloat',
                'bool' => 'fromNullableBool',
                default => 'fromNullableString',
            };
        }

        return match ($type) {
            'int' => 'fromInt',
            'float' => 'fromFloat',
            'bool' => 'fromBool',
            default => 'fromString',
        };
    }

    private function generateValueObjectContent(string $name, string $propName, string $propType): string
    {
        $className = $name . Str::studly($propName);
        $isNullable = str_starts_with($propType, '?');
        $cleanType = ltrim($propType, '?');

        $isId = $propName === 'id';
        $isPassword = str_contains($propName, 'password');

        $generateMethod = $isId ? $this->generateIdGenerateMethod($cleanType) : '';
        $hashedMethod = $isPassword ? $this->generatePasswordHashedMethod() : '';
        $nullableMethods = $isNullable ? $this->generateNullableValueObjectMethods($cleanType) : '';
        $fromPrimitiveMethod = $this->generateFromPrimitiveMethod($propType);

        return <<<PHP
        <?php

        namespace Src\Contexts\\{$name}\Domain\ValueObjects;

        final readonly class {$className}
        {
            private function __construct(
                private {$propType} \$value
            ) {
                \$this->validate(\$value);
            }

            private function validate({$propType} \$value): void
            {
                // Add validation logic here
            }

            public function value(): {$propType}
            {
                return \$this->value;
            }

            public static function create({$propType} \$value): static
            {
                return new self(\$value);
            }

        {$fromPrimitiveMethod}{$generateMethod}{$hashedMethod}{$nullableMethods}
        }
        PHP;
    }

    private function generateIdGenerateMethod(string $type): string
    {
        $cleanType = ltrim($type, '?');

        return match ($cleanType) {
            'int' => <<<'METHOD'

            /**
             * Generate a new auto-incrementing ID.
             * Note: This should typically be handled by the database.
             * Override this method if you need custom ID generation logic.
             */
            public static function generate(): static
            {
                // This is a placeholder. Usually handled by database auto-increment
                throw new \RuntimeException('ID generation should be handled by the database');
            }
        METHOD,
            default => <<<'METHOD'

            /**
             * Generate a new unique ID.
             * By default uses UUID v4. Override to use ULID or other strategies.
             */
            public static function generate(): static
            {
                return new self(\Illuminate\Support\Str::uuid()->toString());
            }

            /**
             * Generate using ULID (uncomment if preferred)
             */
            // public static function generate(): static
            // {
            //     return new self(\Illuminate\Support\Str::ulid()->toString());
            // }
        METHOD,
        };
    }

    private function generatePasswordHashedMethod(): string
    {
        return <<<'METHOD'

            public static function fromHashed(string $hashedPassword): static
            {
                return new self($hashedPassword);
            }

            public static function hash(string $plainPassword): static
            {
                return new self(\Illuminate\Support\Facades\Hash::make($plainPassword));
            }
        METHOD;
    }

    private function generateFromPrimitiveMethod(string $propType): string
    {
        $isNullable = str_starts_with($propType, '?');
        $cleanType = ltrim($propType, '?');

        return match ($cleanType) {
            'int' => <<<'METHOD'
                public static function fromInt(int $value): static
                {
                    return new self($value);
                }
            METHOD,
            'float' => <<<'METHOD'
                public static function fromFloat(float $value): static
                {
                    return new self($value);
                }
            METHOD,
            'bool' => <<<'METHOD'
                public static function fromBool(bool $value): static
                {
                    return new self($value);
                }
            METHOD,
            default => <<<'METHOD'
                public static function fromString(string $value): static
                {
                    return new self($value);
                }
            METHOD,
        };
    }

    private function generateNullableValueObjectMethods(string $cleanType): string
    {
        return match ($cleanType) {
            'int' => <<<'METHOD'

                public static function fromNullableInt(?int $value): static
                {
                    return new self($value);
                }
            METHOD,
            'float' => <<<'METHOD'

                public static function fromNullableFloat(?float $value): static
                {
                    return new self($value);
                }
            METHOD,
            'bool' => <<<'METHOD'

                public static function fromNullableBool(?bool $value): static
                {
                    return new self($value);
                }
            METHOD,
            default => <<<'METHOD'

                public static function fromNullableString(?string $value): static
                {
                    return new self($value);
                }
            METHOD,
        };
    }

    private function generateMapperContent(string $name, array $props): string
    {
        $toDomainMappings = collect($props)
            ->map(function ($type, $prop) use ($name) {
                $accessor = $type === 'enum' ? "\$model->{$prop}" : "\$model->{$prop}";
                return "            {$prop}: {$accessor},";
            })
            ->implode("\n");

        $toEloquentMappings = collect($props)
            ->filter(fn($type, $prop) => $prop !== 'id')
            ->map(function ($type, $prop) use ($name) {
                if ($type === 'enum') {
                    return "            '{$prop}' => \$entity->{$prop}()->value,";
                }
                return "            '{$prop}' => \$entity->{$prop}()->value(),";
            })
            ->implode("\n");

        return <<<PHP
        <?php

        namespace Src\Contexts\\{$name}\Infrastructure\Persistence\Mappers;

        use App\Models\\{$name} as {$name}Model;
        use Src\Contexts\\{$name}\Domain\Entities\\{$name};

        class {$name}Mapper
        {
            //
        }
        PHP;
    }
}