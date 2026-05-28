<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Observers\ProductObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{DB, Log, RateLimiter, Response};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return env('FRONTEND_URL', 'http://localhost:3000') . "/auth/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = env('FRONTEND_URL', 'http://localhost:3000') . "/auth/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Permintaan Reset Password')
                ->greeting('Halo ' . $notifiable->name . '!')
                ->line('Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda.')
                ->action('Reset Password', $url)
                ->line('Link reset password ini akan kedaluwarsa dalam 60 menit.')
                ->line('Jika Anda tidak merasa meminta reset password, Anda dapat mengabaikan email ini dan akun Anda akan tetap aman.')
                ->salutation("Terima kasih,\nTim " . config('app.name', 'Garam Cap Kapal'));
        });

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
        if (config('app.debug')) {
            DB::whenQueryingForLongerThan(500, function ($connection) {
                Log::warning('Slow query detected', [
                    'sql' => $connection->lastQueryLog(),
                ]);
            });
        }
    }
}
