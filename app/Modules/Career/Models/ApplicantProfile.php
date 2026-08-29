<?php

namespace App\Modules\Career\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'linkedin_url',
        'portfolio_url',
        'cv_path',
        'education_level',
        'institution',
        'major',
        'nik',
        'province',
        'city',
        'gender',
        'expected_salary',
        'transcript_path',
        'certificate_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($profile) {
            if ($profile->cv_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->cv_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->cv_path);
            }
            if ($profile->transcript_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->transcript_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->transcript_path);
            }
            if ($profile->certificate_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->certificate_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->certificate_path);
            }
        });
    }
}
