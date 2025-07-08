<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            CurrencySeeder::class,
            CountryCitySeeder::class,
            SettingsSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            TagSeeder::class,
            ProductSeeder::class,
            UserSeeder::class,
            ShippingSeeder::class,
            CouponSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,
            SliderBannerSeeder::class,
        ]);
    }
}