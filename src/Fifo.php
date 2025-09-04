<?php

declare(strict_types=1);

namespace LaravelFifo;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LaravelFifo\Models\FifoTransaction;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;

final class Fifo
{
    public float $quantity;

    public float $price;

    public int $productId;

    /**
     * Calculate the FIFO price for a given product and quantity.
     *
     * @throws Exception
     */
    public function fifoPrice(int $productId, float $quantity): string
    {
        if (! $this->validateProduct($productId)) {
            return 'Producto no encontrado';
        }

        $availableStock = $this->getAvailableStock($productId);

        if ($quantity > $availableStock) {
            return 'Stock insuficiente';
        }

        if ($quantity <= 0) {
            return '0.00';
        }

        $stockByBatch = $this->calculateStockByBatch($productId);

        $totalCost = 0.0;
        $remainingQuantity = $quantity;

        foreach ($stockByBatch as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $usedQuantity = min($remainingQuantity, $batch['available_quantity']);
            $totalCost += $usedQuantity * $batch['unit_price'];
            $remainingQuantity -= $usedQuantity;
        }

        return number_format($totalCost / $quantity, 2);
    }

    /**
     * Get the available stock for a given product.
     */
    public function getAvailableStock(int $productId): float
    {
        $totalInValue = FifoTransaction::query()->where('product_id', $productId)
            ->where('type', 'in')
            ->sum('quantity');
        $totalIn = is_numeric($totalInValue) ? (float) $totalInValue : 0.0;

        $totalOutValue = FifoTransaction::query()->where('product_id', $productId)
            ->where('type', 'out')
            ->sum('quantity');
        $totalOut = is_numeric($totalOutValue) ? (float) $totalOutValue : 0.0;

        return $totalIn - $totalOut;
    }

    /**
     * Calculate stock by batch for a given product.
     *
     * @return array<int, array{transaction_id: int, unit_price: float, original_quantity: float, available_quantity: float, transaction_date: mixed}>
     */
    public function calculateStockByBatch(int $productId): array
    {
        /** @var Collection<int, FifoTransaction> $inboundTransactions */
        $inboundTransactions = FifoTransaction::query()->where('product_id', $productId)
            ->where('type', 'in')
            ->orderBy('transaction_date')
            ->get();

        /** @var Collection<int, FifoTransaction> $outboundTransactions */
        $outboundTransactions = FifoTransaction::query()->where('product_id', $productId)
            ->where('type', 'out')
            ->orderBy('transaction_date')
            ->get();

        $stockByBatch = [];

        foreach ($inboundTransactions as $transaction) {
            $stockByBatch[] = [
                'transaction_id' => $transaction->id,
                'unit_price' => (float) $transaction->unit_price,
                'original_quantity' => (float) $transaction->quantity,
                'available_quantity' => (float) $transaction->quantity,
                'transaction_date' => $transaction->transaction_date,
            ];
        }

        foreach ($outboundTransactions as $outbound) {
            $remainingToDeduct = (float) $outbound->quantity;

            for ($i = 0; $i < count($stockByBatch) && $remainingToDeduct > 0; $i++) {
                if ($stockByBatch[$i]['available_quantity'] > 0) {
                    $deductAmount = min($remainingToDeduct, $stockByBatch[$i]['available_quantity']);
                    $stockByBatch[$i]['available_quantity'] -= $deductAmount;
                    $remainingToDeduct -= $deductAmount;
                }
            }
        }

        return array_filter($stockByBatch, fn (array $batch): bool => $batch['available_quantity'] > 0);
    }

