<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Home Page
Volt::route('/', 'pages.home')->name('home');

// Language & Currency Switchers
Route::get('/language/{locale}', [LocaleController::class, 'switchLanguage'])->name('language.switch');
Route::get('/currency/{code}', [CurrencyController::class, 'switchCurrency'])->name('currency.switch');

/*
|--------------------------------------------------------------------------
| Product & Category Routes
|--------------------------------------------------------------------------
*/
// Categories
Volt::route('/categories', 'pages.category-listing')->name('categories.index');
Volt::route('/categories/{slug}', 'pages.category-show')->name('categories.show');

// Products
Volt::route('/products', 'pages.product-listing')->name('products.index');
Volt::route('/products/{slug}', 'pages.product-details')->name('products.show');

// Search
Volt::route('/search', 'pages.search-results')->name('search');

// Brands
Volt::route('/brands', 'pages.brand-listing')->name('brands.index');
Volt::route('/brands/{slug}', 'pages.brand-show')->name('brands.show');

/*
|--------------------------------------------------------------------------
| Shopping Routes
|--------------------------------------------------------------------------
*/
// Cart
Volt::route('/cart', 'pages.cart')->name('cart');

// Wishlist
Volt::route('/wishlist', 'pages.wishlist')->name('wishlist');

// Compare
Volt::route('/compare', 'pages.compare')->name('compare');

/*
|--------------------------------------------------------------------------
| Checkout Routes (Auth Required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Volt::route('/checkout', 'pages.checkout')->name('checkout');
    Volt::route('/checkout/shipping', 'pages.checkout-shipping')->name('checkout.shipping');
    Volt::route('/checkout/payment', 'pages.checkout-payment')->name('checkout.payment');
    Volt::route('/order-success/{orderNumber}', 'pages.order-success')->name('order.success');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['guest'])->group(function () {
    Volt::route('/login', 'auth.login')->name('login');
    Volt::route('/register', 'auth.register')->name('register');
    Volt::route('/forgot-password', 'auth.forgot-password')->name('password.request');
    Volt::route('/reset-password/{token}', 'auth.reset-password')->name('password.reset');
});

Route::middleware(['auth'])->group(function () {
    Volt::route('/verify-email', 'auth.verify-email')->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Volt::route('/confirm-password', 'auth.confirm-password')->name('password.confirm');
    
    // Logout
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');
});

/*
|--------------------------------------------------------------------------
| Account/Customer Routes (Auth Required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('account')->name('account.')->group(function () {
    Volt::route('/', 'account.dashboard')->name('dashboard');
    
    // Orders
    Volt::route('/orders', 'account.orders.index')->name('orders.index');
    Volt::route('/orders/{order:order_number}', 'account.orders.show')->name('orders.show');
    Volt::route('/orders/{order:order_number}/track', 'account.orders.track')->name('orders.track');
    Volt::route('/orders/{order:order_number}/invoice', 'account.orders.invoice')->name('orders.invoice');
    
    // Addresses
    Volt::route('/addresses', 'account.addresses.index')->name('addresses.index');
    Volt::route('/addresses/create', 'account.addresses.create')->name('addresses.create');
    Volt::route('/addresses/{address}/edit', 'account.addresses.edit')->name('addresses.edit');
    
    // Profile
    Volt::route('/profile', 'account.profile')->name('profile');
    Volt::route('/change-password', 'account.change-password')->name('change-password');
    
    // Wishlist
    Volt::route('/wishlist', 'account.wishlist')->name('wishlist');
    
    // Reviews
    Volt::route('/reviews', 'account.reviews')->name('reviews');
    
    // Wallet (if implemented)
    Volt::route('/wallet', 'account.wallet')->name('wallet');
    
    // Notifications
    Volt::route('/notifications', 'account.notifications')->name('notifications');
});

/*
|--------------------------------------------------------------------------
| Static Pages
|--------------------------------------------------------------------------
*/
Volt::route('/about', 'pages.about')->name('about');
Volt::route('/contact', 'pages.contact')->name('contact');
Volt::route('/faq', 'pages.faq')->name('faq');
Volt::route('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');
Volt::route('/terms-conditions', 'pages.terms-conditions')->name('terms-conditions');
Volt::route('/return-policy', 'pages.return-policy')->name('return-policy');
Volt::route('/shipping-info', 'pages.shipping-info')->name('shipping-info');

