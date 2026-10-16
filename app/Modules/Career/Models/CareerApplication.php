<?php

namespace App\Modules\Career\Models;

use App\Modules\Career\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Auth\Models\User;

class CareerApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'career_id', 'user_id', 'name', 'email', 'phone',
        'resume_path', 'cover_letter',
        'status', 'admin_notes',
        'nik', 'gender', 'education_level', 'major', 'institution',
        'expected_salary', 'province', 'city',
        'transcript_path', 'certificate_path'
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', ApplicationStatus::Pending);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getResumeUrlAttribute(): ?string
    {
        return $this->resume_path
            ? asset('storage/' . $this->resume_path)
            : null;
    }

    public function getTranscriptUrlAttribute(): ?string
    {
        return $this->transcript_path
            ? asset('storage/' . $this->transcript_path)
            : null;
    }

    public function getCertificateUrlAttribute(): ?string
    {
        return $this->certificate_path
            ? asset('storage/' . $this->certificate_path)
            : null;
    }
}
