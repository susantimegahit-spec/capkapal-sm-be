<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Admin\Services\DashboardService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly DashboardService $dashboardService) {}

    // GET /api/v1/admin/dashboard
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:today,week,month,year'],
        ]);

        $kpis = $this->dashboardService->getKpis($request->period ?? 'today');

        return $this->success($kpis);
    }

    // GET /api/v1/admin/dashboard/revenue-chart
    public function revenueChart(Request $request): JsonResponse
    {
        $request->validate([
            'group_by' => ['nullable', 'in:day,week,month'],
            'limit'    => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        $chart = $this->dashboardService->getRevenueChart(
            $request->group_by ?? 'day',
            $request->limit    ?? 30,
        );

        return $this->success($chart);
    }

    // GET /api/v1/admin/dashboard/top-products
    public function topProducts(Request $request): JsonResponse
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:20']]);

        return $this->success(
            $this->dashboardService->getTopProducts($request->limit ?? 10)
        );
    }

    // GET /api/v1/admin/dashboard/user-distribution
    public function userDistribution(): JsonResponse
    {
        return $this->success($this->dashboardService->getUserDistribution());
    }
}
