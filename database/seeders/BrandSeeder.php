<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            [
                'slug' => 'apple',
                'translations' => [
                    'en' => ['name' => 'Apple', 'description' => 'Think Different'],
                    'ar' => ['name' => 'آبل', 'description' => 'فكر بشكل مختلف'],
                ],
            ],
            [
                'slug' => 'samsung',
                'translations' => [
                    'en' => ['name' => 'Samsung', 'description' => 'Inspire the World, Create the Future'],
                    'ar' => ['name' => 'سامسونج', 'description' => 'ألهم العالم، اصنع المستقبل'],
                ],
            ],
            [
                'slug' => 'nike',
                'translations' => [
                    'en' => ['name' => 'Nike', 'description' => 'Just Do It'],
                    'ar' => ['name' => 'نايكي', 'description' => 'فقط افعلها'],
                ],
            ],
            [
                'slug' => 'adidas',
                'translations' => [
                    'en' => ['name' => 'Adidas', 'description' => 'Impossible is Nothing'],
                    'ar' => ['name' => 'أديداس', 'description' => 'لا شيء مستحيل'],
                ],
            ],
            [
                'slug' => 'sony',
                'translations' => [
                    'en' => ['name' => 'Sony', 'description' => 'Make.Believe'],
                    'ar' => ['name' => 'سوني', 'description' => 'اصنع.صدق'],
                ],
            ],
        ];

        foreach ($brands as $brandData) {
            $brand = Brand::create([
                'slug' => $brandData['slug'],
                'is_active' => true,
            ]);

            foreach ($brandData['translations'] as $locale => $translation) {
                $brand->createTranslation($translation, $locale);
            }
        }
    }
}
