<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run(): void
    {
        $pages = [
            [
                'slug' => 'about-us',
                'translations' => [
                    'en' => [
                        'title' => 'About Us',
                        'content' => '<h2>Welcome to Our Store</h2><p>We are a leading e-commerce platform providing quality products at competitive prices.</p>',
                        'meta_title' => 'About Us - Your Trusted Online Store',
                    ],
                    'ar' => [
                        'title' => 'من نحن',
                        'content' => '<h2>مرحباً بك في متجرنا</h2><p>نحن منصة تجارة إلكترونية رائدة نقدم منتجات عالية الجودة بأسعار تنافسية.</p>',
                        'meta_title' => 'من نحن - متجرك الإلكتروني الموثوق',
                    ],
                ],
            ],
            [
                'slug' => 'terms-conditions',
                'translations' => [
                    'en' => [
                        'title' => 'Terms & Conditions',
                        'content' => '<h2>Terms of Service</h2><p>By using our website, you agree to these terms...</p>',
                    ],
                    'ar' => [
                        'title' => 'الشروط والأحكام',
                        'content' => '<h2>شروط الخدمة</h2><p>باستخدام موقعنا، فإنك توافق على هذه الشروط...</p>',
                    ],
                ],
            ],
            [
                'slug' => 'privacy-policy',
                'translations' => [
                    'en' => [
                        'title' => 'Privacy Policy',
                        'content' => '<h2>Privacy Policy</h2><p>We respect your privacy and protect your personal information...</p>',
                    ],
                    'ar' => [
                        'title' => 'سياسة الخصوصية',
                        'content' => '<h2>سياسة الخصوصية</h2><p>نحن نحترم خصوصيتك ونحمي معلوماتك الشخصية...</p>',
                    ],
                ],
            ],
        ];

        foreach ($pages as $pageData) {
            $page = Page::create([
                'slug' => $pageData['slug'],
                'is_active' => true,
            ]);

            foreach ($pageData['translations'] as $locale => $translation) {
                $page->createTranslation($translation, $locale);
            }
        }
    }
}
