<?php

namespace App\Modules\Admin\Services;

use App\Modules\Product\Models\ProductVariant;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryService
{
    public function getLowStockAlerts(int $perPage = 20): LengthAwarePaginator
    {
        return ProductVariant::with(['product.primaryImage', 'product.category'])
            ->where('is_active', true)
            ->whereRaw('stock <= low_stock_threshold')
            ->orderBy('stock', 'asc')
            ->paginate($perPage);
    }

    public function getOutOfStock(int $perPage = 20): LengthAwarePaginator
    {
        return ProductVariant::with(['product.primaryImage', 'product.category'])
            ->where('is_active', true)
            ->where('stock', 0)
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function getStockSummary(): array
    {
        return Cache::remember('admin:inventory:summary', now()->addMinutes(10), function () {
            return DB::connection('pgsql')
                ->table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->where('pv.is_active', true)
                ->where('p.status', 'active')
                ->selectRaw('
                    COUNT(*)                                                          AS total_variants,
                    COUNT(*) FILTER (WHERE pv.stock = 0)                             AS out_of_stock,
                    COUNT(*) FILTER (WHERE pv.stock > 0 AND pv.stock <= pv.low_stock_threshold) AS low_stock,
                    COUNT(*) FILTER (WHERE pv.stock > pv.low_stock_threshold)        AS in_stock,
                    COALESCE(SUM(pv.stock), 0)                                       AS total_units,
                    COALESCE(SUM(pv.stock * pv.price), 0)                            AS stock_value
                ')
                ->first();
        });
    }

    public function adjustStock(
        string $variantId,
        int    $quantity,
        string $operation,
        string $reason,
        string $actorId
    ): ProductVariant {
        return DB::transaction(function () use ($variantId, $quantity, $operation, $reason, $actorId) {
            $variant = ProductVariant::with('product')
                ->lockForUpdate()
                ->findOrFail($variantId);

            $oldStock = $variant->stock;

            $newStock = match ($operation) {
                'set'       => max(0, $quantity),
                'increment' => $variant->stock + $quantity,
                'decrement' => max(0, $variant->stock - $quantity),
                default     => throw new \InvalidArgumentException("Invalid operation: {$operation}"),
            };

            $variant->update(['stock' => $newStock]);

            // Audit log stok
            activity()
                ->performedOn($variant)
                ->causedBy(\App\Modules\Auth\Models\User::find($actorId))
                ->withProperties([
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'operation' => $operation,
                    'quantity'  => $quantity,
                    'reason'    => $reason,
                ])
                ->log('stock_adjusted');

            Cache::tags(['products'])->flush();

            return $variant->fresh('product');
        });
    }

    public function bulkImportStock(array $rows, string $actorId): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($rows as $row) {
            try {
                $variant = ProductVariant::where('sku', $row['sku'])->first();

                if (! $variant) {
                    $results['errors'][] = "SKU tidak ditemukan: {$row['sku']}";
                    $results['failed']++;
                    continue;
                }

                $this->adjustStock(
                    $variant->id,
                    (int) $row['quantity'],
                    $row['operation'] ?? 'set',
                    $row['reason'] ?? 'Bulk import',
                    $actorId
                );

                $results['success']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "SKU {$row['sku']}: {$e->getMessage()}";
                $results['failed']++;
            }
        }

        return $results;
    }
}
