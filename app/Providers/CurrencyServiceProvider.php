<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CurrencyService;
use App\Models\Currency;
use Illuminate\Support\Facades\View;

class CurrencyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CurrencyService::class, function ($app) {
            return new CurrencyService();
        });
    }

    public function boot()
    {
        // Share currencies with all views
        View::composer('*', function ($view) {
            $view->with('availableCurrencies', Currency::active()->get());
            $view->with('currentCurrency', app(CurrencyService::class)->getCurrent());
        });
    }
}