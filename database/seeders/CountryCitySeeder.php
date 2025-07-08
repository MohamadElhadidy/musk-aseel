<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountryCitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        $countries = [
            [
                'code' => 'EG',
                'name' => ['en' => 'Egypt', 'ar' => 'مصر'],
                'cities' => [
                    ['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']],
                    ['name' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية']],
                    ['name' => ['en' => 'Giza', 'ar' => 'الجيزة']],
                    ['name' => ['en' => 'Damietta', 'ar' => 'دمياط']],
                    ['name' => ['en' => 'Port Said', 'ar' => 'بورسعيد']],
                ],
            ],
            [
                'code' => 'SA',
                'name' => ['en' => 'Saudi Arabia', 'ar' => 'المملكة العربية السعودية'],
                'cities' => [
                    ['name' => ['en' => 'Riyadh', 'ar' => 'الرياض']],
                    ['name' => ['en' => 'Jeddah', 'ar' => 'جدة']],
                    ['name' => ['en' => 'Mecca', 'ar' => 'مكة']],
                    ['name' => ['en' => 'Medina', 'ar' => 'المدينة']],
                    ['name' => ['en' => 'Dammam', 'ar' => 'الدمام']],
                ],
            ],
            [
                'code' => 'AE',
                'name' => ['en' => 'United Arab Emirates', 'ar' => 'الإمارات العربية المتحدة'],
                'cities' => [
                    ['name' => ['en' => 'Dubai', 'ar' => 'دبي']],
                    ['name' => ['en' => 'Abu Dhabi', 'ar' => 'أبوظبي']],
                    ['name' => ['en' => 'Sharjah', 'ar' => 'الشارقة']],
                    ['name' => ['en' => 'Ajman', 'ar' => 'عجمان']],
                ],
            ],
        ];

        foreach ($countries as $countryData) {
            $country = Country::create([
                'code' => $countryData['code'],
                'name' => $countryData['name'],
                'is_active' => true,
            ]);

            foreach ($countryData['cities'] as $cityData) {
                City::create([
                    'country_id' => $country->id,
                    'name' => $cityData['name'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
