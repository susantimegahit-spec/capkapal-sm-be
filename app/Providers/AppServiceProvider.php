<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Observers\ProductObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{DB, Log, RateLimiter, Response};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        // ── Rate Limiters ─────────────────────────────────────────────
        RateLimiter::for(

            'api-public',
            fn(Request $r) =>
            Limit::perMinute(config('app.rate_limit_public', 60))->by($r->ip())
        );

        RateLimiter::for(
            'api-auth',
            fn(Request $r) =>
            Limit::perMinute(config('app.rate_limit_auth', 120))->by($r->user()?->id ?: $r->ip())
        );

        RateLimiter::for(
            'api-admin',
            fn(Request $r) =>
            Limit::perMinute(config('app.rate_limit_admin', 300))->by($r->user()?->id ?: $r->ip())
        );

        // ── Slow Query Logger ─────────────────────────────────────────
        if (config('app.debug') && !app()->runningInConsole()) {
            DB::whenQueryingForLongerThan(500, function ($connection) {
                Log::warning('Slow query detected', [
                    'connection' => $connection->getName(),
                    'total_duration_ms' => $connection->totalQueryDuration(),
                ]);
            });
        }
    }
}
