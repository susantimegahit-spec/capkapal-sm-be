<?php

namespace App\Core\Providers;

use Illuminate\Support\ServiceProvider;

// Auth
use App\Modules\Auth\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Repositories\Eloquent\UserRepository;

// Product
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use App\Modules\Product\Repositories\Eloquent\ProductRepository;
//
//// Order
use App\Modules\Order\Repositories\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Repositories\Eloquent\OrderRepository;

use App\Modules\Payment\Gateway\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Gateway\MidtransGateway;

// Career
use App\Modules\Career\Repositories\Contracts\CareerRepositoryInterface;
use App\Modules\Career\Repositories\Eloquent\CareerRepository;

class RepositoryServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
    $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
    $this->app->bind(PaymentGatewayInterface::class, MidtransGateway::class);
    $this->app->bind(CareerRepositoryInterface::class, CareerRepository::class);
  }
}
