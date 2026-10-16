<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Admin\Services\ReportService;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ReportService $reportService) {}

    // GET /api/v1/admin/reports/revenue
    public function revenue(Request $request): JsonResponse
    {
        // 1. Tampung ke $validated
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by'  => ['nullable', 'in:day,week,month'],
            'user_type' => ['nullable', 'in:consumer,umkm,distributor'],
        ]);

        // 2. Gunakan variabel $validated
        return $this->success(
            $this->reportService->getRevenueReport($validated)
        );
    }

    // GET /api/v1/admin/reports/products
    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->success(
            $this->reportService->getProductSalesReport($validated)
        );
    }

    // GET /api/v1/admin/reports/users
    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'user_type' => ['nullable', 'in:consumer,umkm,distributor'],
            'status'    => ['nullable', 'in:active,inactive,suspended'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->success(
            $this->reportService->getUserReport($validated)
        );
    }

    // GET /api/v1/admin/reports/revenue/export
    // Stream CSV langsung untuk data kecil
    public function exportRevenueCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]);

        $filename = 'revenue-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($validated) {
            // Gunakan $validated di sini
            foreach ($this->reportService->streamRevenueCsv($validated) as $chunk) {
                echo $chunk;
                ob_flush();
                flush();
            }
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Cache-Control'       => 'no-cache',
            'X-Accel-Buffering'   => 'no',
        ]);
    }

    // POST /api/v1/admin/reports/export — async export untuk data besar
    public function queueExport(Request $request): JsonResponse
    {
        // Untuk yang ini sudah agak berbeda pendekatannya,
        // tapi sebaiknya tetap ditampung agar aman jika ada perubahan parameter
        $validated = $request->validate([
            'type'      => ['required', 'in:revenue,products,users,orders'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'format'    => ['nullable', 'in:csv,xlsx'],
        ]);

        // Ambil filter selain 'type' dari data yang sudah divalidasi
        $filters = array_diff_key($validated, ['type' => '']);

        $exportId = $this->reportService->queueExport(
            $validated['type'],
            $filters,
            $request->user()->id,
        );

        return $this->success([
            'export_id'   => $exportId,
            'status'      => 'queued',
            'check_url'   => url("/api/v1/admin/reports/export/{$exportId}"),
        ], 'Export sedang diproses. Silakan cek status setelah beberapa saat.');
    }

    // GET /api/v1/admin/reports/export/{exportId}
    public function checkExport(Request $request, string $exportId): JsonResponse
    {
        $url = $this->reportService->getExportDownloadUrl($exportId, $request->user()->id);

        if (! $url) {
            return $this->success(['status' => 'processing']);
        }

        return $this->success([
            'status'       => 'ready',
            'download_url' => $url,
            'expires_at'   => now()->addMinutes(15)->toISOString(),
        ]);
    }
}
