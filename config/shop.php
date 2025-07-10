<?php

// config/shop.php

return [
    /*
    |--------------------------------------------------------------------------
    | Shop Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all the configuration options for the eCommerce shop.
    |
    */

    'name' => env('SHOP_NAME', 'MyShop'),
    
    'currency' => [
        'default' => 'USD',
        'position' => 'before', // before or after
        'thousand_separator' => ',',
        'decimal_separator' => '.',
        'decimals' => 2,
    ],
    
    'tax' => [
        'rate' => env('TAX_RATE', 14), // percentage
        'included_in_price' => false,
    ],
    
    'shipping' => [
        'free_shipping_threshold' => env('FREE_SHIPPING_THRESHOLD', 100),
        'default_method' => 'standard',
    ],
    
    'cart' => [
        'cookie_lifetime' => 60 * 24 * 30, // 30 days
        'session_lifetime' => 120, // minutes
    ],
    
    'wishlist' => [
        'guest_enabled' => true,
        'max_items' => 50,
    ],
    
    'products' => [
        'per_page' => 12,
        'image_sizes' => [
            'thumbnail' => [150, 150],
            'small' => [300, 300],
            'medium' => [600, 600],
            'large' => [1200, 1200],
        ],
        'placeholder_image' => 'images/product-placeholder.png',
    ],
    
    'orders' => [
        'prefix' => 'ORD',
        'statuses' => [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ],
        'cancelable_statuses' => ['pending', 'processing'],
        'refundable_statuses' => ['delivered'],
    ],
    
    'payment' => [
        'methods' => [
            'cod' => [
                'enabled' => true,
                'name' => 'Cash on Delivery',
                'fee' => 0,
            ],
            'card' => [
                'enabled' => true,
                'name' => 'Credit/Debit Card',
                'gateway' => env('PAYMENT_GATEWAY', 'stripe'),
            ],
        ],
    ],
    
    'notifications' => [
        'order_placed' => true,
        'order_shipped' => true,
        'order_delivered' => true,
        'order_cancelled' => true,
        'newsletter_welcome' => true,
    ],
    
    'seo' => [
        'title_suffix' => ' - ' . env('APP_NAME', 'MyShop'),
        'meta_description' => 'Shop the latest products at great prices',
        'meta_keywords' => 'shopping, online store, ecommerce',
    ],
    
    'locales' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'dir' => 'rtl',
        ],
    ],
    
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'pagination' => 20,
    ],
];