# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - 2026-08-26

First production-ready release.

Versions `1.x`–`3.x` were experimental and should not be used in production.
This release is a full rewrite of the generator architecture.

### Added

- Explicit ID strategies: `id:uuid`, `id:uuidv7`, `id:ulid`, `id:int`
- Shared kernel helpers generated on install:
  - `Src\Shared\Domain\Support\Uuid` (v4 and v7, pure PHP)
  - `Src\Shared\Domain\Support\Ulid` (pure PHP)
- Configurable domain folder structure via `config/domain-forge.php`
- Migration generator that fills columns from `--props`
- Eloquent model customization:
  - `HasUuids` for `id:uuid` and `id:uuidv7`
  - `HasUlids` for `id:ulid`
- Duplicate context validation (existing modules are not overwritten)
- Richer command feedback: warnings, summary, next steps and rollback
- Separated generators:
  - `DirectoryStructureGenerator`
  - `ValueObjectGenerator`
  - `EnumGenerator`
  - `EntityGenerator`
  - `MapperGenerator`
  - `RepositoryGenerator`
  - `RoutesGenerator`
  - `ServiceProviderGenerator`
  - `MigrationGenerator`
  - `ModelCustomizer`
  - `ModuleGenerator`

### Changed

- Domain path now defaults to `src/Contexts`
- Application layer defaults to `UseCases` and `DTOs` only
- Repository contract and implementation are generated empty on purpose
- Routes file is a neutral skeleton (no REST methods)
- ID Value Objects no longer depend on Laravel facades
- `id:string` is deprecated and falls back to `id:uuid` with a warning

### Removed

- `src/Core` directory from the install structure
- Automatic REST controller / method scaffolding
- Prescribed repository methods (`findById`, `save`, `delete`)
- Fragile `RepositoryServiceProvider` code injection from previous versions

### Fixed

- Mapper now generates `toDomain()` and `toEloquent()`
- Command class naming / PSR-4 consistency
- Incomplete mapper generation from 3.x

## [3.1.0] - 2026-03-24

Experimental release.

## [3.0.1] - 2026-02-28

Experimental release.

## [3.0.0] - 2026-01-06

Experimental release.

## [2.0.0] - 2025-10-08

Experimental release.

## [1.2.0] - 2025-03-19

Experimental release.

## [1.1.0] - 2025-03-14

Experimental release.

## [1.0.0] - 2025-03-13

Initial experimental release.
