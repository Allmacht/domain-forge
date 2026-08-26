<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class RepositoryGenerator
{
	/** @var array<string> */
	private array $createdFiles = [];

	public function __construct(
		private readonly Filesystem $filesystem
	) {
	}

	/**
	 * @return array<string>
	 */
	public function generate(string $moduleName): array
	{
		$moduleName = Str::studly($moduleName);
		$this->createdFiles = [];

		$this->generateContract($moduleName);
		$this->generateImplementation($moduleName);

		return $this->createdFiles;
	}

	public function getCreatedFiles(): array
	{
		return $this->createdFiles;
	}

	private function generateContract(string $moduleName): void
	{
		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$path = "{$basePath}/{$moduleName}/Domain/Contracts/{$moduleName}RepositoryContract.php";

		$content = $this->buildContractContent($moduleName);

		$this->filesystem->ensureDirectoryExists(dirname($path));
		$this->filesystem->put($path, $content);

		$this->createdFiles[] = $path;
	}

	private function generateImplementation(string $moduleName): void
	{
		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$path = "{$basePath}/{$moduleName}/Infrastructure/Persistence/Repositories/Eloquent/{$moduleName}Repository.php";

		$content = $this->buildImplementationContent($moduleName);

		$this->filesystem->ensureDirectoryExists(dirname($path));
		$this->filesystem->put($path, $content);

		$this->createdFiles[] = $path;
	}

	private function buildContractContent(string $moduleName): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');
		$namespace = "{$baseNamespace}\\{$moduleName}\\Domain\\Contracts";

		return <<<PHP
<?php

namespace {$namespace};

interface {$moduleName}RepositoryContract
{
    //
}
PHP;
	}

	private function buildImplementationContent(string $moduleName): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');
		$namespace = "{$baseNamespace}\\{$moduleName}\\Infrastructure\\Persistence\\Repositories\\Eloquent";

		return <<<PHP
<?php

namespace {$namespace};

use {$baseNamespace}\\{$moduleName}\\Domain\\Contracts\\{$moduleName}RepositoryContract;

final class {$moduleName}Repository implements {$moduleName}RepositoryContract
{
    //
}
PHP;
	}
}