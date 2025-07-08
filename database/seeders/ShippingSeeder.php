<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        // Create shipping zones
        $zone1 = ShippingZone::create([
            'name' => ['en' => 'Zone 1 - Major Cities', 'ar' => 'المنطقة 1 - المدن الرئيسية'],
            'is_active' => true,
        ]);

        $zone2 = ShippingZone::create([
            'name' => ['en' => 'Zone 2 - Other Cities', 'ar' => 'المنطقة 2 - المدن الأخرى'],
            'is_active' => true,
        ]);

        // Assign cities to zones
        $majorCities = City::whereHas('country', function ($q) {
            $q->where('code', 'EG');
        })->whereIn('name->en', ['Cairo', 'Alexandria', 'Giza'])->pluck('id');
        
        $zone1->cities()->attach($majorCities);
        
        $otherCities = City::whereNotIn('id', $majorCities)->pluck('id');
        $zone2->cities()->attach($otherCities);

        // Create shipping methods
        $standard = ShippingMethod::create([
            'name' => ['en' => 'Standard Shipping', 'ar' => 'الشحن العادي'],
            'description' => ['en' => 'Delivery in 5-7 business days', 'ar' => 'التوصيل خلال 5-7 أيام عمل'],
            'base_cost' => 50,
            'calculation_type' => 'flat',
            'min_days' => 5,
            'max_days' => 7,
            'is_active' => true,
        ]);

        $express = ShippingMethod::create([
            'name' => ['en' => 'Express Shipping', 'ar' => 'الشحن السريع'],
            'description' => ['en' => 'Delivery in 1-2 business days', 'ar' => 'التوصيل خلال 1-2 أيام عمل'],
            'base_cost' => 100,
            'calculation_type' => 'flat',
            'min_days' => 1,
            'max_days' => 2,
            'is_active' => true,
        ]);

        // Attach methods to zones
        $standard->zones()->attach($zone1, ['cost_override' => 30]);
        $standard->zones()->attach($zone2, ['cost_override' => 50]);
        
        $express->zones()->attach($zone1, ['cost_override' => 80]);
        $express->zones()->attach($zone2, ['cost_override' => 120]);
    }
}
