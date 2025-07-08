<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Slider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SliderBannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sliders
        $sliders = [
            [
                'title' => ['en' => 'Summer Collection', 'ar' => 'مجموعة الصيف'],
                'subtitle' => ['en' => 'Up to 50% Off', 'ar' => 'خصم يصل إلى 50%'],
                'image' => 'sliders/summer-collection.jpg',
                'link' => '/categories/fashion',
                'button_text' => ['en' => 'Shop Now', 'ar' => 'تسوق الآن'],
                'sort_order' => 1,
            ],
            [
                'title' => ['en' => 'New Electronics', 'ar' => 'إلكترونيات جديدة'],
                'subtitle' => ['en' => 'Latest Tech Gadgets', 'ar' => 'أحدث الأجهزة التقنية'],
                'image' => 'sliders/electronics.jpg',
                'link' => '/categories/electronics',
                'button_text' => ['en' => 'Explore', 'ar' => 'استكشف'],
                'sort_order' => 2,
            ],
        ];

        foreach ($sliders as $sliderData) {
            Slider::create($sliderData);
        }

        // Banners
        $banners = [
            [
                'position' => 'home_top',
                'title' => ['en' => 'Free Shipping', 'ar' => 'شحن مجاني'],
                'image' => 'banners/free-shipping.jpg',
                'link' => '/shipping-info',
                'sort_order' => 1,
            ],
            [
                'position' => 'home_middle',
                'title' => ['en' => 'Special Offers', 'ar' => 'عروض خاصة'],
                'image' => 'banners/special-offers.jpg',
                'link' => '/deals',
                'sort_order' => 1,
            ],
        ];

        foreach ($banners as $bannerData) {
            Banner::create($bannerData);
        }
    }
}
