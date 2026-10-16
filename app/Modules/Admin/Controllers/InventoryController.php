<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Admin\Services\InventoryService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class InventoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly InventoryService $inventoryService) {}

    // GET /api/v1/admin/inventory/summary
    public function summary(): JsonResponse
    {
        return $this->success($this->inventoryService->getStockSummary());
    }

    // GET /api/v1/admin/inventory/low-stock
    public function lowStock(Request $request): JsonResponse
    {
        return $this->success(
            $this->inventoryService->getLowStockAlerts($request->per_page ?? 20)
        );
    }

    // GET /api/v1/admin/inventory/out-of-stock
    public function outOfStock(Request $request): JsonResponse
    {
        return $this->success(
            $this->inventoryService->getOutOfStock($request->per_page ?? 20)
        );
    }

    // PATCH /api/v1/admin/inventory/{variantId}/stock
    public function adjustStock(Request $request, string $variantId): JsonResponse
    {
        $request->validate([
            'quantity'  => ['required', 'integer', 'min:0'],
            'operation' => ['required', 'in:set,increment,decrement'],
            'reason'    => ['required', 'string', 'max:255'],
        ]);

        $variant = $this->inventoryService->adjustStock(
            variantId : $variantId,
            quantity  : $request->quantity,
            operation : $request->operation,
            reason    : $request->reason,
            actorId   : $request->user()->id,
        );

        return $this->success($variant, 'Stok berhasil diperbarui.');
    }

    // POST /api/v1/admin/inventory/bulk-import
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'rows'               => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.sku'         => ['required', 'string'],
            'rows.*.quantity'    => ['required', 'integer', 'min:0'],
            'rows.*.operation'   => ['nullable', 'in:set,increment,decrement'],
            'rows.*.reason'      => ['nullable', 'string', 'max:255'],
        ]);

        $results = $this->inventoryService->bulkImportStock(
            $request->rows,
            $request->user()->id,
        );

        return $this->success($results, "Import selesai: {$results['success']} berhasil, {$results['failed']} gagal.");
    }
}
