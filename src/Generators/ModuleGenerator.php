<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Yntech\DomainForge\Support\Property;

final class ModuleGenerator
{
	/** @var array<string> */
	private array $createdFiles = [];

	/** @var array<string> */
	private array $createdDirectories = [];

	public function __construct(
		private readonly Filesystem $filesystem,
		private readonly DirectoryStructureGenerator $directoryGenerator,
		private readonly EnumGenerator $enumGenerator,
		private readonly ValueObjectGenerator $valueObjectGenerator,
		private readonly EntityGenerator $entityGenerator,
		private readonly MapperGenerator $mapperGenerator,
		private readonly RepositoryGenerator $repositoryGenerator,
		private readonly ServiceProviderGenerator $serviceProviderGenerator,
		private readonly RoutesGenerator $routesGenerator,
		private readonly MigrationGenerator $migrationGenerator,
		private readonly ModelCustomizer $modelCustomizer
	) {
	}

	/**
	 * @param  array<Property>  $properties
	 */
	public function generate(
		string $moduleName,
		array $properties = [],
		bool $withMigration = false,
		bool $withModel = false
	): void {
		$moduleName = Str::studly($moduleName);

		$hasEnums = collect($properties)->contains(
			fn(Property $p) => $p->isEnum()
		);

		$this->createdDirectories = $this->directoryGenerator->generate(
			$moduleName,
			$hasEnums
		);

		$this->createdFiles = array_merge(
			$this->createdFiles,
			$this->enumGenerator->generate($moduleName, $properties)
		);

		$this->createdFiles = array_merge(
			$this->createdFiles,
			$this->valueObjectGenerator->generate($moduleName, $properties)
		);

		$this->createdFiles = array_merge(
			$this->createdFiles,
			$this->entityGenerator->generate($moduleName, $properties)
		);

		if (!empty($properties)) {
			$this->createdFiles = array_merge(
				$this->createdFiles,
				$this->mapperGenerator->generate($moduleName, $properties)
			);
		}

		$this->createdFiles = array_merge(
			$this->createdFiles,
			$this->repositoryGenerator->generate($moduleName)
		);

		$this->createdFiles = array_merge(
			$this->createdFiles,
			$this->routesGenerator->generate($moduleName)
		);

		$this->createdFiles = array_merge(
			$this->createdFiles,
			$this->serviceProviderGenerator->generate($moduleName)
		);

		if ($withMigration && !empty($properties)) {
			$this->createdFiles = array_merge(
				$this->createdFiles,
				$this->migrationGenerator->generate($moduleName, $properties)
			);
		}

		if ($withModel && !empty($properties)) {
			$this->createdFiles = array_merge(
				$this->createdFiles,
				$this->modelCustomizer->customize($moduleName, $properties)
			);
		}

		$this->registerServiceProvider($moduleName);
	}

	/** @return array<string> */
	public function getCreatedFiles(): array
	{
		return $this->createdFiles;
	}

	/** @return array<string> */
	public function getCreatedDirectories(): array
	{
		return $this->createdDirectories;
	}

	public function rollback(): void
	{
		foreach ($this->createdFiles as $file) {
			if ($this->filesystem->exists($file)) {
				$this->filesystem->delete($file);
			}
		}

		$directories = collect($this->createdDirectories)
			->sortByDesc(fn(string $dir) => substr_count($dir, '/'))
			->values();

		foreach ($directories as $directory) {
			if ($this->filesystem->isDirectory($directory) && $this->isDirectoryEmpty($directory)) {
				$this->filesystem->deleteDirectory($directory);
			}
		}
	}

	private function registerServiceProvider(string $moduleName): void
	{
		$providersPath = base_path('bootstrap/providers.php');

		if (!$this->filesystem->exists($providersPath)) {
			return;
		}

		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');
		$providerClass = "{$baseNamespace}\\{$moduleName}\\Infrastructure\\{$moduleName}ServiceProvider::class";

		$content = $this->filesystem->get($providersPath);

		if (str_contains($content, $providerClass)) {
			return;
		}

		$content = str_replace(
			'return [',
			"return [\n    {$providerClass},",
			$content
		);

		$this->filesystem->put($providersPath, $content);
	}

	private function isDirectoryEmpty(string $directory): bool
	{
		return empty($this->filesystem->files($directory))
			&& empty($this->filesystem->directories($directory));
	}
}