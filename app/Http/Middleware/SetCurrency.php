<?php 

namespace App\Http\Middleware;

use Closure;
use App\Models\Currency;
use Illuminate\Support\Facades\Session;

class SetCurrency
{
    public function handle($request, Closure $next)
    {
        // Priority: URL parameter > Session > User preference > Default
        $currencyCode = null;
        
        // 1. Check URL parameter
        if ($request->has('currency')) {
            $currencyCode = $request->get('currency');
        }
        
        // 2. Check session
        elseif (Session::has('currency')) {
            $currencyCode = Session::get('currency');
        }
        
        // 3. Check user preference (if implemented)
        elseif (auth()->check() && method_exists(auth()->user(), 'preferred_currency')) {
            $currencyCode = auth()->user()->preferred_currency;
        }
        
        // Get currency object
        $currency = null;
        if ($currencyCode) {
            $currency = Currency::where('code', $currencyCode)->where('is_active', true)->first();
        }
        
        // Fallback to default currency
        if (!$currency) {
            $currency = Currency::where('is_default', true)->where('is_active', true)->first();
            if (!$currency) {
                $currency = Currency::first();
            }
        }
        
        // Set currency in session and view
        if ($currency) {
            Session::put('currency', $currency->code);
            Session::put('currency_id', $currency->id);
            view()->share('currentCurrency', $currency);
        }
        
        return $next($request);
    }
}