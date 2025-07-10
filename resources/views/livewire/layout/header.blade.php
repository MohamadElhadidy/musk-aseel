<?php

use Livewire\Volt\Component;
use App\Models\Language;
use App\Models\Currency;
use App\Models\Category;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public string $searchQuery = '';
    public $languages;
    public $currencies;
    public $categories;
    public $cartItemsCount = 0;
    public $wishlistCount = 0;
    public bool $showMobileMenu = false;
    public bool $showSearchResults = false;
    public $searchResults = [];

    protected $listeners = [
        'cart-updated' => 'updateCounts',
        'wishlist-updated' => 'updateCounts'
    ];

    public function mount()
    {
        $this->languages = Language::active()->get();
        $this->currencies = Currency::active()->get();
        $this->categories = Category::with('children')
            ->active()
            ->root()
            ->orderBy('sort_order')
            ->get();

        $this->updateCounts();
    }

    public function updateCounts()
    {
        $cart = Cart::getCurrentCart();
        $this->cartItemsCount = $cart->items_count;

        if (auth()->check()) {
            $this->wishlistCount = auth()->user()->wishlist()->count();
        } else {
            // Get guest wishlist count from session
            $guestWishlist = session('wishlist', []);
            $this->wishlistCount = count($guestWishlist);
        }
    }

    public function changeLanguage($locale)
    {
        Cookie::queue(Cookie::forever('locale', $locale));
        return redirect()->to(url()->previous());
    }

    public function changeCurrency($currencyId)
    {
        Cookie::queue(Cookie::forever('currency', $currencyId));
        return redirect()->to(url()->previous());
    }

    public function updatedSearchQuery()
    {
        if (strlen($this->searchQuery) >= 2) {
            $this->searchResults = Product::active()
                ->where(function ($query) {
                    $query->whereHas('translations', function ($q) {
                        $q->where('locale', app()->getLocale())
                            ->where('name', 'like', "%{$this->searchQuery}%");
                    });
                })
                ->take(5)
                ->get();

            $this->showSearchResults = true;
        } else {
            $this->searchResults = [];
            $this->showSearchResults = false;
        }
    }

    public function search()
    {
        if ($this->searchQuery) {
            $this->redirect('/search?q=' . urlencode($this->searchQuery), navigate: true);
        }
    }

    public function toggleMobileMenu()
    {
        $this->showMobileMenu = !$this->showMobileMenu;
    }
}; ?>

