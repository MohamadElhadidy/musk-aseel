<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'translations' => [
                    'en' => [
                        'question' => 'How long does shipping take?',
                        'answer' => 'Standard shipping takes 5-7 business days, while express shipping takes 1-2 business days.',
                    ],
                    'ar' => [
                        'question' => 'كم يستغرق الشحن؟',
                        'answer' => 'يستغرق الشحن العادي 5-7 أيام عمل، بينما يستغرق الشحن السريع 1-2 أيام عمل.',
                    ],
                ],
            ],
            [
                'translations' => [
                    'en' => [
                        'question' => 'What is your return policy?',
                        'answer' => 'We offer a 30-day return policy for all products in their original condition.',
                    ],
                    'ar' => [
                        'question' => 'ما هي سياسة الإرجاع الخاصة بكم؟',
                        'answer' => 'نقدم سياسة إرجاع لمدة 30 يومًا لجميع المنتجات في حالتها الأصلية.',
                    ],
                ],
            ],
            [
                'translations' => [
                    'en' => [
                        'question' => 'Do you ship internationally?',
                        'answer' => 'Currently, we ship to Egypt, Saudi Arabia, and UAE.',
                    ],
                    'ar' => [
                        'question' => 'هل تشحنون دولياً؟',
                        'answer' => 'حالياً، نقوم بالشحن إلى مصر والمملكة العربية السعودية والإمارات العربية المتحدة.',
                    ],
                ],
            ],
        ];

        foreach ($faqs as $index => $faqData) {
            $faq = Faq::create([
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);

            foreach ($faqData['translations'] as $locale => $translation) {
                $faq->createTranslation($translation, $locale);
            }
        }
    }
}
