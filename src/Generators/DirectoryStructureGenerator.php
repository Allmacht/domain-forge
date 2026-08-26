<?php

namespace Yntech\DomainForge\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class DirectoryStructureGenerator
{
	/** @var array<string> */
	private array $createdDirectories = [];

	public function __construct(
		private readonly Filesystem $filesystem
	) {
	}

	/**
	 * @param  bool  $hasEnums
	 * @return array<string>
	 */
	public function generate(string $moduleName, bool $hasEnums = false): array
	{
		$moduleName = Str::studly($moduleName);
		$this->createdDirectories = [];

		$basePath = config('domain-forge.domain_path', base_path('src/Contexts'));
		$base = $basePath . '/' . $moduleName;

		$directories = $this->resolveDirectories($base, $hasEnums);

		foreach ($directories as $directory) {
			$this->createDirectory($directory);
		}

		return $this->createdDirectories;
	}

	public function getCreatedDirectories(): array
	{
		return $this->createdDirectories;
	}

	/**
	 * @return array<string>
	 */
	private function resolveDirectories(string $base, bool $hasEnums): array
	{
		$structure = config('domain-forge.structure', []);
		$directories = [];

		if ($application = $structure['application'] ?? []) {
			$directories[] = "{$base}/Application";

			foreach ($application as $folder => $enabled) {
				if ($enabled) {
					$directories[] = "{$base}/Application/" . $this->folderName($folder);
				}
			}
		}

		// Domain
		if ($domain = $structure['domain'] ?? []) {
			$directories[] = "{$base}/Domain";

			foreach ($domain as $folder => $enabled) {
				if (!$enabled) {
					continue;
				}

				if ($folder === 'enums' && !$hasEnums) {
					continue;
				}

				$directories[] = "{$base}/Domain/" . $this->folderName($folder);
			}
		}

		if ($infrastructure = $structure['infrastructure'] ?? []) {
			$directories[] = "{$base}/Infrastructure";

			if ($http = $infrastructure['http'] ?? []) {
				$directories[] = "{$base}/Infrastructure/Http";

				foreach ($http as $folder => $enabled) {
					if ($enabled) {
						$directories[] = "{$base}/Infrastructure/Http/" . $this->folderName($folder);
					}
				}
			}

			if ($persistence = $infrastructure['persistence'] ?? []) {
				$directories[] = "{$base}/Infrastructure/Persistence";

				foreach ($persistence as $folder => $enabled) {
					if (!$enabled) {
						continue;
					}

					$folderName = $this->folderName($folder);
					$directories[] = "{$base}/Infrastructure/Persistence/{$folderName}";

					// Caso especial: Repositories/Eloquent
					if ($folder === 'repositories') {
						$directories[] = "{$base}/Infrastructure/Persistence/Repositories/Eloquent";
					}
				}
			}
		}

		return $directories;
	}

	private function folderName(string $key): string
	{
		return match ($key) {
			'use_cases' => 'UseCases',
			'value_objects' => 'ValueObjects',
			'dtos' => 'DTOs',
			default => Str::studly($key),
		};
	}

	private function createDirectory(string $path): void
	{
		if ($this->filesystem->isDirectory($path)) {
			return;
		}

		$this->filesystem->makeDirectory($path, 0755, true);
		$this->createdDirectories[] = $path;
	}
}