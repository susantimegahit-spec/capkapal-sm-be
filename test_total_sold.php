<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Order\Models\Order;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

$product = Product::forceCreate([
    'id' => Str::uuid(),
    'category_id' => Str::uuid(),
    'sku' => 'SKU-TEST-2',
    'name' => 'Test Product',
    'slug' => 'test-product-2',
    'base_price' => 10000,
    'total_sold' => 0,
]);

$variant = ProductVariant::forceCreate([
    'id' => Str::uuid(),
    'product_id' => $product->id,
    'sku' => 'SKU-TEST-2-VAR',
    'name' => 'Test Var',
    'price' => 10000,
    'stock' => 10,
]);

$order = Order::forceCreate([
    'id' => Str::uuid(),
    'user_id' => Str::uuid(),
    'order_number' => 'ORD-TEST-2',
    'status' => OrderStatus::Pending,
    'subtotal' => 10000,
    'total' => 10000,
]);

OrderItem::forceCreate([
    'id' => Str::uuid(),
    'order_id' => $order->id,
    'product_variant_id' => $variant->id,
    'quantity' => 2,
    'unit_price' => 10000,
    'subtotal' => 20000,
]);

$order->transitionTo(OrderStatus::Confirmed);

$product->refresh();
echo "Total Sold after confirmed: " . $product->total_sold . "\n";

DB::rollBack();
