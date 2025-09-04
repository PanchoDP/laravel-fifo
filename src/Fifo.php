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
            return 'Product not found';
        }

        $availableStock = $this->getAvailableStock($productId);

        if ($quantity > $availableStock) {
            return 'Insufficient stock';
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

        // Validate decimal precision to prevent overflow attacks
        if (! $this->validateDecimalPrecision($quantity) || ! $this->validateDecimalPrecision($unitPrice)) {
            return false;
        }

        // Sanitize reference field
        if ($reference !== null) {
            $reference = $this->sanitizeReference($reference);
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
            return ['success' => false, 'error' => 'Product not found'];
        }

        if ($quantity <= 0) {
            return ['success' => false, 'error' => 'Quantity must be greater than zero'];
        }

        // Validate decimal precision to prevent overflow attacks
        if (! $this->validateDecimalPrecision($quantity)) {
            return ['success' => false, 'error' => 'Invalid quantity precision'];
        }

        $availableStock = $this->getAvailableStock($productId);
        if ($quantity > $availableStock) {
            return ['success' => false, 'error' => 'Insufficient stock available'];
        }

        $fifoPrice = $this->fifoPrice($productId, $quantity);
        if ($fifoPrice === 'Insufficient stock') {
            return ['success' => false, 'error' => 'Error calculating FIFO price'];
        }

        // Sanitize reference field
        if ($reference !== null) {
            $reference = $this->sanitizeReference($reference);
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
            return ['success' => false, 'error' => 'Product not found'];
        }

        // Check if product has transactions
        $transactionCount = FifoTransaction::query()->where('product_id', $productId)->count();

        if ($transactionCount > 0) {
            return [
                'success' => false,
                'error' => 'Cannot delete product with existing transactions',
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
            return ['success' => false, 'error' => 'Product not found'];
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
     * Sanitize reference field to prevent XSS and other attacks.
     */
    private function sanitizeReference(string $reference): string
    {
        // Remove HTML tags and potentially dangerous characters
        $reference = strip_tags($reference);

        // Remove or encode special characters that could be used for XSS
        $reference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');

        // Limit length to prevent buffer overflow attacks
        $reference = mb_substr($reference, 0, 255);

        // Remove null bytes and control characters
        $reference = preg_replace('/[\x00-\x1F\x7F]/', '', $reference);

        return mb_trim($reference ?? '');
    }

    /**
     * Validate the product model configuration.
     *
     * @throws Exception
     */
    private function validateProductModel(): void
    {
        $productModel = config('fifo.product_model');

        if (! $productModel || ! is_string($productModel)) {
            throw new Exception('Product model not configured properly. Please set FIFO_PRODUCT_MODEL environment variable.');
        }

        if (! class_exists($productModel)) {
            throw new Exception("Product model class '{$productModel}' does not exist.");
        }

        // Ensure the model extends Laravel's Model class
        if (! is_subclass_of($productModel, Model::class)) {
            throw new Exception("Product model '{$productModel}' must extend Illuminate\\Database\\Eloquent\\Model.");
        }

        // Check if the model has the required 'id' column by trying to instantiate it
        try {
            /** @var class-string<Model> $productModel */
            $modelInstance = new $productModel();
            if (! $modelInstance->getKeyName()) {
                throw new Exception("Product model '{$productModel}' must have a primary key defined.");
            }
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'must have a primary key')) {
                throw $e;
            }
            throw new Exception("Failed to validate product model '{$productModel}': ".$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Validate decimal precision to prevent overflow attacks.
     */
    private function validateDecimalPrecision(float $value): bool
    {
        // Check for reasonable decimal precision (max 10 digits, 2 decimal places)
        if ($value > 99999999.99) {
            return false;
        }

        // Check for too many decimal places (prevents precision attacks)
        $decimalString = number_format($value, 10, '.', '');
        $decimals = explode('.', $decimalString)[1] ?? '';
        $significantDecimals = mb_rtrim($decimals, '0');

        if (mb_strlen($significantDecimals) > 2) {
            return false;
        }

        // Check for negative infinity, positive infinity, or NaN
        return is_finite($value);
    }

    /**
     * Validate if the product exists in the configured product model.
     *
     * @throws Exception
     */
    private function validateProduct(int $productId): bool
    {
        $this->validateProductModel();

        $productModel = config('fifo.product_model');

        /** @var class-string<Model> $productModel */
        return $productModel::query()->where('id', $productId)->exists();
    }
}
