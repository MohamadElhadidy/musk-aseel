<?php

use App\Services\TranslationService;
use App\Services\CurrencyService;

if (!function_exists('__')) {
    /**
     * Translate the given message.
     */
    function __($key, $parameters = [], $locale = null)
    {
        return app(TranslationService::class)->get($key, $parameters, $locale);
    }
}

if (!function_exists('currency')) {
    /**
     * Get currency service or format amount.
     */
    function currency($amount = null, $from = null, $to = null)
    {
        $service = app(CurrencyService::class);
        
        if ($amount === null) {
            return $service;
        }
        
        return $service->format($service->convert($amount, $from, $to));
    }
}

if (!function_exists('convert_currency')) {
    /**
     * Convert amount between currencies.
     */
    function convert_currency($amount, $from = null, $to = null)
    {
        return app(CurrencyService::class)->convert($amount, $from, $to);
    }
}

if (!function_exists('format_price')) {
    /**
     * Format price in current currency.
     */
    function format_price($amount)
    {
        return app(CurrencyService::class)->format($amount);
    }
}

if (!function_exists('current_currency')) {
    /**
     * Get current currency.
     */
    function current_currency()
    {
        return app(CurrencyService::class)->getCurrent();
    }
}

if (!function_exists('current_language')) {
    /**
     * Get current language.
     */
    function current_language()
    {
        return \App\Models\Language::where('code', app()->getLocale())->first();
    }
}

