<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Home Page
Volt::route('/', 'home')->name('home');

// Categories
Volt::route('/categories', 'category-listing')->name('categories.index');
Volt::route('/categories/{slug}', 'category-listing')->name('categories.show');

// Products
Volt::route('/products/{slug}', 'product-details')->name('products.show');

// Search
Volt::route('/search', 'search-results')->name('search');

// Cart & Wishlist
Volt::route('/cart', 'cart')->name('cart');
Volt::route('/wishlist', 'wishlist')->name('wishlist');

// Static Pages
Volt::route('/contact', 'contact')->name('contact');
Volt::route('/faq', 'faq')->name('faq');
Volt::route('/pages/{slug}', 'cms-page')->name('pages.show');

// Authentication Routes (Guest only)
Route::middleware(['guest'])->group(function () {
    Volt::route('/login', 'auth.login')->name('login');
    Volt::route('/register', 'auth.register')->name('register');
});

// Checkout & Order Success (Auth required)
Route::middleware(['auth'])->group(function () {
    Volt::route('/checkout', 'checkout')->name('checkout');
    Volt::route('/order-success/{orderNumber}', 'order-success')->name('order.success');
});

// Account Routes (Auth required)
Route::middleware(['auth'])->prefix('account')->name('account.')->group(function () {
    Volt::route('/', 'account.dashboard')->name('dashboard');
    Volt::route('/orders', 'account.order-history')->name('orders.index');
    Volt::route('/orders/{order:order_number}', 'account.order-details')->name('orders.show');
    Volt::route('/addresses', 'account.address-book')->name('addresses');
    Volt::route('/profile', 'account.profile-settings')->name('profile');
});


Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/', 'admin.dashboard')->name('dashboard');
    Volt::route('/products', 'admin.products-list')->name('products');
    Volt::route('/products/create', 'admin.products.create')->name('products.create');
    Volt::route('/orders', 'admin.orders')->name('orders');
    Volt::route('/orders/{id}', 'admin.order-details');
    Volt::route('/categories', 'admin.categories')->name('categories');
    Volt::route('/brands', 'admin.brands')->name('brands');
    Volt::route('/tags', 'admin.tags')->name('tags');
    Volt::route('/customers', 'admin.customers')->name('customers');
    Volt::route('/customers/{id}', 'admin.customer-details')->name('customers.details');

    Volt::route('/coupons', 'admin.coupons')->name('coupons');
    Volt::route('/reviews', 'admin.reviews')->name('reviews');
});









// Newsletter Routes
Route::get('/newsletter/unsubscribe/{token}', function ($token) {
    $subscriber = \App\Models\NewsletterSubscriber::where('token', $token)->first();

    if ($subscriber) {
        $subscriber->unsubscribe();
        return view('newsletter.unsubscribed');
    }

    return redirect('/');
})->name('newsletter.unsubscribe');

// Logout Route
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');

// Language Switcher (if not using Livewire for this)
Route::get('/language/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'ar'])) {
        session(['locale' => $locale]);

        if (auth()->check()) {
            auth()->user()->update(['preferred_locale' => $locale]);
        }
    }

    return redirect()->back();
})->name('language.switch');

// Admin Routes (Optional - for future implementation)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Admin dashboard and management routes would go here
    // Volt::route('/', 'admin.dashboard')->name('dashboard');
    // Volt::route('/products', 'admin.products.index')->name('products.index');
    // etc.
});

// API Routes for AJAX calls (if needed)
Route::prefix('api')->name('api.')->group(function () {
    // Get cities by country
    Route::get('/countries/{country}/cities', function ($countryId) {
        return \App\Models\City::where('country_id', $countryId)
            ->active()
            ->get()
            ->map(function ($city) {
                return [
                    'id' => $city->id,
                    'name' => $city->name,
                ];
            });
    })->name('cities.by-country');
});

// Fallback route (404)
Route::fallback(function () {
    return view('errors.404');
});
