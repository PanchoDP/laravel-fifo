<?php

declare(strict_types=1);

namespace LaravelFifo\Test\Database\Seeders;

use Illuminate\Database\Seeder;
use LaravelFifo\Test\Models\Product;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory()
            ->count(80)
            ->create();
    }
}
