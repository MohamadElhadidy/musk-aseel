<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TranslationService;
use App\Models\Language;
use Illuminate\Support\Facades\View;

class LocalizationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService();
        });
    }

    public function boot()
    {
        // Share languages with all views
        View::composer('*', function ($view) {
            $view->with('availableLanguages', Language::active()->get());
            $view->with('currentLanguage', Language::where('code', app()->getLocale())->first());
        });
    }
}