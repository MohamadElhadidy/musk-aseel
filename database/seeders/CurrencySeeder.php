<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate' => 1.0000,
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'code' => 'EGP',
                'name' => 'Egyptian Pound',
                'symbol' => 'ج.م',
                'exchange_rate' => 30.9000,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'SAR',
                'name' => 'Saudi Riyal',
                'symbol' => 'ر.س',
                'exchange_rate' => 3.7500,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'AED',
                'name' => 'UAE Dirham',
                'symbol' => 'د.إ',
                'exchange_rate' => 3.6730,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'exchange_rate' => 0.9200,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'GBP',
                'name' => 'British Pound',
                'symbol' => '£',
                'exchange_rate' => 0.7900,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'KWD',
                'name' => 'Kuwaiti Dinar',
                'symbol' => 'د.ك',
                'exchange_rate' => 0.3080,
                'is_active' => false,
                'is_default' => false,
            ],
            [
                'code' => 'QAR',
                'name' => 'Qatari Riyal',
                'symbol' => 'ر.ق',
                'exchange_rate' => 3.6400,
                'is_active' => false,
                'is_default' => false,
            ],
        ];

        foreach ($currencies as $currencyData) {
            Currency::create($currencyData);
        }
    }
}