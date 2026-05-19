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
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
try {
    $user = User::first() ?? User::forceCreate([
        'id' => Str::uuid(),
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $product = Product::forceCreate([
        'id' => Str::uuid(),
        'category_id' => Str::uuid(),
        'sku' => 'SKU-BUG-1',
        'name' => 'Bug Product',
        'slug' => 'bug-product',
        'base_price' => 100,
        'total_sold' => 0,
    ]);

    $variant = ProductVariant::forceCreate([
        'id' => Str::uuid(),
        'product_id' => $product->id,
        'sku' => 'SKU-BUG-VAR-1',
        'name' => 'Bug Var',
        'price' => 100,
        'stock' => 10,
    ]);

    $order = Order::forceCreate([
        'id' => Str::uuid(),
        'user_id' => $user->id,
        'order_number' => 'ORD-BUG-1',
        'status' => OrderStatus::Pending,
        'subtotal' => 100,
        'total' => 100,
    ]);

    OrderItem::forceCreate([
        'id' => Str::uuid(),
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Bug Product',
        'variant_name' => 'Bug Var',
        'sku' => 'SKU-BUG-VAR-1',
        'quantity' => 5,
        'unit_price' => 100,
        'subtotal' => 500,
    ]);

    echo "Before transition:\n";
    echo "Order Status: " . $order->status->value . "\n";
    echo "Product Total Sold: " . Product::find($product->id)->total_sold . "\n";

    $order->transitionTo(OrderStatus::Confirmed);

    echo "After transition:\n";
    echo "Order Status: " . $order->status->value . "\n";
    echo "Product Total Sold: " . Product::find($product->id)->total_sold . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
DB::rollBack();
