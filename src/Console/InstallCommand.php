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

        $packageMigrations = __DIR__.'/../../database/migrations';
        $appMigrations = database_path('migrations');

        $filesystem = new Filesystem();

        if ($filesystem->exists($packageMigrations)) {
            $filesystem->copyDirectory($packageMigrations, $appMigrations);
            $this->info('✓ Migrations published');
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
