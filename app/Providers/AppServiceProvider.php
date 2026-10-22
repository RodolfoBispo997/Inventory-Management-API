<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Gate::define('manage-inventory-catalog', fn (User $user): bool => $user->role === User::ROLE_ADMIN);

        RateLimiter::for('auth-login', function (Request $request): array {
            return [
                Limit::perMinute(5)->by(Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip())),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
