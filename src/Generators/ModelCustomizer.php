<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Yntech\DomainForge\Support\IdStrategy;
use Yntech\DomainForge\Support\Property;
use RuntimeException;

final class ModelCustomizer
{
    /** @var array<string> */
    private array $touchedFiles = [];

    public function __construct(
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @param  array<Property>  $properties
     * @return array<string>
     */
    public function customize(string $moduleName, array $properties): array
    {
        $moduleName = Str::studly($moduleName);
        $this->touchedFiles = [];

        $idProperty = collect($properties)->first(
            fn(Property $property) => $property->isId()
        );

        if ($idProperty === null || $idProperty->idStrategy === null) {
            return [];
        }

        if ($idProperty->idStrategy === IdStrategy::Int) {
            return [];
        }

        $modelPath = app_path("Models/{$moduleName}.php");

        if (!$this->filesystem->exists($modelPath)) {
            throw new RuntimeException("Model [{$moduleName}] was not found at {$modelPath}");
        }

        $content = $this->filesystem->get($modelPath);
        $content = $this->applyTrait($content, $idProperty->idStrategy);

        $this->filesystem->put($modelPath, $content);
        $this->touchedFiles[] = $modelPath;

        return $this->touchedFiles;
    }

    /** @return array<string> */
    public function getTouchedFiles(): array
    {
        return $this->touchedFiles;
    }

    private function applyTrait(string $content, IdStrategy $strategy): string
    {
        [$traitFqn, $traitShort] = match ($strategy) {
            IdStrategy::Ulid => [
                'Illuminate\\Database\\Eloquent\\Concerns\\HasUlids',
                'HasUlids',
            ],
            IdStrategy::Uuid,
            IdStrategy::UuidV7 => [
                'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
                'HasUuids',
            ],
            IdStrategy::Int => [
                null,
                null,
            ],
        };

        if ($traitFqn === null) {
            return $content;
        }

        if (str_contains($content, $traitShort)) {
            return $content;
        }

        if (!str_contains($content, $traitFqn)) {
            $content = preg_replace(
                '/(namespace App\\\\Models;\s*)/',
                "$1\nuse {$traitFqn};\n",
                $content,
                1
            ) ?? $content;
        }

        if (str_contains($content, 'use HasFactory;')) {
            $content = str_replace(
                'use HasFactory;',
                "use HasFactory, {$traitShort};",
                $content
            );

            return $content;
        }

        $content = preg_replace(
            '/(class\s+\w+\s+extends\s+Model\s*\{)/',
            "$1\n    use {$traitShort};\n",
            $content,
            1
        ) ?? $content;

        return $content;
    }
}