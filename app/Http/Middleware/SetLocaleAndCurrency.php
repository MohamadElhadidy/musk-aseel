<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;

class SetLocaleAndCurrency
{
    public function handle(Request $request, Closure $next)
    {

        // Set currency binding
        $currencyId = $request->cookie('currency');
        app()->singleton('currency', function () use ($currencyId) {
            return $currencyId ? Currency::find($currencyId) : Currency::getDefault();
        });

        $locale = $request->cookie('locale');

        if ($locale) {
            app()->setLocale($locale);
        } else {
            $defaultLanguage = Language::getDefault();
            if ($defaultLanguage) {
                app()->setLocale($defaultLanguage->code);
            }
        }

        return $next($request);
    }
}
