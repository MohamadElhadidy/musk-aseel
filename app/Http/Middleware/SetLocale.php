<?php
// app/Http/Middleware/SetLocale.php
namespace App\Http\Middleware;

use Closure;
use App\Models\Language;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        // Priority: URL parameter > User preference > Session > Browser > Default
        $locale = null;
        
        // 1. Check URL parameter
        if ($request->has('lang')) {
            $locale = $request->get('lang');
        }
        
        // 2. Check user preference
        elseif (auth()->check() && auth()->user()->preferred_locale) {
            $locale = auth()->user()->preferred_locale;
        }
        
        // 3. Check session
        elseif (Session::has('locale')) {
            $locale = Session::get('locale');
        }
        
        // 4. Check browser preference
        elseif ($request->hasHeader('Accept-Language')) {
            $browserLang = substr($request->header('Accept-Language'), 0, 2);
            if (Language::where('code', $browserLang)->where('is_active', true)->exists()) {
                $locale = $browserLang;
            }
        }
        
        // 5. Get default
        if (!$locale) {
            $defaultLang = Language::where('is_default', true)->where('is_active', true)->first();
            $locale = $defaultLang ? $defaultLang->code : config('app.locale');
        }
        
        // Validate locale exists and is active
        $language = Language::where('code', $locale)->where('is_active', true)->first();
        if (!$language) {
            $language = Language::where('is_default', true)->where('is_active', true)->first();
            $locale = $language ? $language->code : config('app.locale');
        }
        
        // Set locale
        App::setLocale($locale);
        Session::put('locale', $locale);
        
        // Set text direction in view
        view()->share('textDirection', $language->direction ?? 'ltr');
        view()->share('currentLanguage', $language);
        
        return $next($request);
    }
}