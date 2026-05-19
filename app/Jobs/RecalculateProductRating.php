<?php

namespace App\Jobs;

use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class RecalculateProductRating implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $productId) {
        $this->onQueue('default');
    }

    public function handle(ProductRepositoryInterface $repository): void
    {
        $repository->recalculateRating($this->productId);
    }
}
