<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Language;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    public function switchLanguage(Request $request, $locale)
    {
        $language = Language::where('code', $locale)->where('is_active', true)->first();
        
        if ($language) {
            Session::put('locale', $locale);
            
            if (auth()->check()) {
                auth()->user()->update(['preferred_locale' => $locale]);
            }
        }
        
        return redirect()->back();
    }
}