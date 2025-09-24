<?php

declare(strict_types=1);

namespace LaravelFifo\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class InstallCommand extends Command
{
    protected $signature = 'fifo:install {--force : Overwrite existing files}';

    protected $description = 'Install Laravel FIFO package migrations and configuration';

    public function handle(): int
    {
        $this->info('Installing Laravel FIFO package...');

        $this->publishMigrations();
        $this->publishConfig();

        $this->info('Laravel FIFO package installed successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->info('1. Configure your Product model in config/fifo.php');
        $this->info('2. Set FIFO_PRODUCT_MODEL environment variable');
        $this->info('3. Run migrations: php artisan migrate');

        return self::SUCCESS;
    }

    private function publishMigrations(): void
    {
        $this->info('Publishing migrations...');

        $filesystem = new Filesystem();
        $sourcePath = __DIR__.'/../../database/migrations/create_fifo_transactions_table.php';

        // Generate dynamic timestamp
        $timestamp = date('Y_m_d_His');
        $migrationName = "{$timestamp}_create_fifo_transactions_table.php";
        $destinationPath = database_path("migrations/{$migrationName}");

        if (!$filesystem->exists($destinationPath)) {
            // Check if migration already exists with different timestamp
            $existingMigrations = glob(database_path('migrations/*_create_fifo_transactions_table.php'));

            if (!empty($existingMigrations) && !$this->option('force')) {
                $this->warn('! FIFO migration already exists. Use --force to overwrite.');
                return;
            }

            if ($filesystem->exists($sourcePath)) {
                $filesystem->copy($sourcePath, $destinationPath);
                $this->info("✓ Migration created: {$migrationName}");
            } else {
                $this->error('! Source migration file not found');
            }
        } else {
            $this->warn('! Migration already exists with this timestamp');
        }
    }

    private function publishConfig(): void
    {
        $this->info('Publishing configuration...');

        $packageConfig = __DIR__.'/../../config/fifo.php';
        $appConfig = config_path('fifo.php');

        $filesystem = new Filesystem();

        if ($filesystem->exists($packageConfig)) {
            if ($filesystem->exists($appConfig) && ! $this->option('force')) {
                $this->warn('! Configuration file already exists. Use --force to overwrite.');

                return;
            }

            $filesystem->copy($packageConfig, $appConfig);
            $this->info('✓ Configuration published');
        }
    }
}
