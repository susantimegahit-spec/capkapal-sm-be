<?php
// app/Console/Commands/ReindexProducts.php

namespace App\Console\Commands;

use App\Modules\Product\Services\ProductSearchService;
use Illuminate\Console\Command;

class ReindexProducts extends Command
{
    protected $signature   = 'products:reindex';
    protected $description = 'Re-index semua produk aktif ke Meilisearch';

    public function handle(ProductSearchService $searchService): void
    {
        $this->info('Memulai re-index produk...');
        $searchService->reindexAll();
        $this->info('Re-index selesai.');
    }
}
