<?php

declare(strict_types=1);

namespace LaravelFifo\Test\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use LaravelFifo\Fifo;
use LaravelFifo\Models\FifoTransaction;
use LaravelFifo\Test\Models\Product;

final class FifoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        foreach ($products->take(10) as $product) {
            $this->createFifoSequenceForProduct($product);
        }

        foreach ($products->skip(10)->take(5) as $product) {
            $this->createComplexFifoSequenceForProduct($product);
        }

        FifoTransaction::factory()
            ->count(50)
            ->recent()
            ->create();

        FifoTransaction::factory()
            ->count(30)
            ->old()
            ->smallQuantity()
            ->create();

        FifoTransaction::factory()
            ->count(20)
            ->largeQuantity()
            ->inbound()
            ->create();
    }

    private function createFifoSequenceForProduct(Product $product): void
    {
        $baseDate = Carbon::now()->subMonths(6);
        $fifo = new Fifo();

        FifoTransaction::factory()
            ->forProduct($product)
            ->inbound()
            ->state([
                'quantity' => 100,
                'unit_price' => 10.00,
                'total_amount' => 1000.00,
                'transaction_date' => $baseDate->copy(),
                'reference' => 'IN001',
            ])
            ->create();

        FifoTransaction::factory()
            ->forProduct($product)
            ->inbound()
            ->state([
                'quantity' => 50,
                'unit_price' => 12.00,
                'total_amount' => 600.00,
                'transaction_date' => $baseDate->copy()->addDays(10),
                'reference' => 'IN002',
            ])
            ->create();

        $fifoPrice1 = (float) $fifo->fifoPrice($product->id, 30);
        FifoTransaction::factory()
            ->forProduct($product)
            ->outbound()
            ->state([
                'quantity' => 30,
                'unit_price' => $fifoPrice1,
                'total_amount' => 30 * $fifoPrice1,
                'transaction_date' => $baseDate->copy()->addDays(20),
                'reference' => 'OUT001',
            ])
            ->create();

        $fifoPrice2 = (float) $fifo->fifoPrice($product->id, 80);
        FifoTransaction::factory()
            ->forProduct($product)
            ->outbound()
            ->state([
                'quantity' => 80,
                'unit_price' => $fifoPrice2,
                'total_amount' => 80 * $fifoPrice2,
                'transaction_date' => $baseDate->copy()->addDays(30),
                'reference' => 'OUT002',
            ])
            ->create();
    }

    private function createComplexFifoSequenceForProduct(Product $product): void
    {
        $baseDate = Carbon::now()->subMonths(3);
        $fifo = new Fifo();

        FifoTransaction::factory()
            ->forProduct($product)
            ->inbound()
            ->state([
                'quantity' => 200,
                'unit_price' => 15.00,
                'total_amount' => 3000.00,
                'transaction_date' => $baseDate->copy(),
                'reference' => 'IN001',
            ])
            ->create();

        FifoTransaction::factory()
            ->forProduct($product)
            ->inbound()
            ->state([
                'quantity' => 150,
                'unit_price' => 16.50,
                'total_amount' => 2475.00,
                'transaction_date' => $baseDate->copy()->addDays(5),
                'reference' => 'IN002',
            ])
            ->create();

        $fifoPrice1 = (float) $fifo->fifoPrice($product->id, 100);
        FifoTransaction::factory()
            ->forProduct($product)
            ->outbound()
            ->state([
                'quantity' => 100,
                'unit_price' => $fifoPrice1,
                'total_amount' => 100 * $fifoPrice1,
                'transaction_date' => $baseDate->copy()->addDays(10),
                'reference' => 'OUT001',
            ])
            ->create();

        FifoTransaction::factory()
            ->forProduct($product)
            ->inbound()
            ->state([
                'quantity' => 300,
                'unit_price' => 17.00,
                'total_amount' => 5100.00,
                'transaction_date' => $baseDate->copy()->addDays(15),
                'reference' => 'IN003',
            ])
            ->create();

        $fifoPrice2 = (float) $fifo->fifoPrice($product->id, 200);
        FifoTransaction::factory()
            ->forProduct($product)
            ->outbound()
            ->state([
                'quantity' => 200,
                'unit_price' => $fifoPrice2,
                'total_amount' => 200 * $fifoPrice2,
                'transaction_date' => $baseDate->copy()->addDays(20),
                'reference' => 'OUT002',
            ])
            ->create();

        $fifoPrice3 = (float) $fifo->fifoPrice($product->id, 150);
        FifoTransaction::factory()
            ->forProduct($product)
            ->outbound()
            ->state([
                'quantity' => 150,
                'unit_price' => $fifoPrice3,
                'total_amount' => 150 * $fifoPrice3,
                'transaction_date' => $baseDate->copy()->addDays(25),
                'reference' => 'OUT003',
            ])
            ->create();
    }
}
