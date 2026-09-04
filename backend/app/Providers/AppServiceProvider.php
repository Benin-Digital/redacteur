<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
    public function boot()
    {
        Schema::defaultStringLength(191); // Pour compatibilité MySQL

        // Configuration Sanctum
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Rate limiting pour les connexions
        RateLimiter::for('login', function (Request $request) {
            $key = $request->input('email') . '|' . $request->ip();
            return Limit::perMinute(5)->by($key);
        });

        // Rate limiting global API (optionnel)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
