<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'code' => 'ar',
                'name' => 'Arabic',
                'native_name' => 'العربية',
                'direction' => 'rtl',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'fr',
                'name' => 'French',
                'native_name' => 'Français',
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'es',
                'name' => 'Spanish',
                'native_name' => 'Español',
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'de',
                'name' => 'German',
                'native_name' => 'Deutsch',
                'direction' => 'ltr',
                'is_active' => false,
                'is_default' => false,
            ],
            [
                'code' => 'it',
                'name' => 'Italian',
                'native_name' => 'Italiano',
                'direction' => 'ltr',
                'is_active' => false,
                'is_default' => false,
            ],
            [
                'code' => 'tr',
                'name' => 'Turkish',
                'native_name' => 'Türkçe',
                'direction' => 'ltr',
                'is_active' => false,
                'is_default' => false,
            ],
        ];

        foreach ($languages as $languageData) {
            $language = Language::create($languageData);
            $this->createLanguageFiles($language);
        }
    }


    protected function createLanguageFiles(Language $language)
    {
        $langPath = resource_path('lang/' . $language->code);

        if (!File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);

            // Create basic language files
            $files = $this->getLanguageFileTemplates($language->code);

            foreach ($files as $filename => $content) {
                File::put($langPath . '/' . $filename, $content);
            }
        }
    }

    protected function getLanguageFileTemplates($locale)
    {
        $translations = [
            'en' => [
                'messages.php' => $this->getEnglishMessages(),
                'validation.php' => $this->getEnglishValidation(),
                'auth.php' => $this->getEnglishAuth(),
            ],
            'ar' => [
                'messages.php' => $this->getArabicMessages(),
                'validation.php' => $this->getArabicValidation(),
                'auth.php' => $this->getArabicAuth(),
            ],
        ];

        return $translations[$locale] ?? $translations['en'];
    }

    protected function getEnglishMessages()
    {
        return <<<'PHP'
<?php

return [
    // General
    'welcome' => 'Welcome',
    'home' => 'Home',
    'shop' => 'Shop',
    'products' => 'Products',
    'categories' => 'Categories',
    'brands' => 'Brands',
    'cart' => 'Cart',
    'checkout' => 'Checkout',
    'my_account' => 'My Account',
    'login' => 'Login',
    'register' => 'Register',
    'logout' => 'Logout',
    'search' => 'Search',
    
    // Product
    'add_to_cart' => 'Add to Cart',
    'add_to_wishlist' => 'Add to Wishlist',
    'in_stock' => 'In Stock',
    'out_of_stock' => 'Out of Stock',
    'quantity' => 'Quantity',
    'price' => 'Price',
    'description' => 'Description',
    'reviews' => 'Reviews',
    
    // Cart
    'shopping_cart' => 'Shopping Cart',
    'cart_empty' => 'Your cart is empty',
    'continue_shopping' => 'Continue Shopping',
    'update_cart' => 'Update Cart',
    'proceed_to_checkout' => 'Proceed to Checkout',
    'subtotal' => 'Subtotal',
    'total' => 'Total',
    
    // Messages
    'success' => 'Success',
    'error' => 'Error',
    'info' => 'Info',
    'warning' => 'Warning',
];
PHP;
    }

    protected function getArabicMessages()
    {
        return <<<'PHP'
<?php

return [
    // General
    'welcome' => 'مرحبا',
    'home' => 'الرئيسية',
    'shop' => 'المتجر',
    'products' => 'المنتجات',
    'categories' => 'الفئات',
    'brands' => 'العلامات التجارية',
    'cart' => 'السلة',
    'checkout' => 'الدفع',
    'my_account' => 'حسابي',
    'login' => 'تسجيل الدخول',
    'register' => 'إنشاء حساب',
    'logout' => 'تسجيل الخروج',
    'search' => 'بحث',
    
    // Product
    'add_to_cart' => 'أضف إلى السلة',
    'add_to_wishlist' => 'أضف إلى المفضلة',
    'in_stock' => 'متوفر',
    'out_of_stock' => 'نفذ المخزون',
    'quantity' => 'الكمية',
    'price' => 'السعر',
    'description' => 'الوصف',
    'reviews' => 'التقييمات',
    
    // Cart
    'shopping_cart' => 'سلة التسوق',
    'cart_empty' => 'سلة التسوق فارغة',
    'continue_shopping' => 'متابعة التسوق',
    'update_cart' => 'تحديث السلة',
    'proceed_to_checkout' => 'متابعة للدفع',
    'subtotal' => 'المجموع الفرعي',
    'total' => 'المجموع',
    
    // Messages
    'success' => 'نجاح',
    'error' => 'خطأ',
    'info' => 'معلومة',
    'warning' => 'تحذير',
];
PHP;
    }

    protected function getEnglishValidation()
    {
        return <<<'PHP'
<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
    ],
];
PHP;
    }

    protected function getArabicValidation()
    {
        return <<<'PHP'
<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صحيح.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'min' => [
        'string' => 'يجب أن يكون :attribute على الأقل :min أحرف.',
    ],
];
PHP;
    }

    protected function getEnglishAuth()
    {
        return <<<'PHP'
<?php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
PHP;
    }

    protected function getArabicAuth()
    {
        return <<<'PHP'
<?php

return [
    'failed' => 'بيانات الاعتماد هذه لا تتطابق مع سجلاتنا.',
    'password' => 'كلمة المرور المقدمة غير صحيحة.',
    'throttle' => 'محاولات تسجيل دخول كثيرة جداً. يرجى المحاولة مرة أخرى بعد :seconds ثانية.',
];
PHP;
    }
}
