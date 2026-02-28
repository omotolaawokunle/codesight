<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // General API: 100 requests/minute per IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // Login: 5 attempts per 15 minutes per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(15, 5)->by($request->ip());
        });

        // Repository creation: 10 per hour per authenticated user (fallback to IP)
        RateLimiter::for('repository-create', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?? $request->ip());
        });

        // Chat queries: 30 per minute per authenticated user (fallback to IP)
        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?? $request->ip());
        });
    }
}

