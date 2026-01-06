<?php

namespace Yntech\DomainForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class PublishStubsCommand extends Command
{
    protected $signature = 'domain-forge:publish-stubs {--force : Overwrite existing stubs}';
    protected $description = 'Publish Domain Forge stub files for customization';

    public function __construct(
        private readonly Filesystem $filesystem
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $stubsPath = base_path('stubs/domain-forge');

        if ($this->filesystem->exists($stubsPath) && !$this->option('force')) {
            if (!$this->confirm('Stubs directory already exists. Do you want to overwrite?')) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $this->filesystem->ensureDirectoryExists($stubsPath);

        $stubs = $this->getStubDefinitions();

        foreach ($stubs as $name => $content) {
            $path = "{$stubsPath}/{$name}.stub";
            $this->filesystem->put($path, $content);
            $this->info("✅ Published: {$name}.stub");
        }

        $this->newLine();
        $this->info("🎉 Stubs published successfully to: {$stubsPath}");
        $this->line("You can now customize these templates to fit your needs.");

        return self::SUCCESS;
    }

    private function getStubDefinitions(): array
    {
        return [
            'entity' => $this->getEntityStub(),
            'entity-simple' => $this->getSimpleEntityStub(),
            'value-object' => $this->getValueObjectStub(),
            'enum' => $this->getEnumStub(),
            'repository-contract' => $this->getRepositoryContractStub(),
            'repository' => $this->getRepositoryStub(),
            'mapper' => $this->getMapperStub(),
            'service-provider' => $this->getServiceProviderStub(),
            'routes' => $this->getRoutesStub(),
        ];
    }

    private function getEntityStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

{{ imports }}

final readonly class {{ className }}
{
    private function __construct(
{{ constructorParams }}
    ) {}

{{ getters }}

    public static function create(
{{ createParams }}
    ): static {
        return new self(
{{ createArgs }}
        );
    }

    public static function fromPrimitives(
{{ fromPrimitivesParams }}
    ): static {
        return new self(
{{ fromPrimitivesArgs }}
        );
    }
}
STUB;
    }

    private function getSimpleEntityStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

final readonly class {{ className }}
{
    public function __construct() {}

    public static function create(): static 
    {
        return new self();
    }
}
STUB;
    }

    private function getValueObjectStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

final readonly class {{ className }}
{
    private function __construct(
        private {{ type }} $value
    ) {
        $this->validate($value);
    }

    private function validate({{ type }} $value): void
    {
        // Add validation logic here
    }

    public function value(): {{ type }}
    {
        return $this->value;
    }

    public static function create({{ type }} $value): static
    {
        return new self($value);
    }

{{ additionalMethods }}
}
STUB;
    }

    private function getEnumStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

enum {{ className }}: string
{
{{ cases }}

{{ allMethod }}

{{ fromStringMethod }}
}
STUB;
    }

    private function getRepositoryContractStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

interface {{ className }}
{
    //
}
STUB;
    }

    private function getRepositoryStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

{{ uses }}

class {{ className }} implements {{ implements }}
{
    //
}
STUB;
    }

    private function getMapperStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use App\Models\{{ modelName }} as {{ modelName }}Model;
use Src\Contexts\{{ contextName }}\Domain\Entities\{{ entityName }};

class {{ className }}
{
    //
}
STUB;
    }

    private function getServiceProviderStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

{{ uses }}

class {{ className }} extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            {{ contractClass }},
            {{ repositoryClass }}
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom({{ routesPath }});
    }
}
STUB;
    }

    private function getRoutesStub(): string
    {
        return <<<'STUB'
<?php

use Illuminate\Support\Facades\Route;

// Define your routes here
STUB;
    }
}