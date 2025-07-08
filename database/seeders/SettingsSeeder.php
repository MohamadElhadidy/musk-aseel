<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name', 'value' => ['en' => 'MyShop', 'ar' => 'متجري'], 'group' => 'general'],
            ['key' => 'site_tagline', 'value' => ['en' => 'Your Trusted Online Store', 'ar' => 'متجرك الإلكتروني الموثوق'], 'group' => 'general'],
            ['key' => 'contact_email', 'value' => 'support@myshop.com', 'group' => 'contact'],
            ['key' => 'contact_phone', 'value' => '+201234567890', 'group' => 'contact'],
            ['key' => 'contact_address', 'value' => ['en' => '123 Main St, Cairo, Egypt', 'ar' => '123 الشارع الرئيسي، القاهرة، مصر'], 'group' => 'contact'],
            ['key' => 'tax_rate', 'value' => 14, 'group' => 'shop'],
            ['key' => 'currency_position', 'value' => 'before', 'group' => 'shop'],
            ['key' => 'facebook_url', 'value' => 'https://facebook.com/myshop', 'group' => 'social'],
            ['key' => 'twitter_url', 'value' => 'https://twitter.com/myshop', 'group' => 'social'],
            ['key' => 'instagram_url', 'value' => 'https://instagram.com/myshop', 'group' => 'social'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
