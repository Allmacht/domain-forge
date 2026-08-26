<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class RoutesGenerator
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
		$path = "{$basePath}/{$moduleName}/Infrastructure/Http/Routes/{$moduleName}.php";

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
		$routePrefix = Str::kebab(Str::plural($moduleName));

		return <<<PHP
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$moduleName} Routes
|--------------------------------------------------------------------------
|
| Register the routes for this context here.
| This file is loaded by the {$moduleName}ServiceProvider.
|
*/

Route::prefix('{$routePrefix}')->group(function () {
    //
});
PHP;
	}
}