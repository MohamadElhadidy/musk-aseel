<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Tag;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Electronics - Smartphones
            [
                'category' => 'smartphones',
                'brand' => 'apple',
                'sku' => 'IPH-15-PRO',
                'price' => 999.99,
                'compare_price' => 1199.99,
                'quantity' => 50,
                'is_featured' => true,
                'tags' => ['new-arrival', 'best-seller'],
                'translations' => [
                    'en' => [
                        'name' => 'iPhone 15 Pro',
                        'short_description' => 'The most advanced iPhone ever',
                        'description' => 'Experience the future with iPhone 15 Pro. Featuring the revolutionary A17 Pro chip, advanced camera system, and titanium design.',
                    ],
                    'ar' => [
                        'name' => 'آيفون 15 برو',
                        'short_description' => 'أكثر هاتف آيفون تقدماً على الإطلاق',
                        'description' => 'اختبر المستقبل مع آيفون 15 برو. يتميز بشريحة A17 Pro الثورية، ونظام كاميرا متقدم، وتصميم من التيتانيوم.',
                    ],
                ],
                'variants' => [
                    ['sku' => 'IPH-15-PRO-128GB', 'attributes' => ['storage' => '128GB'], 'price' => 999.99, 'quantity' => 20],
                    ['sku' => 'IPH-15-PRO-256GB', 'attributes' => ['storage' => '256GB'], 'price' => 1099.99, 'quantity' => 15],
                    ['sku' => 'IPH-15-PRO-512GB', 'attributes' => ['storage' => '512GB'], 'price' => 1299.99, 'quantity' => 10],
                ],
            ],
            [
                'category' => 'smartphones',
                'brand' => 'samsung',
                'sku' => 'SAM-S24-ULTRA',
                'price' => 1199.99,
                'compare_price' => 1399.99,
                'quantity' => 40,
                'is_featured' => true,
                'tags' => ['new-arrival', 'featured'],
                'translations' => [
                    'en' => [
                        'name' => 'Samsung Galaxy S24 Ultra',
                        'short_description' => 'Epic. Just like that.',
                        'description' => 'Meet Galaxy S24 Ultra, the ultimate form of Galaxy Ultra with a new titanium exterior and a 6.8" flat display.',
                    ],
                    'ar' => [
                        'name' => 'سامسونج جالاكسي S24 ألترا',
                        'short_description' => 'ملحمي. هكذا ببساطة.',
                        'description' => 'تعرف على Galaxy S24 Ultra، الشكل النهائي لـ Galaxy Ultra مع هيكل خارجي جديد من التيتانيوم وشاشة مسطحة مقاس 6.8 بوصة.',
                    ],
                ],
            ],
            
            // Electronics - Laptops
            [
                'category' => 'laptops',
                'brand' => 'apple',
                'sku' => 'MBP-16-M3',
                'price' => 2499.99,
                'compare_price' => 2799.99,
                'quantity' => 25,
                'is_featured' => true,
                'tags' => ['best-seller', 'featured'],
                'translations' => [
                    'en' => [
                        'name' => 'MacBook Pro 16" M3',
                        'short_description' => 'Supercharged by M3 Pro and M3 Max',
                        'description' => 'The most powerful MacBook Pro ever is here. With the blazing-fast M3 Pro or M3 Max chip, exceptional battery life, and a brilliant Liquid Retina XDR display.',
                    ],
                    'ar' => [
                        'name' => 'ماك بوك برو 16 بوصة M3',
                        'short_description' => 'مدعوم بشرائح M3 Pro و M3 Max',
                        'description' => 'أقوى ماك بوك برو على الإطلاق هنا. مع شريحة M3 Pro أو M3 Max فائقة السرعة، وعمر بطارية استثنائي، وشاشة Liquid Retina XDR رائعة.',
                    ],
                ],
            ],
            
            // Fashion - Men's Clothing
            [
                'category' => 'mens-clothing',
                'brand' => 'nike',
                'sku' => 'NK-TEE-001',
                'price' => 29.99,
                'compare_price' => 39.99,
                'quantity' => 100,
                'tags' => ['on-sale'],
                'translations' => [
                    'en' => [
                        'name' => 'Nike Dri-FIT T-Shirt',
                        'short_description' => 'Stay dry and comfortable',
                        'description' => 'The Nike Dri-FIT T-Shirt delivers a soft feel, sweat-wicking performance and great range of motion to get you through your workout in total comfort.',
                    ],
                    'ar' => [
                        'name' => 'تي شيرت نايكي Dri-FIT',
                        'short_description' => 'ابق جافاً ومرتاحاً',
                        'description' => 'يوفر تي شيرت Nike Dri-FIT ملمسًا ناعمًا وأداءً ماصًا للعرق ونطاق حركة رائع لمساعدتك على إكمال تمرينك براحة تامة.',
                    ],
                ],
                'variants' => [
                    ['sku' => 'NK-TEE-001-S-BLK', 'attributes' => ['size' => 'S', 'color' => 'Black'], 'price' => 29.99, 'quantity' => 25],
                    ['sku' => 'NK-TEE-001-M-BLK', 'attributes' => ['size' => 'M', 'color' => 'Black'], 'price' => 29.99, 'quantity' => 25],
                    ['sku' => 'NK-TEE-001-L-BLK', 'attributes' => ['size' => 'L', 'color' => 'Black'], 'price' => 29.99, 'quantity' => 25],
                    ['sku' => 'NK-TEE-001-XL-BLK', 'attributes' => ['size' => 'XL', 'color' => 'Black'], 'price' => 29.99, 'quantity' => 25],
                ],
            ],
            
            // Fashion - Women's Clothing
            [
                'category' => 'womens-clothing',
                'brand' => 'adidas',
                'sku' => 'AD-DRESS-001',
                'price' => 59.99,
                'compare_price' => 79.99,
                'quantity' => 80,
                'tags' => ['trending', 'on-sale'],
                'translations' => [
                    'en' => [
                        'name' => 'Adidas 3-Stripes Dress',
                        'short_description' => 'Classic sporty style',
                        'description' => 'This adidas dress keeps things casual with a comfortable cotton build and iconic 3-Stripes down the sides.',
                    ],
                    'ar' => [
                        'name' => 'فستان أديداس بثلاثة خطوط',
                        'short_description' => 'أسلوب رياضي كلاسيكي',
                        'description' => 'يحافظ فستان أديداس هذا على الطابع الكاجوال مع بنية قطنية مريحة وخطوط أيقونية ثلاثية على الجانبين.',
                    ],
                ],
            ],
            
            // Home & Garden - Furniture
            [
                'category' => 'furniture',
                'brand' => null,
                'sku' => 'SOFA-MOD-001',
                'price' => 899.99,
                'quantity' => 15,
                'is_featured' => true,
                'tags' => ['featured'],
                'translations' => [
                    'en' => [
                        'name' => 'Modern 3-Seater Sofa',
                        'short_description' => 'Comfortable and stylish',
                        'description' => 'Transform your living room with this modern 3-seater sofa. Features premium upholstery, sturdy construction, and timeless design.',
                    ],
                    'ar' => [
                        'name' => 'أريكة عصرية بثلاثة مقاعد',
                        'short_description' => 'مريحة وأنيقة',
                        'description' => 'حول غرفة معيشتك مع هذه الأريكة العصرية بثلاثة مقاعد. تتميز بتنجيد فاخر وبناء قوي وتصميم خالد.',
                    ],
                ],
            ],
        ];

        foreach ($products as $productData) {
            // Find category and brand
            $category = Category::where('slug', $productData['category'])->first();
            $brand = $productData['brand'] ? Brand::where('slug', $productData['brand'])->first() : null;
            
            // Create product
            $product = Product::create([
                'brand_id' => $brand?->id,
                'sku' => $productData['sku'],
                'slug' => Str::slug($productData['translations']['en']['name']),
                'price' => $productData['price'],
                'compare_price' => $productData['compare_price'] ?? null,
                'quantity' => $productData['quantity'],
                'is_featured' => $productData['is_featured'] ?? false,
                'is_active' => true,
            ]);
            
            // Create translations
            foreach ($productData['translations'] as $locale => $translation) {
                $product->createTranslation($translation, $locale);
            }
            
            // Attach to category
            if ($category) {
                $product->categories()->attach($category);
            }
            
            // Attach tags
            if (isset($productData['tags'])) {
                $tags = Tag::whereIn('slug', $productData['tags'])->pluck('id');
                $product->tags()->attach($tags);
            }
            
            // Create variants
            if (isset($productData['variants'])) {
                foreach ($productData['variants'] as $variantData) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'],
                        'attributes' => $variantData['attributes'],
                        'price' => $variantData['price'],
                        'quantity' => $variantData['quantity'],
                        'is_active' => true,
                    ]);
                }
            }
            
            // Create sample images
            for ($i = 1; $i <= 3; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => "products/{$product->slug}-{$i}.jpg",
                    'is_primary' => $i === 1,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}