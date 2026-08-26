<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yntech\DomainForge\Support\IdStrategy;
use Yntech\DomainForge\Support\Property;

final class MapperGenerator
{
	/** @var array<string> */
	private array $createdFiles = [];

	public function __construct(
		private readonly Filesystem $filesystem
	) {
	}

	/**
	 * @param  array<Property>  $properties
	 * @return array<string>
	 */
	public function generate(string $moduleName, array $properties): array
	{
		if (empty($properties)) {
			return [];
		}

		$moduleName = Str::studly($moduleName);
		$this->createdFiles = [];

		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$path = "{$basePath}/{$moduleName}/Infrastructure/Persistence/Mappers/{$moduleName}Mapper.php";

		$content = $this->buildContent($moduleName, collect($properties));

		$this->filesystem->ensureDirectoryExists(dirname($path));
		$this->filesystem->put($path, $content);

		$this->createdFiles[] = $path;

		return $this->createdFiles;
	}

	public function getCreatedFiles(): array
	{
		return $this->createdFiles;
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildContent(string $moduleName, Collection $properties): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');
		$namespace = "{$baseNamespace}\\{$moduleName}\\Infrastructure\\Persistence\\Mappers";

		$toDomainArgs = $this->buildToDomainArguments($properties);
		$toEloquentArray = $this->buildToEloquentArray($properties);

		return <<<PHP
<?php

namespace {$namespace};

use App\Models\\{$moduleName} as {$moduleName}Model;
use {$baseNamespace}\\{$moduleName}\\Domain\\Entities\\{$moduleName};

final class {$moduleName}Mapper
{
    public static function toDomain({$moduleName}Model \$model): {$moduleName}
    {
        return {$moduleName}::fromPrimitives(
{$toDomainArgs}
        );
    }

    public static function toEloquent({$moduleName} \$entity): array
    {
        return [
{$toEloquentArray}
        ];
    }
}
PHP;
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildToDomainArguments(Collection $properties): string
	{
		return $properties
			->map(function (Property $property) {
				return "            {$property->name}: \$model->{$property->name},";
			})
			->implode("\n");
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildToEloquentArray(Collection $properties): string
	{
		return $properties
			->map(function (Property $property) {
				$accessor = $this->resolveEloquentAccessor($property);

				return "            '{$property->name}' => {$accessor},";
			})
			->implode("\n");
	}

	private function resolveEloquentAccessor(Property $property): string
	{
		if ($property->isEnum()) {
			return "\$entity->{$property->name}()->value";
		}

		return "\$entity->{$property->name}()->value()";
	}
}