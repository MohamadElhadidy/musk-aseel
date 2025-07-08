<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        $tags = [
            ['slug' => 'new-arrival', 'name' => ['en' => 'New Arrival', 'ar' => 'وصل حديثاً']],
            ['slug' => 'best-seller', 'name' => ['en' => 'Best Seller', 'ar' => 'الأكثر مبيعاً']],
            ['slug' => 'featured', 'name' => ['en' => 'Featured', 'ar' => 'مميز']],
            ['slug' => 'on-sale', 'name' => ['en' => 'On Sale', 'ar' => 'تخفيضات']],
            ['slug' => 'limited-edition', 'name' => ['en' => 'Limited Edition', 'ar' => 'إصدار محدود']],
            ['slug' => 'trending', 'name' => ['en' => 'Trending', 'ar' => 'رائج']],
        ];

        foreach ($tags as $tagData) {
            Tag::create($tagData);
        }
    }
}
