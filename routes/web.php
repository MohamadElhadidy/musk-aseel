<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public routes
Volt::route('/', 'home')->name('home');
Volt::route('cart', 'cart')->name('cart');
Volt::route('categories', 'category-listing')->name('categories');
Volt::route('categories/{category:slug}', 'category-listing')->name('categories');
Volt::route('contact', 'contact')->name('contact');
Volt::route('faq', 'faq')->name('faq');
Volt::route('search', 'search-results')->name('search');
Volt::route('products/{product:slug}', 'product-details')->name('products.show');
Volt::route('order-success/{orderNumber}', 'order-success')->name('order.success');
Volt::route('pages/{slug}', 'cms-page')->name('pages.show');
Volt::route('checkout', 'checkout')->name('checkout');


Route::middleware(['auth'])->group(function () {
    Volt::route('account', 'account.index')->name('account');
    Volt::route('account/orders', 'account.orders')->name('account.orders');
    Volt::route('account/orders/{order:order_number}', 'account.order-details')->name('account.order-details');
    Volt::route('account/addresses', 'account.addresses')->name('account.addresses');
    Volt::route('account/profile', 'account.profile')->name('account.profile');

    Volt::route('wishlist', 'account.wishlist')->name('account.wishlist');

    




    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
