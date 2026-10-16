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

    public function submit(
        Career $career, 
        array $data, 
        ?UploadedFile $resume = null,
        ?UploadedFile $transcript = null,
        ?UploadedFile $certificate = null,
        ?\App\Modules\Auth\Models\User $user = null
    ): CareerApplication {
        $profile = $user ? $user->applicantProfile : null;
        
        $resumePath = null;
        $transcriptPath = null;
        $certificatePath = null;

        if ($user && $career->applications()->where('user_id', $user->id)->exists()) {
            throw new \Exception('Anda sudah melamar posisi ini.');
        }

        if ($resume) {
            $resumePath = $resume->store(path: "careers/{$career->id}/resumes", options: 'public');
        } elseif ($profile && $profile->cv_path && Storage::disk('public')->exists($profile->cv_path)) {
            $resumePath = "careers/{$career->id}/resumes/" . uniqid() . "_" . basename($profile->cv_path);
            Storage::disk('public')->copy($profile->cv_path, $resumePath);
        }

        if ($transcript) {
            $transcriptPath = $transcript->store(path: "careers/{$career->id}/transcripts", options: 'public');
        } elseif ($profile && $profile->transcript_path && Storage::disk('public')->exists($profile->transcript_path)) {
            $transcriptPath = "careers/{$career->id}/transcripts/" . uniqid() . "_" . basename($profile->transcript_path);
            Storage::disk('public')->copy($profile->transcript_path, $transcriptPath);
        }

        if ($certificate) {
            $certificatePath = $certificate->store(path: "careers/{$career->id}/certificates", options: 'public');
        } elseif ($profile && $profile->certificate_path && Storage::disk('public')->exists($profile->certificate_path)) {
            $certificatePath = "careers/{$career->id}/certificates/" . uniqid() . "_" . basename($profile->certificate_path);
            Storage::disk('public')->copy($profile->certificate_path, $certificatePath);
        }

        return $career->applications()->create([
            'user_id'         => $user?->id,
            'name'            => $data['name'],
            'email'           => $data['email'],
            'phone'           => $data['phone'] ?? null,
            'nik'             => $data['nik'] ?? null,
            'gender'          => $data['gender'] ?? null,
            'education_level' => $data['education_level'] ?? null,
            'major'           => $data['major'] ?? null,
            'institution'     => $data['institution'] ?? null,
            'expected_salary' => $data['expected_salary'] ?? null,
            'province'        => $data['province'] ?? null,
            'city'            => $data['city'] ?? null,
            'cover_letter'    => $data['cover_letter'] ?? null,
            'resume_path'     => $resumePath,
            'transcript_path' => $transcriptPath,
            'certificate_path'=> $certificatePath,
            'status'          => ApplicationStatus::Pending,
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
