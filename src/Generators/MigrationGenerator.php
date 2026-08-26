<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yntech\DomainForge\Support\IdStrategy;
use Yntech\DomainForge\Support\Property;
use RuntimeException;

final class MigrationGenerator
{
    /** @var array<string> */
    private array $createdFiles = [];

    public function __construct(
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     *
     * @param  array<Property>  $properties
     * @return array<string>
     */
    public function generate(string $moduleName, array $properties): array
    {
        $moduleName = Str::studly($moduleName);
        $this->createdFiles = [];

        $table = Str::snake(Str::pluralStudly($moduleName));
        $migrationPath = $this->findMigrationPath($table);

        if ($migrationPath === null) {
            throw new RuntimeException("Migration for table [{$table}] was not found.");
        }

        $content = $this->buildContent($moduleName, $table, collect($properties));
        $this->filesystem->put($migrationPath, $content);

        $this->createdFiles[] = $migrationPath;

        return $this->createdFiles;
    }

    /**
     * @return array<string>
     */
    public function getCreatedFiles(): array
    {
        return $this->createdFiles;
    }

    private function findMigrationPath(string $table): ?string
    {
        $migrationsPath = database_path('migrations');

        if (!$this->filesystem->isDirectory($migrationsPath)) {
            return null;
        }

        $pattern = $migrationsPath . '/*_create_' . $table . '_table.php';
        $files = glob($pattern) ?: [];

        if (empty($files)) {
            return null;
        }

        rsort($files);

        return $files[0];
    }

    /**
     * @param  Collection<int, Property>  $properties
     */
    private function buildContent(string $moduleName, string $table, Collection $properties): string
    {
        $columns = $this->buildColumns($properties);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
{$columns}
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP;
    }

    /**
     * @param  Collection<int, Property>  $properties
     */
    private function buildColumns(Collection $properties): string
    {
        if ($properties->isEmpty()) {
            return "            \$table->id();\n";
        }

        $lines = [];

        foreach ($properties as $property) {
            $lines[] = '            ' . $this->columnFor($property);
        }

        return implode("\n", $lines) . "\n";
    }

    private function columnFor(Property $property): string
    {
        if ($property->isId() && $property->idStrategy !== null) {
            return match ($property->idStrategy) {
                IdStrategy::Int => "\$table->id();",
                IdStrategy::Ulid => "\$table->ulid('id')->primary();",
                IdStrategy::Uuid,
                IdStrategy::UuidV7 => "\$table->uuid('id')->primary();",
            };
        }

        if ($property->isTimestamp()) {
            return $property->isNullable
                ? "\$table->timestamp('{$property->name}')->nullable();"
                : "\$table->timestamp('{$property->name}');";
        }

        if ($property->isPassword()) {
            return "\$table->string('{$property->name}');";
        }

        if ($property->isEnum()) {
            return $property->isNullable
                ? "\$table->string('{$property->name}')->nullable();"
                : "\$table->string('{$property->name}');";
        }

        return match ($property->type) {
            'int' => $property->isNullable
            ? "\$table->integer('{$property->name}')->nullable();"
            : "\$table->integer('{$property->name}');",

            'float' => $property->isNullable
            ? "\$table->decimal('{$property->name}', 12, 2)->nullable();"
            : "\$table->decimal('{$property->name}', 12, 2);",

            'bool' => $property->isNullable
            ? "\$table->boolean('{$property->name}')->nullable();"
            : "\$table->boolean('{$property->name}');",

            default => $property->isNullable
            ? "\$table->string('{$property->name}')->nullable();"
            : "\$table->string('{$property->name}');",
        };
    }
}