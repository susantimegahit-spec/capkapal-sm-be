<?php

namespace App\Modules\Product\Services;

use App\Modules\Product\Events\{ProductCreated, ProductDeleted, ProductUpdated};
use App\Modules\Product\Models\{Product, ProductImage, ProductVariant};
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{Cache, DB, Storage};
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    // ── List & Find ───────────────────────────────────────────────────────────

    public function listPublic(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = 'products:list:' . md5(serialize($filters) . $perPage);

        return Cache::tags(['products'])->remember($cacheKey, now()->addMinutes(15), fn() =>
        $this->productRepository->listPublic($filters, $perPage)
        );
    }

    public function listAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        // Admin list tidak di-cache karena butuh data real-time
        return $this->productRepository->listAdmin($filters, $perPage);
    }

    public function findBySlug(string $slug): Product
    {
        $product = Cache::tags(['products'])->remember(
            "products:detail:{$slug}",
            now()->addHour(),
            fn() => $this->productRepository->findBySlug($slug)
        );

        if (! $product) {
            abort(404, 'Produk tidak ditemukan.');
        }

        return $product;
    }

    public function findById(string $id): Product
    {
        $product = $this->productRepository->findOrFail($id);

        return $product;
    }

    public function getFeatured(int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::tags(['products'])->remember(
            "products:featured:{$limit}",
            now()->addMinutes(15),
            fn() => $this->productRepository->getFeatured($limit)
        );
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data, array $variants = [], array $images = []): Product
    {
        return DB::transaction(function () use ($data, $variants, $images) {
            $product = $this->productRepository->create($data);

            // Buat variants
            foreach ($variants as $variantData) {
                $product->variants()->create($variantData);
            }

            // Upload & simpan gambar
            foreach ($images as $index => $imageData) {
                $this->attachImage($product, $imageData['file'], $index === 0);
            }

            event(new ProductCreated($product));
            $this->flushCache();

            return $product->load(['category', 'activeVariants', 'images']);
        });
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(string $id, array $data): Product
    {
        $product = DB::transaction(function () use ($id, $data) {
            $product = $this->productRepository->update($id, $data);
            event(new ProductUpdated($product));
            return $product;
        });

        $this->flushCache();
        return $product;
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    public function delete(string $id): void
    {
        $product = $this->productRepository->findOrFail($id);
        $product->delete();

        event(new ProductDeleted($product));
        $this->flushCache();
    }

    // ── Status ────────────────────────────────────────────────────────────────
    public function updateStatus(string $id, string $status): Product
    {
        $product = DB::transaction(function () use ($id, $status) {
            $product = $this->productRepository->update($id, [
                'status' => $status
            ]);

            event(new ProductUpdated($product));

            return $product;
        });

        $this->flushCache();

        return $product->load(['category', 'activeVariants', 'images']);
    }

    // ── Variants ──────────────────────────────────────────────────────────────

    public function upsertVariant(string $productId, array $data): ProductVariant
    {
        $product = $this->productRepository->findOrFail($productId);

        // Verifikasi kepemilikan variant: pastikan variant.id milik produk ini.
        // Mencegah IDOR di mana editor bisa mengirim ID variant dari produk lain.
        $variant = isset($data['id'])
            ? ProductVariant::where('product_id', $product->id)->findOrFail($data['id'])
            : new ProductVariant(['product_id' => $product->id]);

        $variant->fill($data)->save();
        $this->flushCache();

        return $variant;
    }

    public function deleteVariant(string $variantId): void
    {
        $variant = ProductVariant::findOrFail($variantId);

        // Jangan hapus jika ada order aktif
        $hasActiveOrders = \DB::table('order_items')
            ->where('product_variant_id', $variantId)
            ->exists();

        if ($hasActiveOrders) {
            // Nonaktifkan saja
            $variant->update(['is_active' => false]);
        } else {
            $variant->delete();
        }

        $this->flushCache();
    }

    public function updateStock(string $variantId, int $qty, string $operation = 'set'): ProductVariant
    {
        return DB::transaction(function () use ($variantId, $qty, $operation) {
            // lockForUpdate mencegah race condition saat dua admin update stok bersamaan
            $variant = ProductVariant::lockForUpdate()->findOrFail($variantId);

            $newStock = match ($operation) {
                'set'       => $qty,
                'increment' => $variant->stock + $qty,
                'decrement' => max(0, $variant->stock - $qty),
                default     => throw new \InvalidArgumentException("Invalid stock operation: {$operation}"),
            };

            $variant->update(['stock' => $newStock]);
            $this->flushCache();

            return $variant->fresh();
        });
    }

    // ── Images ────────────────────────────────────────────────────────────────

    public function attachImage(
        Product $product,
        UploadedFile $file,
        bool $isPrimary = false
    ): ProductImage {
        // Upload ke S3
        $path = $file->store(
            path   : "products/{$product->id}",
            options: 's3'
        );

        $image = $product->images()->create([
            'url'        => $path,
            'alt'        => $product->name,
            'sort_order' => $product->images()->count(),
            'is_primary' => $isPrimary,
        ]);

        $this->flushCache();
        return $image;
    }

    public function deleteImage(string $imageId): void
    {
        $image = ProductImage::findOrFail($imageId);

        // Hapus dari S3
        Storage::disk('s3')->delete($image->attributes['url']);
        $image->delete();

        // Jika gambar yang dihapus adalah primary, jadikan yang pertama sebagai primary
        $product = $image->product;
        if ($product->images()->count() > 0 && ! $product->images()->where('is_primary', true)->exists()) {
            $product->images()->oldest()->first()?->update(['is_primary' => true]);
        }

        $this->flushCache();
    }

    public function reorderImages(string $productId, array $orderedIds): void
    {
        foreach ($orderedIds as $sortOrder => $imageId) {
            ProductImage::where('product_id', $productId)
                ->where('id', $imageId)
                ->update(['sort_order' => $sortOrder]);
        }
        $this->flushCache();
    }

    // ── Reviews ───────────────────────────────────────────────────────────────

    public function submitReview(string $productId, string $userId, array $data): \App\Modules\Product\Models\ProductReview
    {
        // Cek apakah user sudah pernah review produk ini
        $exists = \App\Modules\Product\Models\ProductReview::where('product_id', $productId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'product_id' => ['Anda sudah memberikan ulasan untuk produk ini.'],
            ]);
        }

        // Verifikasi pembelian jika order_item_id diberikan:
        // Pastikan order_item milik user ini, terkait produk ini, dan ordernya sudah selesai.
        $isVerifiedPurchase = false;

        if (! empty($data['order_item_id'])) {
            $isVerifiedPurchase = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
                ->where('oi.id', $data['order_item_id'])
                ->where('o.user_id', $userId)
                ->where('pv.product_id', $productId)
                ->whereIn('o.status', ['delivered', 'completed', 'confirmed'])
                ->exists();

            if (! $isVerifiedPurchase) {
                throw ValidationException::withMessages([
                    'order_item_id' => ['Item pesanan tidak valid atau pesanan belum selesai.'],
                ]);
            }
        }

        $review = \App\Modules\Product\Models\ProductReview::create([
            ...$data,
            'product_id'           => $productId,
            'user_id'              => $userId,
            'is_verified_purchase' => $isVerifiedPurchase,
        ]);

        // Hitung ulang rating di background
        \App\Jobs\RecalculateProductRating::dispatch($productId)->onQueue('default');

        return $review->load('user');
    }

    // ── Cache ─────────────────────────────────────────────────────────────────

    public function flushCache(): void
    {
        Cache::tags(['products'])->flush();
    }
}
