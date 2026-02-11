<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.api_per_minute', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('tracking', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.public_per_minute', 100))
                ->by($request->ip());
        });

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.admin_per_minute', 500))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.webhook_per_minute', 1000))
                ->by($request->header('X-Partner-ID') ?: $request->ip());
        });

        RateLimiter::for('batch', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.batch_per_minute', 10))
                ->by($request->header('X-API-Key') ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
