<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'electronics',
                'icon' => 'laptop',
                'is_featured' => true,
                'translations' => [
                    'en' => ['name' => 'Electronics', 'description' => 'Latest electronic devices and gadgets'],
                    'ar' => ['name' => 'الإلكترونيات', 'description' => 'أحدث الأجهزة الإلكترونية والأدوات'],
                ],
                'children' => [
                    [
                        'slug' => 'smartphones',
                        'translations' => [
                            'en' => ['name' => 'Smartphones', 'description' => 'Latest smartphones and accessories'],
                            'ar' => ['name' => 'الهواتف الذكية', 'description' => 'أحدث الهواتف الذكية وملحقاتها'],
                        ],
                    ],
                    [
                        'slug' => 'laptops',
                        'translations' => [
                            'en' => ['name' => 'Laptops', 'description' => 'Laptops for work and gaming'],
                            'ar' => ['name' => 'أجهزة الكمبيوتر المحمولة', 'description' => 'أجهزة كمبيوتر محمولة للعمل والألعاب'],
                        ],
                    ],
                    [
                        'slug' => 'tablets',
                        'translations' => [
                            'en' => ['name' => 'Tablets', 'description' => 'Tablets and e-readers'],
                            'ar' => ['name' => 'الأجهزة اللوحية', 'description' => 'الأجهزة اللوحية وأجهزة القراءة الإلكترونية'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'fashion',
                'icon' => 'shirt',
                'is_featured' => true,
                'translations' => [
                    'en' => ['name' => 'Fashion', 'description' => 'Clothing and accessories'],
                    'ar' => ['name' => 'الأزياء', 'description' => 'الملابس والإكسسوارات'],
                ],
                'children' => [
                    [
                        'slug' => 'mens-clothing',
                        'translations' => [
                            'en' => ['name' => "Men's Clothing", 'description' => 'Fashion for men'],
                            'ar' => ['name' => 'ملابس رجالية', 'description' => 'أزياء للرجال'],
                        ],
                    ],
                    [
                        'slug' => 'womens-clothing',
                        'translations' => [
                            'en' => ['name' => "Women's Clothing", 'description' => 'Fashion for women'],
                            'ar' => ['name' => 'ملابس نسائية', 'description' => 'أزياء للنساء'],
                        ],
                    ],
                    [
                        'slug' => 'shoes',
                        'translations' => [
                            'en' => ['name' => 'Shoes', 'description' => 'Footwear for all occasions'],
                            'ar' => ['name' => 'الأحذية', 'description' => 'أحذية لجميع المناسبات'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'home-garden',
                'icon' => 'home',
                'is_featured' => true,
                'translations' => [
                    'en' => ['name' => 'Home & Garden', 'description' => 'Everything for your home'],
                    'ar' => ['name' => 'المنزل والحديقة', 'description' => 'كل شيء لمنزلك'],
                ],
                'children' => [
                    [
                        'slug' => 'furniture',
                        'translations' => [
                            'en' => ['name' => 'Furniture', 'description' => 'Home and office furniture'],
                            'ar' => ['name' => 'الأثاث', 'description' => 'أثاث المنزل والمكتب'],
                        ],
                    ],
                    [
                        'slug' => 'kitchen',
                        'translations' => [
                            'en' => ['name' => 'Kitchen', 'description' => 'Kitchen appliances and tools'],
                            'ar' => ['name' => 'المطبخ', 'description' => 'أجهزة وأدوات المطبخ'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'sports',
                'icon' => 'dumbbell',
                'translations' => [
                    'en' => ['name' => 'Sports & Outdoors', 'description' => 'Sports equipment and outdoor gear'],
                    'ar' => ['name' => 'الرياضة والأنشطة الخارجية', 'description' => 'معدات رياضية ومعدات خارجية'],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $this->createCategory($categoryData);
        }
    }

    private function createCategory($data, $parentId = null)
    {
        $category = Category::create([
            'parent_id' => $parentId,
            'slug' => $data['slug'],
            'icon' => $data['icon'] ?? null,
            'is_featured' => $data['is_featured'] ?? false,
            'is_active' => true,
        ]);

        foreach ($data['translations'] as $locale => $translation) {
            $category->createTranslation($translation, $locale);
        }

        if (isset($data['children'])) {
            foreach ($data['children'] as $child) {
                $this->createCategory($child, $category->id);
            }
        }
    }
}

