<?php

declare(strict_types=1);

namespace LaravelFifo\Test\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LaravelFifo\Test\Models\Product;

final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $categories = [
            'Electrónicos' => ['Laptop', 'Mouse', 'Teclado', 'Monitor', 'Auriculares', 'Smartphone', 'Tablet'],
            'Ropa' => ['Camiseta', 'Pantalón', 'Chaqueta', 'Zapatos', 'Gorra', 'Vestido'],
            'Hogar' => ['Mesa', 'Silla', 'Lámpara', 'Sofá', 'Estantería', 'Espejo'],
            'Deportes' => ['Pelota', 'Raqueta', 'Pesas', 'Cinta de correr', 'Bicicleta'],
            'Libros' => ['Novela', 'Manual técnico', 'Biografía', 'Enciclopedia'],
        ];

        $category = $this->faker->randomElement(array_keys($categories));
        $productName = $this->faker->randomElement($categories[$category]);

        return [
            'name' => $productName.' '.$this->faker->word(),
            'description' => $this->faker->sentence(10),
        ];
    }
}
