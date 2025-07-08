<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Volt::route('/', 'home')->name('home');

Volt::route('cart', 'cart')->name('cart');
Volt::route('categories', 'list')->name('categories');
Volt::route('categories/{category}', 'list')->name('categories');
Volt::route('products/{product:slug}', 'products.show')->name('products.show');




Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Volt::route('wishlist', 'wishlist')->name('wishlist');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
