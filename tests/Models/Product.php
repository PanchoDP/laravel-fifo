<?php

declare(strict_types=1);

namespace LaravelFifo\Test\Models;

use LaravelFifo\Test\Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
