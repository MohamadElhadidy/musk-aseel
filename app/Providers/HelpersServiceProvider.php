<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Currency;
use App\Models\Language;

class HelpersServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register currency helper
        $this->app->singleton('currency', function () {
            return session('currency') ? 
                Currency::find(session('currency')) : 
                Currency::getDefault();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set locale from session
        if (session()->has('locale')) {
            app()->setLocale(session('locale'));
        } else {
            // Set default locale
            $defaultLanguage = Language::getDefault();
            if ($defaultLanguage) {
                app()->setLocale($defaultLanguage->code);
            }
        }
    }
}