// CMS Pages (dynamic)
Volt::route('/pages/{slug}', 'pages.cms-page')->name('pages.show');

// Blog (if implemented)
Route::prefix('blog')->name('blog.')->group(function () {
    Volt::route('/', 'blog.index')->name('index');
    Volt::route('/category/{slug}', 'blog.category')->name('category');
    Volt::route('/{slug}', 'blog.show')->name('show');
});

/*
|--------------------------------------------------------------------------
| Newsletter Routes
|--------------------------------------------------------------------------
*/
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');

/*
|--------------------------------------------------------------------------
| Admin Routes (Auth + Admin Middleware Required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Volt::route('/', 'admin.dashboard')->name('dashboard');
    
    // Products Management
    Route::prefix('products')->name('products.')->group(function () {
        Volt::route('/', 'admin.products.index')->name('index');
        Volt::route('/create', 'admin.products.create')->name('create');
        Volt::route('/{product}/edit', 'admin.products.edit')->name('edit');
        Volt::route('/import', 'admin.products.import')->name('import');
        Volt::route('/export', 'admin.products.export')->name('export');
    });
    
    // Categories Management
    Route::prefix('categories')->name('categories.')->group(function () {
        Volt::route('/', 'admin.categories.index')->name('index');
        Volt::route('/create', 'admin.categories.create')->name('create');
        Volt::route('/{category}/edit', 'admin.categories.edit')->name('edit');
    });
    
    // Brands Management
    Route::prefix('brands')->name('brands.')->group(function () {
        Volt::route('/', 'admin.brands.index')->name('index');
        Volt::route('/create', 'admin.brands.create')->name('create');
        Volt::route('/{brand}/edit', 'admin.brands.edit')->name('edit');
    });
    
    // Tags Management
    Route::prefix('tags')->name('tags.')->group(function () {
        Volt::route('/', 'admin.tags.index')->name('index');
        Volt::route('/create', 'admin.tags.create')->name('create');
        Volt::route('/{tag}/edit', 'admin.tags.edit')->name('edit');
    });
    
    // Orders Management
    Route::prefix('orders')->name('orders.')->group(function () {
        Volt::route('/', 'admin.orders.index')->name('index');
        Volt::route('/{order}', 'admin.orders.show')->name('show');
        Volt::route('/{order}/invoice', 'admin.orders.invoice')->name('invoice');
        Volt::route('/{order}/ship', 'admin.orders.ship')->name('ship');
    });
    
    // Customers Management
    Route::prefix('customers')->name('customers.')->group(function () {
        Volt::route('/', 'admin.customers.index')->name('index');
        Volt::route('/{customer}', 'admin.customers.show')->name('show');
        Volt::route('/{customer}/orders', 'admin.customers.orders')->name('orders');
    });
    
    // Coupons Management
    Route::prefix('coupons')->name('coupons.')->group(function () {
        Volt::route('/', 'admin.coupons.index')->name('index');
        Volt::route('/create', 'admin.coupons.create')->name('create');
        Volt::route('/{coupon}/edit', 'admin.coupons.edit')->name('edit');
    });
    
    // Reviews Management
    Route::prefix('reviews')->name('reviews.')->group(function () {
        Volt::route('/', 'admin.reviews.index')->name('index');
        Volt::route('/{review}', 'admin.reviews.show')->name('show');
    });
    
    // Shipping Management
    Route::prefix('shipping')->name('shipping.')->group(function () {
        Volt::route('/zones', 'admin.shipping.zones')->name('zones');
        Volt::route('/methods', 'admin.shipping.methods')->name('methods');
        Volt::route('/rates', 'admin.shipping.rates')->name('rates');
    });
    
    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Volt::route('/general', 'admin.settings.general')->name('general');
        Volt::route('/languages', 'admin.settings.languages')->name('languages');
        Volt::route('/currencies', 'admin.settings.currencies')->name('currencies');
        Volt::route('/payment-methods', 'admin.settings.payment-methods')->name('payment-methods');
        Volt::route('/email', 'admin.settings.email')->name('email');
        Volt::route('/seo', 'admin.settings.seo')->name('seo');
        Volt::route('/social', 'admin.settings.social')->name('social');
        Volt::route('/maintenance', 'admin.settings.maintenance')->name('maintenance');
    });
    
    // CMS Management
    Route::prefix('cms')->name('cms.')->group(function () {
        Volt::route('/pages', 'admin.cms.pages')->name('pages');
        Volt::route('/pages/create', 'admin.cms.pages-create')->name('pages.create');
        Volt::route('/pages/{page}/edit', 'admin.cms.pages-edit')->name('pages.edit');
        Volt::route('/sliders', 'admin.cms.sliders')->name('sliders');
        Volt::route('/banners', 'admin.cms.banners')->name('banners');
        Volt::route('/faqs', 'admin.cms.faqs')->name('faqs');
    });
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Volt::route('/sales', 'admin.reports.sales')->name('sales');
        Volt::route('/products', 'admin.reports.products')->name('products');
        Volt::route('/customers', 'admin.reports.customers')->name('customers');
        Volt::route('/inventory', 'admin.reports.inventory')->name('inventory');
    });
    
    // Media Manager
    Volt::route('/media', 'admin.media')->name('media');
    
    // Activity Log
    Volt::route('/activity-log', 'admin.activity-log')->name('activity-log');
});

/*
|--------------------------------------------------------------------------
| API Routes for AJAX calls
|--------------------------------------------------------------------------
*/
Route::prefix('api')->name('api.')->group(function () {
    // Location APIs
    Route::get('/countries', [LocationController::class, 'countries'])->name('countries');
    Route::get('/countries/{country}/cities', [LocationController::class, 'cities'])->name('cities');
    
    // Shipping APIs
    Route::post('/shipping/calculate', [ShippingController::class, 'calculate'])->name('shipping.calculate');
    Route::get('/shipping/methods', [ShippingController::class, 'methods'])->name('shipping.methods');
    
    // Product APIs
    Route::get('/products/search', 'App\Http\Controllers\Api\ProductController@search')->name('products.search');
    Route::get('/products/{product}/variants', 'App\Http\Controllers\Api\ProductController@variants')->name('products.variants');
    
    // Cart APIs (if using API approach)
    Route::prefix('cart')->name('cart.')->group(function () {
        Route::post('/add', 'App\Http\Controllers\Api\CartController@add')->name('add');
        Route::post('/update', 'App\Http\Controllers\Api\CartController@update')->name('update');
        Route::post('/remove', 'App\Http\Controllers\Api\CartController@remove')->name('remove');
        Route::get('/count', 'App\Http\Controllers\Api\CartController@count')->name('count');
    });
    
    // Wishlist APIs
    Route::middleware(['auth'])->prefix('wishlist')->name('wishlist.')->group(function () {
        Route::post('/toggle', 'App\Http\Controllers\Api\WishlistController@toggle')->name('toggle');
        Route::get('/check/{product}', 'App\Http\Controllers\Api\WishlistController@check')->name('check');
    });
});

/*
|--------------------------------------------------------------------------
| Sitemap & Robots
|--------------------------------------------------------------------------
*/
Route::get('/sitemap.xml', 'App\Http\Controllers\SitemapController@index')->name('sitemap');
Route::get('/robots.txt', 'App\Http\Controllers\RobotsController@index')->name('robots');

/*
|--------------------------------------------------------------------------
| Fallback Route (404)
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return view('errors.404');
});