<header class="bg-white shadow-sm sticky top-0 z-50">
    <!-- Top Bar -->
    <div class="bg-gray-900 text-white py-2">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-4">
                    <!-- Language Switcher -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-1 hover:text-gray-300">
                            <span>{{ app()->getLocale() === 'ar' ? 'العربية' : 'English' }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute top-full {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-1 bg-white text-gray-900 rounded shadow-lg py-1 w-32">
                            @foreach($languages as $language)
                            <button wire:click="changeLanguage('{{ $language->code }}')" class="block w-full text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} px-4 py-2 hover:bg-gray-100">
                                {{ $language->native_name }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Currency Switcher -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-1 hover:text-gray-300">
                            @php
                            $currentCurrency = app('currency');
                            @endphp
                            <span>{{ $currentCurrency->code }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute top-full {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-1 bg-white text-gray-900 rounded shadow-lg py-1 w-32">
                            @foreach($currencies as $currency)
                            <button wire:click="changeCurrency({{ $currency->id }})" class="block w-full text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} px-4 py-2 hover:bg-gray-100">
                                {{ $currency->symbol }} {{ $currency->code }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <a href="/contact" class="hover:text-gray-300">{{ __('Contact') }}</a>
                    <a href="/faq" class="hover:text-gray-300">{{ __('FAQ') }}</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <!-- Logo -->
            <a href="/" wire:navigate class="text-2xl font-bold text-gray-900">
                {{ config('app.name') }}
            </a>

            <!-- Search Bar -->
            <div class="hidden md:block flex-1 max-w-xl mx-8">
                <div class="relative">
                    <form wire:submit="search">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="{{ __('Search products...') }}"
                            class="w-full px-4 py-2 {{ app()->getLocale() === 'ar' ? 'pr-10 pl-4' : 'pl-4 pr-10' }} border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                        <button type="submit" class="absolute {{ app()->getLocale() === 'ar' ? 'left-2' : 'right-2' }} top-1/2 transform -translate-y-1/2 text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                    </form>

                    <!-- Search Results Dropdown -->
                    @if($showSearchResults && count($searchResults) > 0)
                    <div class="absolute top-full {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }} w-full mt-1 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        @foreach($searchResults as $product)
                        <a href="/products/{{ $product->slug }}" wire:navigate class="flex items-center p-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                            @if($product->primary_image_url)
                            <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" class="w-12 h-12 object-cover rounded">
                            @endif
                            <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }}">
                                <p class="font-medium text-gray-900">{{ $product->name }}</p>
                                <p class="text-sm text-gray-600">{{ $product->formatted_price }}</p>
                            </div>
                        </a>
                        @endforeach
                        <a href="/search?q={{ urlencode($searchQuery) }}" wire:navigate class="block p-3 text-center text-blue-600 hover:bg-gray-50">
                            {{ __('View all results') }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center gap-4">
                <!-- Account -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center gap-1 text-gray-700 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="hidden md:inline">{{ __('Account') }}</span>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute top-full {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 bg-white rounded-lg shadow-lg py-2 w-48">
                        @auth
                        <a href="/account" wire:navigate class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('My Account') }}</a>
                        <a href="/account/orders" wire:navigate class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('My Orders') }}</a>
                        <a href="/account/wishlist" wire:navigate class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Wishlist') }}</a>
                        <hr class="my-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} px-4 py-2 text-gray-700 hover:bg-gray-100">
                                {{ __('Logout') }}
                            </button>
                        </form>
                        @else
                        <a href="/login" wire:navigate class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Login') }}</a>
                        <a href="/register" wire:navigate class="block px-4 py-2 text-gray-700 hover:bg-gray-100">{{ __('Register') }}</a>
                        @endauth
                    </div>
                </div>

                <!-- Wishlist -->
                <a href="/wishlist" wire:navigate class="relative text-gray-700 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                    @if($wishlistCount > 0)
                    <span class="absolute -top-2 {{ app()->getLocale() === 'ar' ? '-left-2' : '-right-2' }} bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ $wishlistCount }}
                    </span>
                    @endif
                </a>

                <!-- Cart -->
                <a href="/cart" wire:navigate class="relative text-gray-700 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    @if($cartItemsCount > 0)
                    <span class="absolute -top-2 {{ app()->getLocale() === 'ar' ? '-left-2' : '-right-2' }} bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ $cartItemsCount }}
                    </span>
                    @endif
                </a>

                <!-- Mobile Menu Toggle -->
                <button wire:click="toggleMobileMenu" class="md:hidden text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Search -->
        <div class="md:hidden mt-4">
            <form wire:submit="search">
                <input
                    type="text"
                    wire:model="searchQuery"
                    placeholder="{{ __('Search products...') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </form>
        </div>
    </div>

    <!-- Categories Navigation -->
    <nav class="bg-gray-100 border-t border-gray-200 hidden md:block">
        <div class="container mx-auto px-4">
            <ul class="flex items-center space-x-8 {{ app()->getLocale() === 'ar' ? 'space-x-reverse' : '' }}">
                @foreach($categories as $category)
                <li class="relative group">
                    <a href="/categories/{{ $category->slug }}" wire:navigate class="block py-3 text-gray-700 hover:text-blue-600 font-medium">
                        {{ $category->name }}
                    </a>
                    @if($category->children->count() > 0)
                    <div class="absolute top-full {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }} bg-white shadow-lg rounded-lg py-2 w-48 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                        @foreach($category->children as $child)
                        <a href="/categories/{{ $child->slug }}" wire:navigate class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                            {{ $child->name }}
                        </a>
                        @endforeach
                    </div>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
    </nav>

    <!-- Mobile Menu -->
    @if($showMobileMenu)
    <div class="md:hidden bg-white border-t border-gray-200">
        <div class="container mx-auto px-4 py-4">
            <ul class="space-y-2">
                @foreach($categories as $category)
                <li>
                    <a href="/categories/{{ $category->slug }}" wire:navigate class="block py-2 text-gray-700 hover:text-blue-600">
                        {{ $category->name }}
                    </a>
                    @if($category->children->count() > 0)
                    <ul class="{{ app()->getLocale() === 'ar' ? 'pr-4' : 'pl-4' }} mt-2 space-y-1">
                        @foreach($category->children as $child)
                        <li>
                            <a href="/categories/{{ $child->slug }}" wire:navigate class="block py-1 text-sm text-gray-600 hover:text-blue-600">
                                {{ $child->name }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif
</header>