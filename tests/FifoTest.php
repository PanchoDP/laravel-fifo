<?php

declare(strict_types=1);

use LaravelFifo\Fifo;
use LaravelFifo\Models\FifoTransaction;
use LaravelFifo\Test\Models\Product;

beforeEach(function (): void {
    $this->fifo = new Fifo();
    $this->product = Product::factory()->create();
});

describe('registerInbound', function (): void {
    it('can register inbound transactions', function (): void {
        $result = $this->fifo->registerInbound($this->product->id, 100, 15.50, 'TEST-IN-001');

        expect($result)->toBeTrue()
            ->and(FifoTransaction::query()->count())->toBe(1);

        $transaction = FifoTransaction::query()->first();
        expect($transaction->product_id)->toBe($this->product->id)
            ->and($transaction->type)->toBe('in')
            ->and($transaction->quantity)->toBe('100.00')
            ->and($transaction->unit_price)->toBe('15.50')
            ->and($transaction->total_amount)->toBe('1550.00')
            ->and($transaction->reference)->toBe('TEST-IN-001');
    });

    it('generates automatic reference when not provided', function (): void {
        $this->fifo->registerInbound($this->product->id, 50, 10.00);

        $transaction = FifoTransaction::query()->first();
        expect($transaction->reference)->toStartWith('IN-');
    });

    it('rejects invalid quantities', function (): void {
        $result1 = $this->fifo->registerInbound($this->product->id, 0, 15.50);
        $result2 = $this->fifo->registerInbound($this->product->id, -10, 15.50);

        expect($result1)->toBeFalse()
            ->and($result2)->toBeFalse()
            ->and(FifoTransaction::query()->count())->toBe(0);
    });

    it('rejects invalid prices', function (): void {
        $result1 = $this->fifo->registerInbound($this->product->id, 100, 0);
        $result2 = $this->fifo->registerInbound($this->product->id, 100, -5.50);

        expect($result1)->toBeFalse()
            ->and($result2)->toBeFalse()
            ->and(FifoTransaction::query()->count())->toBe(0);
    });
});

describe('getAvailableStock', function (): void {
    it('calculates stock correctly with no transactions', function (): void {
        $stock = $this->fifo->getAvailableStock($this->product->id);
        expect($stock)->toBe(0.0);
    });

    it('calculates stock correctly with only inbound transactions', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);
        $this->fifo->registerInbound($this->product->id, 50, 18.00);

        $stock = $this->fifo->getAvailableStock($this->product->id);
        expect($stock)->toBe(150.0);
    });

    it('calculates stock correctly with inbound and outbound transactions', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);
        $this->fifo->registerInbound($this->product->id, 50, 18.00);

        FifoTransaction::create([
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 30,
            'unit_price' => 15.50,
            'total_amount' => 465.0,
            'transaction_date' => now(),
            'reference' => 'TEST-OUT-001',
        ]);

        $stock = $this->fifo->getAvailableStock($this->product->id);
        expect($stock)->toBe(120.0);
    });
});

describe('fifoPrice', function (): void {
    it('returns stock insufficient for zero stock', function (): void {
        $price = $this->fifo->fifoPrice($this->product->id, 10);
        expect($price)->toBe('Insufficient stock');
    });

    it('returns 0.00 for zero quantity', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);
        $price = $this->fifo->fifoPrice($this->product->id, 0);
        expect($price)->toBe('0.00');
    });

    it('calculates FIFO price for single batch', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);
        $price = $this->fifo->fifoPrice($this->product->id, 50);
        expect($price)->toBe('15.50');
    });

    it('calculates FIFO price across multiple batches', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 10.00);
        $this->fifo->registerInbound($this->product->id, 50, 15.00);

        // Request 120 units: 100 at $10.00 + 20 at $15.00
        // Average: (100*10 + 20*15) / 120 = 1300/120 = 10.83
        $price = $this->fifo->fifoPrice($this->product->id, 120);
        expect($price)->toBe('10.83');
    });

    it('respects FIFO order with outbound transactions', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 10.00);
        $this->fifo->registerInbound($this->product->id, 50, 15.00);

        // Register outbound of 70 units (should consume first batch partially)
        FifoTransaction::query()->create([
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 70,
            'unit_price' => 10.00,
            'total_amount' => 700.0,
            'transaction_date' => now(),
            'reference' => 'TEST-OUT-001',
        ]);

        // Remaining: 30 at $10.00 + 50 at $15.00
        // Request 40 units: 30 at $10.00 + 10 at $15.00
        // Average: (30*10 + 10*15) / 40 = 450/40 = 11.25
        $price = $this->fifo->fifoPrice($this->product->id, 40);
        expect($price)->toBe('11.25');
    });
});

