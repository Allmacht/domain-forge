<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class ServiceProviderGenerator
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

		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$path = "{$basePath}/{$moduleName}/Infrastructure/{$moduleName}ServiceProvider.php";

		$content = $this->buildContent($moduleName);

		$this->filesystem->ensureDirectoryExists(dirname($path));
		$this->filesystem->put($path, $content);

		$this->createdFiles[] = $path;

		return $this->createdFiles;
	}

	public function getCreatedFiles(): array
	{
		return $this->createdFiles;
	}

	private function buildContent(string $moduleName): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');
		$namespace = "{$baseNamespace}\\{$moduleName}\\Infrastructure";

		return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Support\ServiceProvider;
use {$baseNamespace}\\{$moduleName}\\Domain\\Contracts\\{$moduleName}RepositoryContract;
use {$baseNamespace}\\{$moduleName}\\Infrastructure\\Persistence\\Repositories\\Eloquent\\{$moduleName}Repository;

class {$moduleName}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \$this->app->bind(
            {$moduleName}RepositoryContract::class,
            {$moduleName}Repository::class
        );
    }

    public function boot(): void
    {
        \$this->loadRoutesFrom(__DIR__ . '/Http/Routes/{$moduleName}.php');
    }
}
PHP;
	}
}