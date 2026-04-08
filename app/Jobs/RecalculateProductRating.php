<?php
// app/Jobs/RecalculateProductRating.php
namespace App\Jobs;

use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class RecalculateProductRating implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'default';

    public function __construct(private readonly string $productId) {}

    public function handle(ProductRepositoryInterface $repository): void
    {
        $repository->recalculateRating($this->productId);
    }
}
