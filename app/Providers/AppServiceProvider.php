<?php

namespace App\Providers;

use App\Contracts\FaceAntiSpoofingInterface;
use App\Contracts\FaceRecognitionEngineInterface;
use App\Services\FaceRecognitionService;
use App\Services\LivenessDetectionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FaceRecognitionEngineInterface::class, FaceRecognitionService::class);
        $this->app->singleton(FaceAntiSpoofingInterface::class, LivenessDetectionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Rate limiter for the public attendance kiosk endpoints (face verify & record).
        // Limits to 30 requests per minute per IP to mitigate brute-force / replay abuse.
        RateLimiter::for('attendance-kiosk', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
