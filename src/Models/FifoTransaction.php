<?php

declare(strict_types=1);

namespace LaravelFifo\Models;

use Database\Factories\FifoTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $product_id
 * @property string $type
 * @property float $quantity
 * @property float $unit_price
 * @property float $total_amount
 * @property Carbon $transaction_date
 * @property string|null $reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<FifoTransaction> where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static static create(array<string, mixed> $attributes = [])
 */
final class FifoTransaction extends Model
{
    /** @use HasFactory<FifoTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'unit_price',
        'total_amount',
        'transaction_date',
        'reference',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /** @return BelongsTo<Model, $this> */
    public function product(): BelongsTo
    {
        $productModel = config('fifo.product_model');

        if (! $productModel || ! is_string($productModel)) {
            throw new InvalidArgumentException(
                'Product model not configured. Please set the fifo.product_model configuration value.'
            );
        }

        /** @var class-string<Model> $productModel */
        return $this->belongsTo($productModel);
    }

    public function isInbound(): bool
    {
        return $this->type === 'in';
    }

    public function isOutbound(): bool
    {
        return $this->type === 'out';
    }

    protected static function newFactory(): FifoTransactionFactory
    {
        return FifoTransactionFactory::new();
    }
}
