# Domain Forge

Hexagonal architecture generator for Laravel.

Domain Forge helps you scaffold domain modules following **DDD** and **Hexagonal Architecture** principles, without imposing application or HTTP patterns.

## Requirements

- PHP 8.2+
- Laravel 12+

## Installation

```bash
composer require yntech/domain-forge
```

### 1. Install base structure

```bash
php artisan domain-forge:install
```

This creates:

- `src/Contexts`
- `src/Shared` kernel
  - `Uuid` / `Ulid` generators
  - `TransactionManager`
  - `SharedServiceProvider`

### 2. Autoload

Add this to your `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/",
      "Src\\": "src/"
    }
  }
}
```

Then run:

```bash
composer dump-autoload
```

## Generate a domain

```bash
php artisan domain-forge:domain Product --props="id:uuidv7,name:string,price:float,status:enum[draft|published]"
```

### Options

| Option | Description |
|--------|-------------|
| `--props=` | Domain properties |
| `--skip-model` | Skip Eloquent model and migration |

## Property types

| Type | Example | Notes |
|------|---------|-------|
| `id:uuid` | `id:uuid` | UUID v4 |
| `id:uuidv7` | `id:uuidv7` | UUID v7 (time-ordered) |
| `id:ulid` | `id:ulid` | ULID |
| `id:int` | `id:int` | Auto-increment |
| `string` | `name:string` | |
| `?string` | `description:?string` | Nullable |
| `int` | `stock:int` | |
| `float` | `price:float` | Stored as `decimal(12,2)` |
| `bool` | `is_active:bool` | |
| `enum[...]` | `status:enum[draft\|published]` | Native PHP enum |
| password fields | `password:string` | Adds `hash()` / `fromHashed()` |
| `*_at` fields | `published_at:?string` | Timestamp columns |

### Examples

```bash
# UUID v7
php artisan domain-forge:domain Product --props="id:uuidv7,name:string,price:float"

# ULID
php artisan domain-forge:domain Order --props="id:ulid,total:float,status:enum[pending|paid|cancelled]"

# Auto-increment
php artisan domain-forge:domain Category --props="id:int,name:string"

# Without Eloquent model
php artisan domain-forge:domain Tag --props="id:uuid,name:string" --skip-model
```

## Generated structure

```text
src/Contexts/Product/
├── Application/
│   ├── UseCases/
│   └── DTOs/
├── Domain/
│   ├── Entities/
│   │   └── Product.php
│   ├── ValueObjects/
│   │   ├── ProductId.php
│   │   ├── ProductName.php
│   │   └── ...
│   ├── Enums/
│   │   └── ProductStatus.php
│   ├── Contracts/
│   │   └── ProductRepositoryContract.php
│   └── Exceptions/
└── Infrastructure/
    ├── Http/
    │   └── Routes/
    │       └── Product.php
    ├── Persistence/
    │   ├── Mappers/
    │   │   └── ProductMapper.php
    │   └── Repositories/
    │       └── Eloquent/
    │           └── ProductRepository.php
    └── ProductServiceProvider.php
```

Also generated when model creation is enabled:

- `app/Models/Product.php`
- `database/migrations/*_create_products_table.php`

## What Domain Forge generates

### Domain
- Entity (`final readonly`)
- Value Objects
- Enums
- Repository contract (empty, intentionally)

### Infrastructure
- Eloquent repository implementation (empty, intentionally)
- Mapper (`toDomain` / `toEloquent`)
- Routes file (neutral skeleton)
- Module ServiceProvider
- Migration columns from props
- Model traits:
  - `HasUuids` for `id:uuid` / `id:uuidv7`
  - `HasUlids` for `id:ulid`

### What it does **not** impose
- REST controllers
- Form requests
- API resources
- Repository methods
- Use case implementations

You decide the application style (single-action controllers, CQRS, actions, etc.).

## ID generation

IDs are generated in the domain using pure PHP helpers from the shared kernel:

```php
ProductId::generate(); // uses Src\Shared\Domain\Support\Uuid or Ulid
```

No Laravel facades inside domain Value Objects.

## Configuration

Publish config:

```bash
php artisan vendor:publish --provider="Yntech\DomainForge\Providers\DomainForgeServiceProvider" --tag=config
```

Main options in `config/domain-forge.php`:

- `domain_path`
- `namespace`
- generated folder structure

## Design principles

- Domain stays free from Laravel infrastructure details
- Generators are separated by responsibility
- Existing modules cannot be overwritten by default
- Application/HTTP layers are scaffolded, not prescribed

## License

MIT
```