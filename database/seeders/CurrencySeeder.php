<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1.0000,
            'is_active' => true,
            'is_default' => true,
        ]);

        Currency::create([
            'code' => 'EGP',
            'name' => 'Egyptian Pound',
            'symbol' => 'ج.م',
            'exchange_rate' => 30.9000,
            'is_active' => true,
            'is_default' => false,
        ]);

        Currency::create([
            'code' => 'SAR',
            'name' => 'Saudi Riyal',
            'symbol' => 'ر.س',
            'exchange_rate' => 3.7500,
            'is_active' => true,
            'is_default' => false,
        ]);
    }
}
