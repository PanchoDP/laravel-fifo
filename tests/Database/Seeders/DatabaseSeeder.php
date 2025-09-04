<?php

declare(strict_types=1);

namespace LaravelFifo\Test\Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            FifoTransactionSeeder::class,
        ]);
    }
}