describe('registerOutbound', function (): void {
    it('registers outbound transaction with FIFO price', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);
        $result = $this->fifo->registerOutbound($this->product->id, 30, 'TEST-OUT-001');

        expect($result['success'])->toBeTrue()
            ->and($result['fifo_price'])->toBe('15.50')
            ->and($result)->toHaveKey('transaction_id');

        $transaction = FifoTransaction::query()->where('type', 'out')->first();
        expect($transaction->product_id)->toBe($this->product->id)
            ->and($transaction->quantity)->toBe('30.00')
            ->and($transaction->unit_price)->toBe('15.50')
            ->and($transaction->total_amount)->toBe('465.00')
            ->and($transaction->reference)->toBe('TEST-OUT-001');
    });

    it('generates automatic reference when not provided', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);
        $result = $this->fifo->registerOutbound($this->product->id, 30);

        expect($result['success'])->toBeTrue();

        $transaction = FifoTransaction::query()->where('type', 'out')->first();
        expect($transaction->reference)->toStartWith('OUT-');
    });

    it('rejects outbound when insufficient stock', function (): void {
        $this->fifo->registerInbound($this->product->id, 50, 15.50);
        $result = $this->fifo->registerOutbound($this->product->id, 100);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toBe('Insufficient stock available');
    });

    it('rejects invalid quantities', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);
        $result = $this->fifo->registerOutbound($this->product->id, 0);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toBe('Quantity must be greater than zero');
    });

    it('calculates complex FIFO pricing correctly', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 10.00);
        $this->fifo->registerInbound($this->product->id, 50, 20.00);

        // Request 120 units: 100 at $10.00 + 20 at $20.00
        // Average: (100*10 + 20*20) / 120 = 1400/120 = 11.67
        $result = $this->fifo->registerOutbound($this->product->id, 120);

        expect($result['success'])->toBeTrue()
            ->and($result['fifo_price'])->toBe('11.67');

        $transaction = FifoTransaction::query()->where('type', 'out')->first();
        expect($transaction->unit_price)->toBe('11.67')
            ->and($transaction->total_amount)->toBe('1400.40');
        // 120 * 11.67
    });
});

describe('getCurrentInventoryValue', function (): void {
    it('returns zero value for no inventory', function (): void {
        $value = $this->fifo->getCurrentInventoryValue($this->product->id);
        expect($value)->toBe(0.0);
    });

    it('calculates value for single batch', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 15.50);

        $value = $this->fifo->getCurrentInventoryValue($this->product->id);
        expect($value)->toBe(1550.0); // 100 * 15.50
    });

    it('calculates value for multiple batches', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 10.00);
        $this->fifo->registerInbound($this->product->id, 50, 20.00);

        $value = $this->fifo->getCurrentInventoryValue($this->product->id);
        expect($value)->toBe(2000.0); // 100*10 + 50*20
    });

    it('calculates value correctly after outbound transactions', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 10.00);
        $this->fifo->registerInbound($this->product->id, 50, 20.00);

        // Create outbound transaction of 70 units (consumes first batch partially)
        FifoTransaction::create([
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 70,
            'unit_price' => 10.00,
            'total_amount' => 700.0,
            'transaction_date' => now(),
            'reference' => 'TEST-OUT-001',
        ]);

        // Remaining: 30 units at $10.00 + 50 units at $20.00 = 30*10 + 50*20 = 1300
        $value = $this->fifo->getCurrentInventoryValue($this->product->id);
        expect($value)->toBe(1300.0);
    });

    it('calculates value correctly when first batch is completely consumed', function (): void {
        $this->fifo->registerInbound($this->product->id, 100, 10.00);
        $this->fifo->registerInbound($this->product->id, 50, 20.00);

        // Create outbound transaction of 120 units (consumes first batch completely and second partially)
        FifoTransaction::query()->create([
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 120,
            'unit_price' => 11.67,
            'total_amount' => 1400.4,
            'transaction_date' => now(),
            'reference' => 'TEST-OUT-001',
        ]);

        // Remaining: 30 units at $20.00 = 30*20 = 600
        $value = $this->fifo->getCurrentInventoryValue($this->product->id);
        expect($value)->toBe(600.0);
    });

});
