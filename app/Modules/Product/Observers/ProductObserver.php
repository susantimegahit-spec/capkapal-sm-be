<?php
// app/Modules/Product/Observers/ProductObserver.php
namespace App\Modules\Product\Observers;

use App\Modules\Product\Models\Product;

class ProductObserver
{
    // Sync ke Meilisearch setiap kali produk disimpan
    public function saved(Product $product): void
    {
        if ($product->shouldBeSearchable()) {
            $product->searchable();
        } else {
            $product->unsearchable();
        }
    }

    public function deleted(Product $product): void
    {
        $product->unsearchable();
    }
}
