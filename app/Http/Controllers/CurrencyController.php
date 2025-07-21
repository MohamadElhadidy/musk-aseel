<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;
use Illuminate\Support\Facades\Session;

class CurrencyController extends Controller
{
    public function switchCurrency(Request $request, $code)
    {
        $currency = Currency::where('code', $code)->where('is_active', true)->first();
        
        if ($currency) {
            Session::put('currency', $code);
            Session::put('currency_id', $currency->id);
        }
        
        return redirect()->back();
    }
}