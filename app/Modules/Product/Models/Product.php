<?php

namespace App\Modules\Product\Models;

use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Sluggable\{HasSlug, SlugOptions};

class Product extends Model
{
    use HasFactory, HasUuids, HasSlug, Searchable, SoftDeletes;

    protected $fillable = [
        'category_id', 'sku', 'name', 'slug',
        'description', 'short_description', 'base_price',
        'status', 'is_featured', 'meta', 'attributes', 'primary_image_url',
    ];

    protected function casts(): array
    {
        return [
            'base_price'  => 'decimal:2',
            'rating_avg'  => 'decimal:2',
            'is_featured' => 'boolean',
            'meta'        => 'array',
            'attributes'  => 'array',
            'status'      => ProductStatus::class,
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    // ── Scout / Search ────────────────────────────────────────────────────────

    public function toSearchableArray(): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'short_description' => $this->short_description,
            'category_name'     => $this->category?->name,
            'sku'               => $this->sku,
            'base_price'        => (float) $this->base_price,
            'rating_avg'        => (float) $this->rating_avg,
            'is_featured'       => $this->is_featured,
            'status'            => $this->status->value,
            'total_sold'        => $this->total_sold,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)
            ->where('is_published', true);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getPrimaryImageUrlAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }
        return $this->primaryImage?->url
            ? \Storage::url($this->primaryImage->url)
            : null;
    }

    public function getMinPriceAttribute(): float
    {
        return (float) ($this->activeVariants->min('price') ?? $this->base_price);
    }

    public function getMaxPriceAttribute(): float
    {
        return (float) ($this->activeVariants->max('price') ?? $this->base_price);
    }

    public function getInStockAttribute(): bool
    {
        return $this->activeVariants->sum('stock') > 0;
    }
}
