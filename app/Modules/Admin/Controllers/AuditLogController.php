<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Admin\Services\AuditService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class AuditLogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly AuditService $auditService) {}

    // GET /api/v1/admin/audit-logs
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'causer_id'  => ['nullable', 'uuid'],
            'log_name'   => ['nullable', 'string'],
            'description'=> ['nullable', 'string', 'max:100'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
            'per_page'   => ['nullable', 'integer', 'max:100'],
        ]);

        $logs = $this->auditService->getLogs($request->validated());

        return $this->success($logs);
    }
}
