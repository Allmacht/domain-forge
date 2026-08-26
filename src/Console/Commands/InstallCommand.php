<?php

namespace Yntech\DomainForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
	protected $signature = 'domain-forge:install
                            {--force : Overwrite existing files}';

	protected $description = 'Install Domain Forge base structure and shared kernel';

	public function __construct(
		private readonly Filesystem $filesystem
	) {
		parent::__construct();
	}

	public function handle(): int
	{
		$this->components->info('Installing Domain Forge...');

		$this->createBaseDirectories();
		$this->publishConfig();
		$this->createSharedKernel();
		$this->registerSharedServiceProvider();

		$this->newLine();
		$this->components->info('Domain Forge installed successfully!');

		$this->components->bulletList([
			'Base structure created in <fg=cyan>src/</>',
			'Shared kernel ready (TransactionManager, Uuid, Ulid)',
			'Config published',
		]);

		$this->newLine();
		$this->line('Next steps:');
		$this->line('  1. Add to your <fg=yellow>composer.json</> autoload:');
		$this->line('     <fg=cyan>"Src\\\\": "src/"</>');
		$this->line('  2. Run: <fg=yellow>composer dump-autoload</>');
		$this->line('  3. Generate your first domain:');
		$this->line('     <fg=yellow>php artisan domain-forge:domain Product --props="id:uuidv7,name:string,price:float"</>');

		return self::SUCCESS;
	}

	private function createBaseDirectories(): void
	{
		$directories = [
			'src',
			'src/Shared',
			'src/Shared/Application',
			'src/Shared/Domain',
			'src/Shared/Domain/Contracts',
			'src/Shared/Domain/Support',
			'src/Shared/Domain/ValueObjects',
			'src/Shared/Domain/Exceptions',
			'src/Shared/Infrastructure',
			'src/Shared/Infrastructure/Persistence',
			'src/Shared/Infrastructure/Persistence/Eloquent',
			'src/Contexts',
		];

		$created = 0;

		foreach ($directories as $directory) {
			$path = base_path($directory);

			if (!$this->filesystem->isDirectory($path)) {
				$this->filesystem->makeDirectory($path, 0755, true);
				$created++;
			}
		}

		if ($created > 0) {
			$this->components->twoColumnDetail('Directories', "<fg=green>Created {$created} directories</>");
		} else {
			$this->components->twoColumnDetail('Directories', '<fg=gray>Already exist</>');
		}
	}

	private function publishConfig(): void
	{
		$configPath = config_path('domain-forge.php');

		if ($this->filesystem->exists($configPath) && !$this->option('force')) {
			$this->components->twoColumnDetail('Config', '<fg=gray>Already exists</>');
			return;
		}

		$this->callSilent('vendor:publish', [
			'--provider' => 'Yntech\\DomainForge\\Providers\\DomainForgeServiceProvider',
			'--tag' => 'config',
			'--force' => true,
		]);

		$this->components->twoColumnDetail('Config', '<fg=green>Published</>');
	}

	private function createSharedKernel(): void
	{
		$files = [
			'src/Shared/Domain/Contracts/TransactionManagerInterface.php' => $this->transactionManagerInterfaceStub(),
			'src/Shared/Infrastructure/Persistence/Eloquent/LaravelTransactionManager.php' => $this->laravelTransactionManagerStub(),
			'src/Shared/Domain/Support/Uuid.php' => $this->uuidStub(),
			'src/Shared/Domain/Support/Ulid.php' => $this->ulidStub(),
			'src/Shared/Infrastructure/SharedServiceProvider.php' => $this->sharedServiceProviderStub(),
		];

		foreach ($files as $relativePath => $content) {
			$path = base_path($relativePath);

			if ($this->filesystem->exists($path) && !$this->option('force')) {
				$this->components->twoColumnDetail(basename($path), '<fg=gray>Already exists</>');
				continue;
			}

			$this->filesystem->ensureDirectoryExists(dirname($path));
			$this->filesystem->put($path, $content);

			$this->components->twoColumnDetail(basename($path), '<fg=green>Created</>');
		}
	}

	private function registerSharedServiceProvider(): void
	{
		$providersPath = base_path('bootstrap/providers.php');

		if (!$this->filesystem->exists($providersPath)) {
			$this->components->warn('bootstrap/providers.php not found. Please register SharedServiceProvider manually.');
			return;
		}

		$providerClass = 'Src\\Shared\\Infrastructure\\SharedServiceProvider::class';
		$content = $this->filesystem->get($providersPath);

		if (str_contains($content, $providerClass)) {
			$this->components->twoColumnDetail('SharedServiceProvider', '<fg=gray>Already registered</>');
			return;
		}

		$content = str_replace(
			'return [',
			"return [\n    {$providerClass},",
			$content
		);

		$this->filesystem->put($providersPath, $content);
		$this->components->twoColumnDetail('SharedServiceProvider', '<fg=green>Registered</>');
	}

	private function transactionManagerInterfaceStub(): string
	{
		return <<<'PHP'
<?php

namespace Src\Shared\Domain\Contracts;

interface TransactionManagerInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function transaction(callable $callback): mixed;
}
PHP;
	}

	private function laravelTransactionManagerStub(): string
	{
		return <<<'PHP'
<?php

namespace Src\Shared\Infrastructure\Persistence\Eloquent;

use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Contracts\TransactionManagerInterface;

final class LaravelTransactionManager implements TransactionManagerInterface
{
    public function beginTransaction(): void
    {
        DB::beginTransaction();
    }

    public function commit(): void
    {
        DB::commit();
    }

    public function rollback(): void
    {
        DB::rollBack();
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
PHP;
	}

	private function uuidStub(): string
	{
		return <<<'PHP'
<?php

namespace Src\Shared\Domain\Support;

final class Uuid
{
    /**
     * UUID v4 (random)
     */
    public static function v4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        $hex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * UUID v7 (time-ordered)
     *
     * @see https://www.rfc-editor.org/rfc/rfc9562
     */
    public static function v7(): string
    {
        $timestamp = (int) (microtime(true) * 1000);
        $timeHex = str_pad(dechex($timestamp), 12, '0', STR_PAD_LEFT);

        $random = random_bytes(10);
        $random[0] = chr((ord($random[0]) & 0x0f) | 0x70);
        $random[2] = chr((ord($random[2]) & 0x3f) | 0x80);

        $hex = $timeHex . bin2hex($random);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
PHP;
	}

	private function ulidStub(): string
	{
		return <<<'PHP'
<?php

namespace Src\Shared\Domain\Support;

final class Ulid
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $time = (int) (microtime(true) * 1000);
        $timeChars = '';

        for ($i = 9; $i >= 0; $i--) {
            $timeChars = self::ENCODING[$time % 32] . $timeChars;
            $time = intdiv($time, 32);
        }

        $random = '';
        for ($i = 0; $i < 16; $i++) {
            $random .= self::ENCODING[random_int(0, 31)];
        }

        return $timeChars . $random;
    }
}
PHP;
	}

	private function sharedServiceProviderStub(): string
	{
		return <<<'PHP'
<?php

namespace Src\Shared\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Contracts\TransactionManagerInterface;
use Src\Shared\Infrastructure\Persistence\Eloquent\LaravelTransactionManager;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TransactionManagerInterface::class,
            LaravelTransactionManager::class
        );
    }

    public function boot(): void
    {
        //
    }
}
PHP;
	}
}