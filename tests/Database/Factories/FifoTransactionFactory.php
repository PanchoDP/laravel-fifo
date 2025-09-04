<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LaravelFifo\Models\FifoTransaction;
use LaravelFifo\Test\Models\Product;

final class FifoTransactionFactory extends Factory
{
    protected $model = FifoTransaction::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitPrice = $this->faker->randomFloat(2, 1, 500);

        return [
            'product_id' => Product::factory(),
            'type' => $this->faker->randomElement(['in', 'out']),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $quantity * $unitPrice,
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'reference' => $this->faker->optional()->regexify('[A-Z]{2}[0-9]{6}'),
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'in',
        ]);
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'out',
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_id' => $product->id,
        ]);
    }

    public function withReference(string $reference): static
    {
        return $this->state(fn (array $attributes): array => [
            'reference' => $reference,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transaction_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function old(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', '-30 days'),
        ]);
    }

    public function smallQuantity(): static
    {
        return $this->state(function (array $attributes): array {
            $quantity = $this->faker->randomFloat(2, 1, 10);

            return [
                'quantity' => $quantity,
                'total_amount' => $quantity * $attributes['unit_price'],
            ];
        });
    }

    public function largeQuantity(): static
    {
        return $this->state(function (array $attributes): array {
            $quantity = $this->faker->randomFloat(2, 100, 1000);

            return [
                'quantity' => $quantity,
                'total_amount' => $quantity * $attributes['unit_price'],
            ];
        });
    }
}
