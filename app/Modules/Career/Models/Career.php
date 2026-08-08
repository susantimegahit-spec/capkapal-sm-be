<?php

namespace App\Modules\Career\Models;

use App\Modules\Career\Enums\{CareerStatus, EmploymentType};
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\{HasSlug, SlugOptions};

class Career extends Model
{
    use HasUuids, HasSlug, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description', 'requirements',
        'location', 'employment_type', 'status',
        'published_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status'          => CareerStatus::class,
            'published_at'    => 'datetime',
            'closed_at'       => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function applications(): HasMany
    {
        return $this->hasMany(CareerApplication::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', CareerStatus::Published);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', CareerStatus::Published)
            ->where(function ($q) {
                $q->whereNull('closed_at')
                  ->orWhere('closed_at', '>', now());
            });
    }
}