    /**
     * Register an inbound transaction.
     */
    public function registerInbound(int $productId, float $quantity, float $unitPrice, ?string $reference = null): bool
    {
        if (! $this->validateProduct($productId)) {
            return false;
        }

        if ($quantity <= 0 || $unitPrice <= 0) {
            return false;
        }

        try {
            FifoTransaction::query()->create([
                'product_id' => $productId,
                'type' => 'in',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $quantity * $unitPrice,
                'transaction_date' => now(),
                'reference' => $reference ?? 'IN-'.time(),
            ]);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get the current inventory value for a given product.
     */
    public function getCurrentInventoryValue(int $productId): float
    {
        $stockByBatch = $this->calculateStockByBatch($productId);

        $totalValue = 0.0;

        foreach ($stockByBatch as $batch) {
            $totalValue += $batch['available_quantity'] * $batch['unit_price'];
        }

        return $totalValue;
    }

    /**
     * Register an outbound transaction.
     *
     * @return array{success: bool, error?: string, transaction_id?: int, fifo_price?: string}
     *
     * @throws Exception
     */
    public function registerOutbound(int $productId, float $quantity, ?string $reference = null): array
    {
        if (! $this->validateProduct($productId)) {
            return ['success' => false, 'error' => 'Producto no encontrado'];
        }

        if ($quantity <= 0) {
            return ['success' => false, 'error' => 'Cantidad debe ser mayor a 0'];
        }

        $availableStock = $this->getAvailableStock($productId);
        if ($quantity > $availableStock) {
            return ['success' => false, 'error' => "Stock insuficiente. Disponible: {$availableStock}, Solicitado: {$quantity}"];
        }

        $fifoPrice = $this->fifoPrice($productId, $quantity);
        if ($fifoPrice === 'Stock insuficiente') {
            return ['success' => false, 'error' => 'Error calculando precio FIFO'];
        }

        try {
            /** @var FifoTransaction $transaction */
            $transaction = FifoTransaction::query()->create([
                'product_id' => $productId,
                'type' => 'out',
                'quantity' => $quantity,
                'unit_price' => (float) $fifoPrice,
                'total_amount' => $quantity * (float) $fifoPrice,
                'transaction_date' => now(),
                'reference' => $reference ?? 'OUT-'.time(),
            ]);

            return ['success' => true, 'transaction_id' => $transaction->id, 'fifo_price' => $fifoPrice];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a product if it has no associated transactions.
     *
     * @return array{success: bool, error?: string, deleted_transactions?: int}
     *
     * @throws Exception
     */
    public function deleteProduct(int $productId): array
    {
        if (! $this->validateProduct($productId)) {
            return ['success' => false, 'error' => 'Producto no encontrado'];
        }

        // Check if product has transactions
        $transactionCount = FifoTransaction::query()->where('product_id', $productId)->count();

        if ($transactionCount > 0) {
            return [
                'success' => false,
                'error' => "No se puede eliminar el producto. Tiene {$transactionCount} transacciones asociadas",
            ];
        }

        try {
            $productModel = config('fifo.product_model');

            if (! $productModel || ! is_string($productModel) || ! class_exists($productModel)) {
                return ['success' => false, 'error' => 'Product model not configured properly. Please set FIFO_PRODUCT_MODEL environment variable.'];
            }

            /** @var class-string<Model> $productModel */
            $product = $productModel::query()->findOrFail($productId);
            $product->delete();

            return ['success' => true, 'deleted_transactions' => 0];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Force delete a product and all its associated transactions.
     *
     * @return array{success: bool, error?: string, deleted_transactions?: int}
     *
     * @throws Exception
     */
    public function forceDeleteProduct(int $productId): array
    {
        if (! $this->validateProduct($productId)) {
            return ['success' => false, 'error' => 'Producto no encontrado'];
        }

        try {
            // Get transaction count before deletion
            $transactionCount = FifoTransaction::query()->where('product_id', $productId)->count();

            // Delete all transactions first
            FifoTransaction::query()->where('product_id', $productId)->delete();

            // Then delete the product
            $productModel = config('fifo.product_model');

            if (! $productModel || ! is_string($productModel) || ! class_exists($productModel)) {
                return ['success' => false, 'error' => 'Product model not configured properly. Please set FIFO_PRODUCT_MODEL environment variable.'];
            }

            /** @var class-string<Model> $productModel */
            $product = $productModel::query()->findOrFail($productId);
            $product->delete();

            return ['success' => true, 'deleted_transactions' => $transactionCount];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all transactions for a given product.
     *
     * @return Collection<int, FifoTransaction>
     */
    public function getTransactions(int $productId): Collection
    {
        /** @var Collection<int, FifoTransaction> $transactions */
        $transactions = FifoTransaction::query()->where('product_id', $productId)
            ->orderBy('transaction_date')
            ->get();

        return $transactions;
    }

    /**
     * Validate if the product exists in the configured product model.
     *
     * @throws Exception
     */
    private function validateProduct(int $productId): bool
    {
        $productModel = config('fifo.product_model');

        if (! $productModel || ! is_string($productModel) || ! class_exists($productModel)) {
            throw new Exception('Product model not configured properly. Please set FIFO_PRODUCT_MODEL environment variable.');
        }

        /** @var class-string<Model> $productModel */
        return $productModel::query()->where('id', $productId)->exists();
    }
}
