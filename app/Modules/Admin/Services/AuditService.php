<?php

namespace App\Modules\Admin\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class AuditService
{
    public function getLogs(array $filters): LengthAwarePaginator
    {
        return Activity::with('causer', 'subject')
            ->when($filters['causer_id'] ?? null,
                fn($q, $v) => $q->where('causer_id', $v))
            ->when($filters['log_name'] ?? null,
                fn($q, $v) => $q->where('log_name', $v))
            ->when($filters['description'] ?? null,
                fn($q, $v) => $q->where('description', 'ilike', "%{$v}%"))
            ->when($filters['date_from'] ?? null,
                fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null,
                fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

    public function logAdminAction(
        string  $action,
        ?object $subject,
        array   $properties = [],
        ?string $causerId    = null
    ): void {
        activity('admin')
            ->when($subject, fn($log) => $log->performedOn($subject))
            ->when($causerId,
                fn($log) => $log->causedBy(\App\Modules\Auth\Models\User::find($causerId)),
            )
            ->withProperties($properties)
            ->log($action);
    }
}
