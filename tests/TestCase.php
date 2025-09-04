<?php

declare(strict_types=1);

namespace LaravelFifo\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelFifo\Providers\LaravelFifoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelFifoServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Configure the product model for tests
        $app['config']->set('fifo.product_model', \LaravelFifo\Test\Models\Product::class);
    }
}
