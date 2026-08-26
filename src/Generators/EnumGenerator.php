<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Yntech\DomainForge\Support\Property;

final class EnumGenerator
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

		foreach ($properties as $property) {
			if (!$property->isEnum()) {
				continue;
			}

			$this->generateEnum($moduleName, $property);
		}

		return $this->createdFiles;
	}

	public function getCreatedFiles(): array
	{
		return $this->createdFiles;
	}

	private function generateEnum(string $moduleName, Property $property): void
	{
		$className = $moduleName . $property->studlyName();
		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$path = "{$basePath}/{$moduleName}/Domain/Enums/{$className}.php";

		$content = $this->buildContent($moduleName, $className, $property);

		$this->filesystem->ensureDirectoryExists(dirname($path));
		$this->filesystem->put($path, $content);

		$this->createdFiles[] = $path;
	}

	private function buildContent(string $moduleName, string $className, Property $property): string
	{
		$namespace = $this->getNamespace($moduleName);
		$cases = $this->buildCases($property->enumValues);
		$allMethod = $this->buildAllMethod($property->enumValues);

		return <<<PHP
<?php

namespace {$namespace};

enum {$className}: string
{
{$cases}

{$allMethod}

    /**
     * Create enum from string value.
     *
     * @throws \ValueError
     */
    public static function fromString(string \$value): self
    {
        return self::from(\$value);
    }

    /**
     * Create enum from nullable string value.
     */
    public static function fromNullableString(?string \$value): ?self
    {
        return \$value !== null ? self::from(\$value) : null;
    }

    /**
     * Get the string representation of the enum.
     */
    public function toString(): string
    {
        return \$this->value;
    }
}
PHP;
	}

	private function getNamespace(string $moduleName): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');

		return "{$baseNamespace}\\{$moduleName}\\Domain\\Enums";
	}

	/**
	 * @param  array<string>  $values
	 */
	private function buildCases(array $values): string
	{
		return collect($values)
			->map(fn(string $value) => '    case ' . strtoupper($value) . " = '{$value}';")
			->implode("\n");
	}

	/**
	 * @param  array<string>  $values
	 */
	private function buildAllMethod(array $values): string
	{
		$cases = collect($values)
			->map(fn(string $value) => '            self::' . strtoupper($value) . ',')
			->implode("\n");

		return <<<METHOD
    /**
     * Get all possible enum values.
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
}