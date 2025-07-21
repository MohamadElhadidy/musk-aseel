<?php 

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    protected $currency;
    protected $currencies;
    
    public function __construct()
    {
        $this->loadCurrencies();
        $this->setCurrentCurrency();
    }
    
    protected function loadCurrencies()
    {
        $this->currencies = Cache::remember('active_currencies', 3600, function () {
            return Currency::where('is_active', true)->get()->keyBy('code');
        });
    }
    
    protected function setCurrentCurrency()
    {
        $currencyCode = Session::get('currency');
        
        if ($currencyCode && isset($this->currencies[$currencyCode])) {
            $this->currency = $this->currencies[$currencyCode];
        } else {
            $this->currency = $this->currencies->where('is_default', true)->first() 
                            ?? $this->currencies->first();
        }
    }
    
    public function convert($amount, $from = null, $to = null)
    {
        $from = $from ?? $this->getDefaultCurrency()->code;
        $to = $to ?? $this->currency->code;
        
        if ($from === $to) {
            return $amount;
        }
        
        $fromCurrency = $this->currencies[$from] ?? null;
        $toCurrency = $this->currencies[$to] ?? null;
        
        if (!$fromCurrency || !$toCurrency) {
            return $amount;
        }
        
        // Convert to default currency first, then to target currency
        $defaultCurrency = $this->getDefaultCurrency();
        
        if ($from === $defaultCurrency->code) {
            $amountInDefault = $amount;
        } else {
            $amountInDefault = $amount / $fromCurrency->exchange_rate;
        }
        
        if ($to === $defaultCurrency->code) {
            return $amountInDefault;
        } else {
            return $amountInDefault * $toCurrency->exchange_rate;
        }
    }
    
    public function format($amount, $currencyCode = null)
    {
        $currency = $currencyCode 
            ? ($this->currencies[$currencyCode] ?? $this->currency)
            : $this->currency;
            
        $converted = $this->convert($amount, $this->getDefaultCurrency()->code, $currency->code);
        
        return $currency->symbol . ' ' . number_format($converted, 2);
    }
    
    public function getCurrent()
    {
        return $this->currency;
    }
    
    public function getDefaultCurrency()
    {
        return $this->currencies->where('is_default', true)->first();
    }
    
    public function getAllActive()
    {
        return $this->currencies;
    }
    
    public function clearCache()
    {
        Cache::forget('active_currencies');
        $this->loadCurrencies();
    }
}