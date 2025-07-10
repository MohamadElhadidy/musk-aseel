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



        $locale = $request->cookie('locale');

        if ($locale) {
            app()->setLocale($locale);
            //update preferred_locale
            if (auth()->check()) {
                auth()->user()->update(['preferred_locale' => $locale]);
                Cookie::queue(Cookie::forget('locale'));
            }
        }
        // Check if user is authenticated and has a preferred locale
        elseif (auth()->check() && auth()->user()->preferred_locale) {
            app()->setLocale(auth()->user()->preferred_locale);
        }
        // Default to browser preference if available
        else {
            $locale = $request->getPreferredLanguage(['en', 'ar']);
            if ($locale) {
                app()->setLocale($locale);
            }
        }


        // Set currency binding
        $currencyId = $request->cookie('currency');
        app()->singleton('currency', function () use ($currencyId) {
            return $currencyId ? Currency::find($currencyId) : Currency::getDefault();
        });


        return $next($request);
    }
}
