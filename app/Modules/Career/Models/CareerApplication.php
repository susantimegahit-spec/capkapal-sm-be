<?php

namespace App\Modules\Career\Models;

use App\Modules\Career\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'career_id', 'name', 'email', 'phone',
        'resume_path', 'cover_letter',
        'status', 'admin_notes',
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
}
