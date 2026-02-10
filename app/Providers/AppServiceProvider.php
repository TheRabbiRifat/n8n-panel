<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\SystemStatusService;
use Laravel\Sanctum\Sanctum;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

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
    public function boot(SystemStatusService $systemStatusService): void
    {
        Paginator::useBootstrapFive();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Implicitly grant "Super Admin" role all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        // Share system info with the main layout
        View::composer('layouts.app', function ($view) use ($systemStatusService) {
            // Only fetch if authenticated to avoid overhead on login page if it used app layout (it doesn't, but good practice)
            // But app.blade.php checks @auth.

            // Cache system stats for 60 seconds to avoid blocking shell commands on every request
            $stats = Cache::remember('system_stats', 60, function () use ($systemStatusService) {
                return $systemStatusService->getSystemStats();
            });
            $view->with('serverInfo', $stats);
        });
    }
}
