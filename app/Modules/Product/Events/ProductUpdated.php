<?php

namespace App\Modules\Product\Events;

use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUpdated
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly Product $product) {}
}
