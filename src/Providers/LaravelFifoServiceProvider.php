<?php

declare(strict_types=1);

namespace LaravelFifo\Providers;

use Illuminate\Support\ServiceProvider;

final class LaravelFifoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->publishes([
            __DIR__.'/../../database/migrations/2025-09-01_create_fifo_transactions_table.php' => database_path('migrations/2025-09-01_create_fifo_transactions_table.php'),
        ], 'laravel-fifo-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \LaravelFifo\Console\InstallCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        // You can bind your Fifo class here if needed
        $this->app->bind('fifo', fn(): \LaravelFifo\Fifo => new \LaravelFifo\Fifo());
        // Register facade alias
        $this->app->alias('fifo', \LaravelFifo\Facades\Fifo::class);

        // Merge package configuration
        $configPath = __DIR__.'/../../config/fifo.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'fifo');
        }

        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/fifo.php' => config_path('fifo.php'),
        ], 'laravel-fifo-config');
    }
}
