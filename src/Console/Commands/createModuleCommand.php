<?php

namespace Yntech\DomainForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;
use Yntech\DomainForge\Generators\ModuleGenerator;
use Yntech\DomainForge\Support\Property;
use Yntech\DomainForge\Support\PropertyParser;

class CreateModuleCommand extends Command
{
	protected $signature = 'domain-forge:domain
                            {name : The name of the domain module}
                            {--props= : Properties (e.g. "id:uuidv7,name:string,status:enum[active|inactive]")}
                            {--skip-model : Skip Eloquent model and migration creation}';

	protected $description = 'Create a new domain module following Hexagonal Architecture';

	public function __construct(
		private readonly Filesystem $filesystem,
		private readonly PropertyParser $propertyParser,
		private readonly ModuleGenerator $moduleGenerator,
	) {
		parent::__construct();
	}

	public function handle(): int
	{
		$name = Str::studly($this->argument('name'));

		if (!$this->validateModuleName($name)) {
			return self::FAILURE;
		}

		if (!$this->checkWritePermissions()) {
			return self::FAILURE;
		}

		$properties = $this->propertyParser->parse($this->option('props'));

		foreach ($this->propertyParser->warnings() as $warning) {
			$this->components->warn($warning);
		}

		if (!empty($this->propertyParser->warnings())) {
			$this->newLine();
		}

		$this->components->info("Creating domain module: {$name}");
		$this->newLine();

		try {
			$withModel = false;
			$withMigration = false;

			if (!$this->option('skip-model')) {
				$this->createLaravelModel($name);
				$withModel = true;
				$withMigration = true;
			} else {
				$this->components->twoColumnDetail('Eloquent Model', '<fg=gray>Skipped</>');
				$this->components->twoColumnDetail('Migration', '<fg=gray>Skipped</>');
			}

			$this->moduleGenerator->generate(
				moduleName: $name,
				properties: $properties,
				withMigration: $withMigration && !empty($properties),
				withModel: $withModel && !empty($properties)
			);

			if ($withModel && !empty($properties)) {
				$this->components->twoColumnDetail('Model customizer', '<fg=green>Filled</>');
			}

			if ($withMigration && !empty($properties)) {
				$this->components->twoColumnDetail('Migration columns', '<fg=green>Filled</>');
			}

			$this->newLine();
			$this->showSummary($name, $properties);
			$this->newLine();

			$this->components->info("Domain [{$name}] created successfully.");
			$this->newLine();
			$this->showNextSteps($name);

			return self::SUCCESS;
		} catch (Throwable $e) {
			$this->newLine();
			$this->components->error($e->getMessage());
			$this->components->warn('Rolling back changes...');

			$this->moduleGenerator->rollback();

			$this->newLine();
			$this->components->error("Domain [{$name}] could not be created.");

			return self::FAILURE;
		}
	}

	private function validateModuleName(string $name): bool
	{
		if ($name === '') {
			$this->components->error('Module name cannot be empty.');
			return false;
		}

		if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
			$this->components->error('Invalid module name.');
			$this->line('  - Must start with an uppercase letter');
			$this->line('  - Only alphanumeric characters allowed');
			return false;
		}

		if (strlen($name) > 50) {
			$this->components->error('Module name is too long (max 50 characters).');
			return false;
		}

		if ($this->moduleAlreadyExists($name)) {
			$this->components->error("The domain module [{$name}] already exists.");
			$this->line('  Path: ' . $this->modulePath($name));
			return false;
		}

		return true;
	}

	private function moduleAlreadyExists(string $name): bool
	{
		return $this->filesystem->isDirectory($this->modulePath($name));
	}

	private function modulePath(string $name): string
	{
		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));

		return rtrim($basePath, '/') . '/' . $name;
	}

	private function checkWritePermissions(): bool
	{
		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));

		if (!$this->filesystem->isDirectory($basePath)) {
			try {
				$this->filesystem->makeDirectory($basePath, 0755, true);
			} catch (Throwable $e) {
				$this->components->error("Cannot create base directory: {$basePath}");
				return false;
			}
		}

		if (!is_writable($basePath)) {
			$this->components->error("No write permissions for: {$basePath}");
			return false;
		}

		return true;
	}

	private function createLaravelModel(string $name): void
	{
		$modelPath = app_path("Models/{$name}.php");

		if ($this->filesystem->exists($modelPath)) {
			$this->components->twoColumnDetail('Eloquent Model', '<fg=yellow>Already exists</>');
			return;
		}

		Artisan::call('make:model', [
			'name' => $name,
			'--migration' => true,
		]);

		$this->components->twoColumnDetail('Eloquent Model', '<fg=green>Created</>');
		$this->components->twoColumnDetail('Migration', '<fg=green>Created</>');
	}

	/**
	 * @param  array<Property>  $properties
	 */
	private function showSummary(string $name, array $properties): void
	{
		$files = $this->moduleGenerator->getCreatedFiles();
		$directories = $this->moduleGenerator->getCreatedDirectories();

		$this->components->twoColumnDetail('Module', "<fg=cyan>{$name}</>");
		$this->components->twoColumnDetail('Directories', (string) count($directories));
		$this->components->twoColumnDetail('Files', (string) count($files));

		if (!empty($properties)) {
			$this->newLine();
			$this->line('  <fg=yellow>Properties</>');

			foreach ($properties as $property) {
				$this->components->twoColumnDetail(
					"  {$property->name}",
					'<fg=gray>' . $this->formatPropertyType($property) . '</>'
				);
			}
		}

		$this->newLine();
		$this->line('  <fg=yellow>Structure</>');
		$this->line("  <fg=cyan>src/Contexts/{$name}/</>");
		$this->line('  ├── Application/');
		$this->line('  │   ├── UseCases/');
		$this->line('  │   └── DTOs/');
		$this->line('  ├── Domain/');
		$this->line('  │   ├── Entities/');
		$this->line('  │   ├── ValueObjects/');
		$this->line('  │   ├── Enums/');
		$this->line('  │   ├── Contracts/');
		$this->line('  │   └── Exceptions/');
		$this->line('  └── Infrastructure/');
		$this->line('      ├── Http/Routes/');
		$this->line('      ├── Persistence/');
		$this->line("      └── {$name}ServiceProvider.php");
	}

	private function formatPropertyType(Property $property): string
	{
		if ($property->isId() && $property->idStrategy !== null) {
			return 'id:' . $property->idStrategy->value;
		}

		if ($property->isEnum()) {
			return 'enum[' . implode('|', $property->enumValues) . ']';
		}

		return $property->isNullable
			? '?' . $property->type
			: $property->type;
	}

	private function showNextSteps(string $name): void
	{
		$this->line('  <fg=yellow>Next steps</>');
		$this->line("  1. Review the entity: <fg=cyan>Domain/Entities/{$name}.php</>");
		$this->line("  2. Define methods in: <fg=cyan>Domain/Contracts/{$name}RepositoryContract.php</>");
		$this->line("  3. Implement them in: <fg=cyan>Infrastructure/Persistence/Repositories/Eloquent/{$name}Repository.php</>");
		$this->line("  4. Add routes in: <fg=cyan>Infrastructure/Http/Routes/{$name}.php</>");
		$this->newLine();
		$this->line('  Generate another domain:');
		$this->line('  <fg=gray>php artisan domain-forge:domain Order --props="id:ulid,total:float,status:enum[pending|paid]"</>');
	}
}