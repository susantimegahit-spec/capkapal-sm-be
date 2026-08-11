<?php

namespace App\Modules\Career\Services;

use App\Modules\Career\Enums\ApplicationStatus;
use App\Modules\Career\Models\{Career, CareerApplication};
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class CareerApplicationService
{
    // ── Submit (Public) ───────────────────────────────────────────────────────

    public function submit(Career $career, array $data, UploadedFile $resume): CareerApplication
    {
        // Upload resume ke local public storage
        $resumePath = $resume->store(
            path   : "careers/{$career->id}/resumes",
            options: 'public'
        );

        return $career->applications()->create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'] ?? null,
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_path'  => $resumePath,
            'status'       => ApplicationStatus::Pending,
        ]);
    }

    // ── List Applications (Admin) ─────────────────────────────────────────────

    public function listByCareer(string $careerId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return CareerApplication::with('career')
            ->where('career_id', $careerId)
            ->when($filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null,
                fn($q, $v) => $q->where(fn($s) =>
                    $s->where('name', 'ilike', "%{$v}%")
                      ->orWhere('email', 'ilike', "%{$v}%")
                ))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    // ── Show Application (Admin) ──────────────────────────────────────────────

    public function findById(string $applicationId): CareerApplication
    {
        return CareerApplication::with('career')->findOrFail($applicationId);
    }

    // ── Update Status (Admin) ─────────────────────────────────────────────────

    public function updateStatus(string $applicationId, string $status, ?string $adminNotes = null): CareerApplication
    {
        $application = CareerApplication::findOrFail($applicationId);

        $data = ['status' => $status];
        if ($adminNotes !== null) {
            $data['admin_notes'] = $adminNotes;
        }

        $application->update($data);

        return $application->fresh('career');
    }
}
