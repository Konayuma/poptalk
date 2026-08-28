<?php

namespace App\Providers;

use App\Models\Frequency;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('frequency', function (string $value): Frequency {
            if (! preg_match('/^\d{1,2}$/', $value)) {
                abort(404);
            }

            $number = (int) $value;
            $min = (int) config('poptalk.min_frequency');
            $max = (int) config('poptalk.max_frequency');

            if ($number < $min || $number > $max) {
                abort(404);
            }

            return Frequency::query()->where('number', $number)->firstOrFail();
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('operators', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('signals', function (Request $request) {
            return Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
