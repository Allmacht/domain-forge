# Domain Forge

[![Latest Version](https://img.shields.io/packagist/v/yntech/domain-forge.svg?style=flat-square)](https://packagist.org/packages/yntech/domain-forge)
[![Total Downloads](https://img.shields.io/packagist/dt/yntech/domain-forge.svg?style=flat-square)](https://packagist.org/packages/yntech/domain-forge)
[![License](https://img.shields.io/packagist/l/yntech/domain-forge.svg?style=flat-square)](https://packagist.org/packages/yntech/domain-forge)

Domain Forge is a powerful Laravel package that streamlines the creation of domain modules following **Hexagonal Architecture** (Ports & Adapters) and **Domain-Driven Design (DDD)** principles. Generate complete, production-ready domain structures with a single command.

## 🌟 Features

- ✅ **Complete DDD Structure** - Automatically generates Application, Domain, and Infrastructure layers
- ✅ **Value Objects** - Rich domain models with validation and type safety
- ✅ **Native PHP Enums** - First-class support for PHP 8.1+ enums with helper methods
- ✅ **Auto-generated IDs** - Smart UUID/ULID generation for entity identifiers
- ✅ **Type Safety** - Nullable types, primitives, and enum support
- ✅ **Customizable Stubs** - Publish and modify templates to fit your needs
- ✅ **Automatic Mappers** - Eloquent ↔ Domain entity mappers
- ✅ **Smart Validation** - Password hashing, timestamp handling, and more
- ✅ **Rollback Support** - Automatic cleanup on errors
- ✅ **Permission Checks** - Validates write permissions before generation

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x or higher
- Composer

## 🚀 Installation

Install the package via Composer:

```bash
composer require yntech/domain-forge
```

## ⚙️ Configuration

### 1. Configure Base Structure

Run the installation command to set up the base structure:

```bash
php artisan domain-forge:install
```

### 2. Update Composer Autoload

Add the following to your `composer.json` in the `autoload.psr-4` section:

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

### 3. Refresh Autoload

```bash
composer dump-autoload
```

## 📚 Usage

### Basic Domain Generation

Create a simple domain module:

```bash
php artisan domain-forge:domain User
```

This generates:
```
src/Contexts/User/
├── Application/
│   ├── Commands/
│   ├── Handlers/
│   ├── DTOs/
│   ├── Services/
│   └── UseCases/
├── Domain/
│   ├── Entities/
│   │   └── User.php
│   ├── Contracts/
│   │   └── UserRepositoryContract.php
│   ├── Exceptions/
│   └── ValueObjects/
└── Infrastructure/
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   ├── Resources/
    │   └── Routes/
    │       └── User.php
    ├── Persistence/
    │   ├── Mappers/
    │   └── Repositories/
    │       └── Eloquent/
    │           └── UserRepository.php
    └── UserServiceProvider.php
```

### Domain with Properties

Generate a domain with value objects:

```bash
php artisan domain-forge:domain Product --props="name:string,price:float,stock:int,description:?string"
```

**Generated Value Objects:**
- `ProductName` (string)
- `ProductPrice` (float)
- `ProductStock` (int)
- `ProductDescription` (nullable string)

**Entity Structure:**
```php
<?php

namespace Src\Contexts\Product\Domain\Entities;

use Src\Contexts\Product\Domain\ValueObjects\ProductName;
use Src\Contexts\Product\Domain\ValueObjects\ProductPrice;
use Src\Contexts\Product\Domain\ValueObjects\ProductStock;
use Src\Contexts\Product\Domain\ValueObjects\ProductDescription;

final readonly class Product
{
    private function __construct(
        private ProductName $name,
        private ProductPrice $price,
        private ProductStock $stock,
        private ProductDescription $description,
    ) {}

    public function name(): ProductName
    {
        return $this->name;
    }

    public function price(): ProductPrice
    {
        return $this->price;
    }

    // ... other getters

    public static function create(
        ProductName $name,
        ProductPrice $price,
        ProductStock $stock,
        ProductDescription $description,
    ): static {
        return new self(
            name: $name,
            price: $price,
            stock: $stock,
            description: $description,
        );
    }

    public static function fromPrimitives(
        string $name,
        float $price,
        int $stock,
        ?string $description,
    ): static {
        return new self(
            name: ProductName::fromString($name),
            price: ProductPrice::fromFloat($price),
            stock: ProductStock::fromInt($stock),
            description: ProductDescription::fromNullableString($description),
        );
    }
}
```

## 🎯 Property Types

### Supported Types

| Type | Example | Value Object Method |
|------|---------|-------------------|
| `string` | `name:string` | `fromString(string)` |
| `int` | `age:int` | `fromInt(int)` |
| `float` | `price:float` | `fromFloat(float)` |
| `bool` | `active:bool` | `fromBool(bool)` |
| `?string` | `description:?string` | `fromNullableString(?string)` |
| `?int` | `quantity:?int` | `fromNullableInt(?int)` |
| `?float` | `discount:?float` | `fromNullableFloat(?float)` |
| `?bool` | `verified:?bool` | `fromNullableBool(?bool)` |

### Nullable Types

Add `?` prefix for nullable properties:

```bash
php artisan domain-forge:domain Post --props="title:string,content:string,excerpt:?string,published_at:?string"
```

**Generated Value Object:**
```php
final readonly class PostExcerpt
{
    private function __construct(
        private ?string $value
    ) {
        $this->validate($value);
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public static function fromNullableString(?string $value): static
    {
        return new self($value);
    }
}
```

## 🔢 Enum Support

### Creating Enums

Use the `enum[value1|value2|value3]` syntax:

```bash
php artisan domain-forge:domain Order --props="id:string,customer_name:string,status:enum[pending|processing|shipped|delivered|cancelled],total:float"
```

### Generated Enum Structure

**File:** `src/Contexts/Order/Domain/Enums/OrderStatus.php`

```php
<?php

namespace Src\Contexts\Order\Domain\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Get all possible enum values
     *
     * @return array<self>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::SHIPPED,
            self::DELIVERED,
            self::CANCELLED,
        ];
    }

    /**
     * Create enum from string value
     *
     * @throws \ValueError
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    /**
     * Create enum from nullable string value
     */
    public static function fromNullableString(?string $value): ?self
    {
        return $value !== null ? self::from($value) : null;
    }

    /**
     * Get string representation of the enum value
     */
    public function toString(): string
    {
        return $this->value;
    }
}
```

### Using Enums in Your Code

```php
// Create entity with enum
$order = Order::create(
    customer_name: OrderCustomerName::create('John Doe'),
    status: OrderStatus::PENDING,
    total: OrderTotal::create(99.99)
);

// From primitives
$order = Order::fromPrimitives(
    id: '123-456',
    customer_name: 'John Doe',
    status: 'pending',
    total: 99.99
);

// Access enum value
$status = $order->status(); // OrderStatus
$statusString = $order->status()->toString(); // 'pending'
$statusValue = $order->status()->value; // 'pending'

// Get all available statuses
$allStatuses = OrderStatus::all();

// Check specific status
if ($order->status() === OrderStatus::DELIVERED) {
    // Order delivered logic
}
```

### Enum Examples

```bash
# User roles
php artisan domain-forge:domain User --props="id:string,name:string,email:string,role:enum[admin|editor|viewer]"

# Product categories
php artisan domain-forge:domain Product --props="name:string,category:enum[electronics|clothing|food|books]"

# Ticket priority
php artisan domain-forge:domain Ticket --props="title:string,priority:enum[low|medium|high|urgent],status:enum[open|in_progress|resolved|closed]"
```

## 🆔 Auto-Generated IDs

### String IDs (UUID)

When you define an `id:string` property, Domain Forge automatically generates UUID methods:

```bash
php artisan domain-forge:domain User --props="id:string,name:string,email:string"
```

**Generated UserId Value Object:**
```php
final readonly class UserId
{
    private function __construct(
        private string $value
    ) {
        $this->validate($value);
    }

    /**
     * Generate a new unique ID.
     * By default uses UUID v4. Override to use ULID or other strategies.
     */
    public static function generate(): static
    {
        return new self(\Illuminate\Support\Str::uuid()->toString());
    }

    /**
     * Generate using ULID (uncomment if preferred)
     */
    // public static function generate(): static
    // {
    //     return new self(\Illuminate\Support\Str::ulid()->toString());
    // }

    public static function fromString(string $value): static
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
```

**Entity create() method excludes ID:**
```php
public static function create(
    UserName $name,
    UserEmail $email,
): static {
    return new self(
        id: UserId::generate(), // Auto-generated
        name: $name,
        email: $email,
    );
}
```

### Integer IDs (Auto-increment)

For database auto-increment IDs:

```bash
php artisan domain-forge:domain Product --props="id:int,name:string"
```

**Generated ProductId Value Object:**
```php
public static function generate(): static
{
    // This is a placeholder. Usually handled by database auto-increment
    throw new \RuntimeException('ID generation should be handled by the database');
}
```

## 🔐 Special Property Types

### Password Fields

Properties containing "password" get special hashing methods:

```bash
php artisan domain-forge:domain User --props="id:string,email:string,password:string"
```

**Generated UserPassword Value Object:**
```php
final readonly class UserPassword
{
    public static function fromHashed(string $hashedPassword): static
    {
        return new self($hashedPassword);
    }

    public static function hash(string $plainPassword): static
    {
        return new self(\Illuminate\Support\Facades\Hash::make($plainPassword));
    }

    public static function fromString(string $value): static
    {
        return new self($value);
    }
}
```

**Usage:**
```php
// Create with hashed password (from database)
$user = User::fromPrimitives(
    id: '123',
    email: 'user@example.com',
    password: '$2y$10$...' // Already hashed
);

// Hash plain password
$password = UserPassword::hash('my-plain-password');

// Create user with hashed password
$user = User::create(
    email: UserEmail::create('user@example.com'),
    password: UserPassword::hash('plain-password')
);
```

### Timestamp Fields

Properties ending with `_at` are treated as timestamps:

```bash
php artisan domain-forge:domain Post --props="id:string,title:string,created_at:string,published_at:?string"
```

These use `fromString()` or `fromNullableString()` methods automatically in `fromPrimitives()`.

## 🎛️ Command Flags

### `--props`

Define entity properties with their types:

```bash
php artisan domain-forge:domain Product --props="name:string,price:float,stock:int"
```

### `--skip-model`

Skip Laravel Eloquent model and migration generation:

```bash
php artisan domain-forge:domain User --props="name:string,email:string" --skip-model
```

**Use case:** When you don't need database persistence or already have the model.

## 🎨 Customization with Stubs

### Publishing Stubs

Publish stub templates to your project:

```bash
php artisan domain-forge:publish-stubs
```

This creates:
```
stubs/domain-forge/
├── entity.stub
├── entity-simple.stub
├── value-object.stub
├── enum.stub
├── repository-contract.stub
├── repository.stub
├── mapper.stub
├── service-provider.stub
└── routes.stub
```

### Customizing Stubs

Edit any stub file to change the generated code structure. For example, add timestamps to all entities:

**Edit:** `stubs/domain-forge/entity.stub`

```php
final readonly class {{ className }}
{
    private function __construct(
{{ constructorParams }}
        private \DateTime $createdAt,
        private ?\DateTime $updatedAt = null,
    ) {}

    public function createdAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

{{ getters }}

    // ... rest of the code
}
```

### Available Stub Variables

Each stub supports different replacement variables:

**entity.stub:**
- `{{ namespace }}` - Full namespace
- `{{ className }}` - Class name
- `{{ imports }}` - Value Object/Enum imports
- `{{ constructorParams }}` - Constructor parameters
- `{{ getters }}` - Getter methods
- `{{ createParams }}` - create() parameters
- `{{ createArgs }}` - create() arguments
- `{{ fromPrimitivesParams }}` - fromPrimitives() parameters
- `{{ fromPrimitivesArgs }}` - fromPrimitives() arguments

**value-object.stub:**
- `{{ namespace }}`
- `{{ className }}`
- `{{ type }}` - Property type
- `{{ additionalMethods }}` - Generated methods

**enum.stub:**
- `{{ namespace }}`
- `{{ className }}`
- `{{ cases }}` - Enum cases
- `{{ allMethod }}` - all() method
- `{{ fromStringMethod }}` - Conversion methods

### Force Overwrite Stubs

```bash
php artisan domain-forge:publish-stubs --force
```

## 🗺️ Generated Mappers

When generating a domain with properties (and without `--skip-model`), Domain Forge creates a mapper:

**File:** `src/Contexts/User/Infrastructure/Persistence/Mappers/UserMapper.php`

```php
<?php

namespace Src\Contexts\User\Infrastructure\Persistence\Mappers;

use App\Models\User as UserModel;
use Src\Contexts\User\Domain\Entities\User;

class UserMapper
{
    public static function toDomain(UserModel $model): User
    {
        return User::fromPrimitives(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            password: $model->password,
        );
    }

    public static function toEloquent(User $entity): array
    {
        return [
            'name' => $entity->name()->value(),
            'email' => $entity->email()->value(),
            'password' => $entity->password()->value(),
        ];
    }
}
```

**Usage in Repository:**
```php
public function findById(UserId $id): ?User
{
    $model = UserModel::find($id->value());
    
    return $model ? UserMapper::toDomain($model) : null;
}

public function save(User $user): void
{
    $data = UserMapper::toEloquent($user);
    
    UserModel::updateOrCreate(
        ['id' => $user->id()->value()],
        $data
    );
}
```

## 📖 Real-World Examples

### E-Commerce Product

```bash
php artisan domain-forge:domain Product --props="id:string,name:string,description:?string,price:float,stock:int,sku:string,category:enum[electronics|clothing|food|books],status:enum[available|out_of_stock|discontinued],created_at:string,updated_at:?string"
```

### User Management System

```bash
php artisan domain-forge:domain User --props="id:string,name:string,email:string,password:string,phone:?string,avatar:?string,role:enum[admin|manager|user|guest],status:enum[active|inactive|suspended|banned],email_verified_at:?string,created_at:string"
```

### Blog Post

```bash
php artisan domain-forge:domain Post --props="id:string,title:string,slug:string,content:string,excerpt:?string,author_id:string,category_id:?string,status:enum[draft|published|archived],published_at:?string,created_at:string,updated_at:?string"
```

### Support Ticket System

```bash
php artisan domain-forge:domain Ticket --props="id:string,title:string,description:string,user_id:string,assigned_to:?string,priority:enum[low|medium|high|critical],status:enum[open|in_progress|waiting|resolved|closed],created_at:string,updated_at:?string,closed_at:?string"
```

### Invoice System

```bash
php artisan domain-forge:domain Invoice --props="id:string,invoice_number:string,customer_id:string,amount:float,tax:float,total:float,status:enum[draft|sent|paid|overdue|cancelled],due_date:string,paid_at:?string,created_at:string"
```

## 🛡️ Error Handling & Rollback

Domain Forge includes automatic rollback on errors:

```bash
php artisan domain-forge:domain User --props="invalid prop format"
```

**Output:**
```
❌ Error: Property 'invalid prop format' doesn't have correct format
🔄 Rolling back changes...
   🗑️  Deleted: src/Contexts/User/Domain/Entities/User.php
   🗑️  Deleted directory: src/Contexts/User/Domain/Entities
   🗑️  Deleted directory: src/Contexts/User/Domain
✅ Rollback completed.
```

### Permission Validation

Before generating, Domain Forge checks:
- Write permissions on `src/Contexts/`
- Write permissions on `bootstrap/providers.php`
- Directory creation capabilities

**Example error:**
```
❌ No write permissions for: /path/to/src/Contexts
   Run: chmod -R 755 /path/to/src/Contexts
```

## 📊 Generation Summary

After successful generation, you'll see a detailed summary:

```
🚀 Creating domain module: User

📋 Creation Summary:

Directories:
┌────────┬──────────────────────────────────────────┐
│ Status │ Path                                     │
├────────┼──────────────────────────────────────────┤
│  ✅    │ src/Contexts/User/Application           │
│  ✅    │ src/Contexts/User/Domain                │
│  ✅    │ src/Contexts/User/Domain/Entities       │
│  ✅    │ src/Contexts/User/Domain/ValueObjects   │
│  ✅    │ src/Contexts/User/Domain/Enums          │
│  ✅    │ src/Contexts/User/Infrastructure        │
└────────┴──────────────────────────────────────────┘

Files:
┌────────┬──────────────────────────────────────────────────────────┐
│ Status │ Path                                                     │
├────────┼──────────────────────────────────────────────────────────┤
│  ✅    │ src/Contexts/User/Domain/Entities/User.php              │
│  ✅    │ src/Contexts/User/Domain/ValueObjects/UserId.php        │
│  ✅    │ src/Contexts/User/Domain/ValueObjects/UserName.php      │
│  ✅    │ src/Contexts/User/Domain/ValueObjects/UserEmail.php     │
│  ✅    │ src/Contexts/User/Domain/Enums/UserRole.php             │
│  ✅    │ src/Contexts/User/Infrastructure/UserServiceProvider.php │
└────────┴──────────────────────────────────────────────────────────┘

📊 Total items created: 42

✅ Domain User created successfully!
```

## 🔧 Advanced Usage

### Foreign Keys

Use `*_id` properties for foreign keys (they won't auto-generate):

```bash
php artisan domain-forge:domain Post --props="id:string,title:string,author_id:string,category_id:int"
```

- `author_id:string` → Uses `fromString()` (no `generate()`)
- `category_id:int` → Uses `fromInt()` (no `generate()`)

### Combining Features

```bash
php artisan domain-forge:domain Order \
  --props="id:string,customer_id:string,status:enum[pending|paid|shipped],total:float,notes:?string,created_at:string,paid_at:?string" \
  --skip-model
```

This creates:
- ✅ Auto-generated `id`
- ✅ Foreign key `customer_id` (no generation)
- ✅ Enum `status`
- ✅ Nullable `notes` and `paid_at`
- ✅ Timestamp `created_at`
- ❌ No Eloquent model

## 📝 Best Practices

### 1. Property Naming

```bash
# ✅ GOOD - snake_case
--props="first_name:string,created_at:string"

# ❌ BAD - camelCase or PascalCase
--props="firstName:string,CreatedAt:string"
```

### 2. Enum Values

```bash
# ✅ GOOD - lowercase, snake_case
--props="status:enum[pending|in_progress|completed]"

# ❌ BAD - uppercase or mixed case
--props="status:enum[PENDING|InProgress]"
```

### 3. Nullable Usage

Use nullable only when truly optional:

```bash
# User email is required, phone is optional
--props="email:string,phone:?string"
```

### 4. ID Strategy

Choose consistent ID strategy per project:

```bash
# UUID for distributed systems
--props="id:string"

# Auto-increment for traditional apps
--props="id:int"
```

## 🎯 Domain-Driven Design Tips

### Aggregates

Create separate domains for aggregates:

```bash
# Order aggregate
php artisan domain-forge:domain Order --props="id:string,customer_id:string,total:float,status:enum[pending|paid]"

# OrderItem is part of Order aggregate
php artisan domain-forge:domain OrderItem --props="id:string,order_id:string,product_id:string,quantity:int,price:float"
```

### Value Objects

Rich domain models emerge from proper value objects:

```php
// Instead of primitive obsession
$user->email = 'invalid-email'; // No validation!

// Use Value Objects
$user->updateEmail(UserEmail::create('new@example.com')); // Validated!
```

### Domain Events

Add events to your domain entities (manual step):

```php
final readonly class Order
{
    private array $domainEvents = [];

    public function markAsPaid(): void
    {
        $this->status = OrderStatus::PAID;
        $this->domainEvents[] = new OrderPaidEvent($this);
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
```

## 🐛 Troubleshooting

### Error: "No write permissions"

```bash
chmod -R 755 src/Contexts
chmod 755 bootstrap/providers.php
```

### Error: "Class not found"

```bash
composer dump-autoload
```

### Enum ValueError

```php
// ❌ Wrong
OrderStatus::fromString('PENDING'); // ValueError

// ✅ Correct
OrderStatus::fromString('pending');
```

### Property validation errors

Ensure property names follow rules:
- Start with lowercase letter
- Only alphanumeric and underscores
- No spaces or special characters

## 🚧 Roadmap

### v3.1 (Planned)
- [ ] `--dry-run` flag for preview
- [ ] Duplicate module detection
- [ ] Controller generation
- [ ] DTO generation
- [ ] Smart validations in ValueObjects

### v3.2 (Planned)
- [ ] Test generation
- [ ] Event generation
- [ ] Command/Query handlers
- [ ] GraphQL support

### Future
- [ ] Multi-language support
- [ ] Custom templates per project
- [ ] Migration generator from props
- [ ] Interactive mode

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/yntech/domain-forge.git
cd domain-forge
composer install
```

### Running Tests

```bash
composer test
```

## 📄 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## 🙏 Credits

Created and maintained by [Yntech](https://github.com/yntech).

## 📞 Support

- 📧 Email: support@yntech.com
- 🐛 Issues: [GitHub Issues](https://github.com/yntech/domain-forge/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/yntech/domain-forge/discussions)

## ⭐ Show Your Support

If you find this package helpful, please consider giving it a ⭐ on [GitHub](https://github.com/yntech/domain-forge)!

---

Made with ❤️ by Yntech