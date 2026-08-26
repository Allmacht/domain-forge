<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Yntech\DomainForge\Support\IdStrategy;
use Yntech\DomainForge\Support\Property;

final class ValueObjectGenerator
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
			if ($property->isEnum()) {
				continue;
			}

			$this->generateValueObject($moduleName, $property);
		}

		return $this->createdFiles;
	}

	public function getCreatedFiles(): array
	{
		return $this->createdFiles;
	}

	private function generateValueObject(string $moduleName, Property $property): void
	{
		$className = $moduleName . $property->studlyName();
		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$path = "{$basePath}/{$moduleName}/Domain/ValueObjects/{$className}.php";

		$content = $this->buildContent($moduleName, $className, $property);

		$this->filesystem->ensureDirectoryExists(dirname($path));
		$this->filesystem->put($path, $content);

		$this->createdFiles[] = $path;
	}

	private function buildContent(string $moduleName, string $className, Property $property): string
	{
		$phpType = $property->phpType();
		$namespace = $this->getNamespace($moduleName);

		$generateMethod = $this->buildGenerateMethod($property);
		$passwordMethods = $this->buildPasswordMethods($property);
		$fromPrimitiveMethod = $this->buildFromPrimitiveMethod($property);
		$nullableMethods = $this->buildNullableMethods($property);

		return <<<PHP
<?php

namespace {$namespace};

final readonly class {$className}
{
    private function __construct(
        private {$phpType} \$value
    ) {
        \$this->validate(\$value);
    }

    private function validate({$phpType} \$value): void
    {
        // Add domain validation here
    }

    public function value(): {$phpType}
    {
        return \$this->value;
    }

    public static function create({$phpType} \$value): static
    {
        return new self(\$value);
    }
{$fromPrimitiveMethod}{$generateMethod}{$passwordMethods}{$nullableMethods}
}
PHP;
	}

	private function getNamespace(string $moduleName): string
	{
		$baseNamespace = config('domain-forge.namespace', 'Src\\Contexts');

		return "{$baseNamespace}\\{$moduleName}\\Domain\\ValueObjects";
	}

	private function buildGenerateMethod(Property $property): string
	{
		if (!$property->isId() || $property->idStrategy === null) {
			return '';
		}

		return match ($property->idStrategy) {
			IdStrategy::Uuid => <<<'METHOD'

    public static function generate(): static
    {
        return new self(\Src\Shared\Domain\Support\Uuid::v4());
    }
METHOD,

			IdStrategy::UuidV7 => <<<'METHOD'

    public static function generate(): static
    {
        return new self(\Src\Shared\Domain\Support\Uuid::v7());
    }
METHOD,

			IdStrategy::Ulid => <<<'METHOD'

    public static function generate(): static
    {
        return new self(\Src\Shared\Domain\Support\Ulid::generate());
    }
METHOD,

			IdStrategy::Int => <<<'METHOD'

    public static function generate(): static
    {
        throw new \RuntimeException('Auto-increment ID generation must be handled by the database.');
    }
METHOD,
		};
	}

	private function buildPasswordMethods(Property $property): string
	{
		if (!$property->isPassword()) {
			return '';
		}

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

	private function buildFromPrimitiveMethod(Property $property): string
	{
		if ($property->isId()) {
			return match ($property->idStrategy) {
				IdStrategy::Int => <<<'METHOD'

    public static function fromInt(int $value): static
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

		if ($property->isPassword()) {
			return '';
		}

		return match ($property->type) {
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

	private function buildNullableMethods(Property $property): string
	{
		if (!$property->isNullable) {
			return '';
		}

		return match ($property->type) {
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
}