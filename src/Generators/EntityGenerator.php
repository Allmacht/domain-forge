<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yntech\DomainForge\Support\IdStrategy;
use Yntech\DomainForge\Support\Property;

final class EntityGenerator
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
		$moduleName = Str::studly($moduleName);
		$this->createdFiles = [];

		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$path = "{$basePath}/{$moduleName}/Domain/Entities/{$moduleName}.php";

		$content = empty($properties)
			? $this->buildSimpleEntity($moduleName)
			: $this->buildEntityWithProperties($moduleName, $properties);

		$this->filesystem->ensureDirectoryExists(dirname($path));
		$this->filesystem->put($path, $content);

		$this->createdFiles[] = $path;

		return $this->createdFiles;
	}

	public function getCreatedFiles(): array
	{
		return $this->createdFiles;
	}

	private function buildSimpleEntity(string $moduleName): string
	{
		$namespace = $this->getNamespace($moduleName);

		return <<<PHP
<?php

namespace {$namespace};

final readonly class {$moduleName}
{
    private function __construct() {}

    public static function create(): static
    {
        return new self();
    }
}
PHP;
	}

	/**
	 * @param  array<Property>  $properties
	 */
	private function buildEntityWithProperties(string $moduleName, array $properties): string
	{
		$namespace = $this->getNamespace($moduleName);
		$props = collect($properties);

		$imports = $this->buildImports($moduleName, $props);
		$constructorParams = $this->buildConstructorParameters($moduleName, $props);
		$getters = $this->buildGetters($moduleName, $props);
		$createMethod = $this->buildCreateMethod($moduleName, $props);
		$fromPrimitivesMethod = $this->buildFromPrimitivesMethod($moduleName, $props);

		return <<<PHP
<?php

namespace {$namespace};

{$imports}

final readonly class {$moduleName}
{
    private function __construct(
{$constructorParams}
    ) {}

{$getters}

{$createMethod}

{$fromPrimitivesMethod}
}
PHP;
	}

	private function getNamespace(string $moduleName): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');

		return "{$baseNamespace}\\{$moduleName}\\Domain\\Entities";
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildImports(string $moduleName, Collection $properties): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');

		return $properties
			->map(function (Property $property) use ($moduleName, $baseNamespace) {
				$className = $moduleName . $property->studlyName();

				if ($property->isEnum()) {
					return "use {$baseNamespace}\\{$moduleName}\\Domain\\Enums\\{$className};";
				}

				return "use {$baseNamespace}\\{$moduleName}\\Domain\\ValueObjects\\{$className};";
			})
			->unique()
			->implode("\n");
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildConstructorParameters(string $moduleName, Collection $properties): string
	{
		return $properties
			->map(function (Property $property) use ($moduleName) {
				$className = $moduleName . $property->studlyName();
				return "        private {$className} \${$property->name},";
			})
			->implode("\n");
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildGetters(string $moduleName, Collection $properties): string
	{
		return $properties
			->map(function (Property $property) use ($moduleName) {
				$className = $moduleName . $property->studlyName();

				return <<<METHOD
    public function {$property->name}(): {$className}
    {
        return \$this->{$property->name};
    }
METHOD;
			})
			->implode("\n\n");
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildCreateMethod(string $moduleName, Collection $properties): string
	{
		$idProperty = $properties->first(fn(Property $p) => $p->isId());
		$propsWithoutId = $properties->reject(fn(Property $p) => $p->isId());

		$params = $propsWithoutId
			->map(function (Property $property) use ($moduleName) {
				$className = $moduleName . $property->studlyName();
				return "        {$className} \${$property->name},";
			})
			->implode("\n");

		$args = $properties
			->map(function (Property $property) use ($moduleName, $idProperty) {
				if ($property->isId() && $idProperty?->idStrategy !== IdStrategy::Int) {

					$className = $moduleName . $property->studlyName();
					return "            {$property->name}: {$className}::generate(),";
				}

				return "            {$property->name}: \${$property->name},";
			})
			->implode("\n");

		if ($idProperty?->idStrategy === IdStrategy::Int) {
			$idClass = $moduleName . 'Id';
			$params = "        {$idClass} \$id,\n" . $params;
		}

		return <<<METHOD
    public static function create(
{$params}
    ): static {
        return new self(
{$args}
        );
    }
METHOD;
	}

	/**
	 * @param  Collection<int, Property>  $properties
	 */
	private function buildFromPrimitivesMethod(string $moduleName, Collection $properties): string
	{
		$params = $properties
			->map(function (Property $property) {
				if ($property->isEnum()) {
					$type = $property->isNullable ? '?string' : 'string';
					return "        {$type} \${$property->name},";
				}

				return "        {$property->phpType()} \${$property->name},";
			})
			->implode("\n");

		$args = $properties
			->map(function (Property $property) use ($moduleName) {
				$className = $moduleName . $property->studlyName();
				$method = $this->resolveFromPrimitivesMethod($property);

				return "            {$property->name}: {$className}::{$method}(\${$property->name}),";
			})
			->implode("\n");

		return <<<METHOD
    public static function fromPrimitives(
{$params}
    ): static {
        return new self(
{$args}
        );
    }
METHOD;
	}

	private function resolveFromPrimitivesMethod(Property $property): string
	{
		if ($property->isEnum()) {
			return $property->isNullable ? 'fromNullableString' : 'fromString';
		}

		if ($property->isId()) {
			return match ($property->idStrategy) {
				IdStrategy::Int => 'fromInt',
				default => 'fromString',
			};
		}

		if ($property->isPassword()) {
			return 'fromHashed';
		}

		if ($property->isNullable) {
			return match ($property->type) {
				'int' => 'fromNullableInt',
				'float' => 'fromNullableFloat',
				'bool' => 'fromNullableBool',
				default => 'fromNullableString',
			};
		}

		return match ($property->type) {
			'int' => 'fromInt',
			'float' => 'fromFloat',
			'bool' => 'fromBool',
			default => 'fromString',
		};
	}
}