<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Request;

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
        // Fix for Ngrok / Tunneling:
        // If the request comes from an ngrok URL or has the HTTPS header,
        // force Laravel to generate HTTPS links for all assets (CSS, JS, Images).
        if (
            $this->app->environment('local') &&
            (
                Request::header('x-forwarded-proto') === 'https' ||
                str_contains(Request::getHost(), 'ngrok-free.dev')
            )
        ) {
            URL::forceScheme('https');
        }
    }
}