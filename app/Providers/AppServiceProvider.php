<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        /* Polymorphic Relation Morph Map */
        Relation::morphMap([
            'user'       => \App\Models\User::class,
            'bank'       => \App\Models\Bank::class,
            'customer'   => \App\Models\Customer::class,
            'sale'       => \App\Models\Sale::class,
            'cheque'     => \App\Models\Cheque::class,
            'payment'    => \App\Models\Payment::class,
            'expense'    => \App\Models\Expense::class,
        ]);

        /* Rate Limiters */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
