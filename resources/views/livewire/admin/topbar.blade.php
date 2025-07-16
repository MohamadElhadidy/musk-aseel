<?php

use Livewire\Volt\Component;
use App\Models\Language;
use App\Models\Order;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\User;
use App\Models\Page;
use App\Models\Coupon;
use Illuminate\Support\Facades\Cookie;

new class extends Component
{
    public $languages;
    public $pendingOrders = 0;
    public $unreadContacts = 0;
    
    // Search properties
    public $searchQuery = '';
    public $searchResults = [];
    public $showSearchResults = false;
    public $searchLoading = false;

    public function mount()
    {
        $this->languages = Language::where('is_active', true)->get();
        $this->pendingOrders = Order::where('status', 'pending')->count();
        $this->unreadContacts = Contact::where('status', 'new')->count();
    }

    public function updatedSearchQuery()
    {
        if (strlen($this->searchQuery) >= 2) {
            $this->searchLoading = true;
            $this->performSearch();
            $this->showSearchResults = true;
        } else {
            $this->searchResults = [];
            $this->showSearchResults = false;
        }
        $this->searchLoading = false;
    }

    public function performSearch()
    {
        $query = $this->searchQuery;
        $locale = app()->getLocale();
        $results = [];

        // Search Products (with translations)
        $products = Product::whereHas('translations', function($q) use ($query, $locale) {
                $q->where('locale', $locale)
                  ->where(function($subQ) use ($query) {
                      $subQ->where('name', 'like', "%{$query}%")
                           ->orWhere('description', 'like', "%{$query}%");
                  });
            })
            ->orWhere('sku', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->with(['translations' => function($q) use ($locale) {
                $q->where('locale', $locale);
            }])
            ->where('is_active', true)
            ->limit(5)
            ->get();
        
        foreach ($products as $product) {
            $translation = $product->translations->first();
            $results[] = [
                'type' => 'product',
                'title' => $translation ? $translation->name : $product->slug,
                'subtitle' => 'SKU: ' . $product->sku . ' - ' . __('Price') . ': ' . number_format($product->price, 2),
                'url' => '/admin/products/create?id=' . $product->id, // Since you have products/create route
                'icon' => 'package'
            ];
        }

        // Search Categories (with translations)
        $categories = Category::whereHas('translations', function($q) use ($query, $locale) {
                $q->where('locale', $locale)
                  ->where('name', 'like', "%{$query}%");
            })
            ->orWhere('slug', 'like', "%{$query}%")
            ->with(['translations' => function($q) use ($locale) {
                $q->where('locale', $locale);
            }])
            ->where('is_active', true)
            ->limit(3)
            ->get();
        
        foreach ($categories as $category) {
            $translation = $category->translations->first();
            $results[] = [
                'type' => 'category',
                'title' => $translation ? $translation->name : $category->slug,
                'subtitle' => __('Category'),
                'url' => '/admin/categories?edit=' . $category->id,
                'icon' => 'folder'
            ];
        }

        // Search Brands (with translations)
        $brands = Brand::whereHas('translations', function($q) use ($query, $locale) {
                $q->where('locale', $locale)
                  ->where('name', 'like', "%{$query}%");
            })
            ->orWhere('slug', 'like', "%{$query}%")
            ->with(['translations' => function($q) use ($locale) {
                $q->where('locale', $locale);
            }])
            ->where('is_active', true)
            ->limit(3)
            ->get();
        
        foreach ($brands as $brand) {
            $translation = $brand->translations->first();
            $results[] = [
                'type' => 'brand',
                'title' => $translation ? $translation->name : $brand->slug,
                'subtitle' => __('Brand'),
                'url' => '/admin/brands?edit=' . $brand->id,
                'icon' => 'tag'
            ];
        }

        // Search Orders
        $orders = Order::where('order_number', 'like', "%{$query}%")
            ->orWhereHas('user', function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->with('user')
            ->limit(5)
            ->get();
        
        foreach ($orders as $order) {
            $results[] = [
                'type' => 'order',
                'title' => __('Order') . ' #' . $order->order_number,
                'subtitle' => ($order->user->name ?? __('Guest')) . ' - ' . __('Total') . ': ' . number_format($order->total, 2) . ' ' . $order->currency_code,
                'url' => '/admin/orders/' . $order->id,
                'icon' => 'shopping-cart'
            ];
        }

        // Search Users/Customers
        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->where('is_active', true)
            ->limit(5)
            ->get();
        
        foreach ($users as $user) {
            $results[] = [
                'type' => 'customer',
                'title' => $user->name,
                'subtitle' => $user->email . ($user->phone ? ' - ' . $user->phone : ''),
                'url' => '/admin/customers?user=' . $user->id,
                'icon' => 'user'
            ];
        }

        // Search Coupons
        if (class_exists(Coupon::class)) {
            $coupons = Coupon::where('code', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->where('is_active', true)
                ->limit(3)
                ->get();
            
            foreach ($coupons as $coupon) {
                $results[] = [
                    'type' => 'coupon',
                    'title' => $coupon->code,
                    'subtitle' => $coupon->type === 'percentage' 
                        ? $coupon->value . '% ' . __('Off') 
                        : __('Currency discount') . ': ' . number_format($coupon->value, 2),
                    'url' => '/admin/coupons?edit=' . $coupon->id,
                    'icon' => 'gift'
                ];
            }
        }

        // Search Pages (with translations)
        if (class_exists(Page::class)) {
            $pages = Page::whereHas('translations', function($q) use ($query, $locale) {
                    $q->where('locale', $locale)
                      ->where(function($subQ) use ($query) {
                          $subQ->where('title', 'like', "%{$query}%")
                               ->orWhere('content', 'like', "%{$query}%");
                      });
                })
                ->orWhere('slug', 'like', "%{$query}%")
                ->with(['translations' => function($q) use ($locale) {
                    $q->where('locale', $locale);
                }])
                ->where('is_active', true)
                ->limit(3)
                ->get();
            
            foreach ($pages as $page) {
                $translation = $page->translations->first();
                $results[] = [
                    'type' => 'page',
                    'title' => $translation ? $translation->title : $page->slug,
                    'subtitle' => __('Page'),
                    'url' => '/pages/' . $page->slug,
                    'icon' => 'document'
                ];
            }
        }

        $this->searchResults = $results;
    }

    public function clearSearch()
    {
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->showSearchResults = false;
    }

    public function changeLanguage($locale)
    {
        Cookie::queue(Cookie::forever('locale', $locale));
        return redirect()->to(url()->previous());
    }

    public function logout()
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        
        $this->redirect('/', navigate: true);
    }
}; ?>

<header class="bg-white shadow-sm">
    <div class="flex items-center justify-between px-6 py-4">
        <!-- Mobile Menu Toggle -->
        <button 
            @click="sidebarOpen = !sidebarOpen"
            class="lg:hidden text-gray-500 hover:text-gray-700"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>

        <!-- Search Bar -->
        <div class="flex-1 max-w-xl {{ app()->getLocale() === 'ar' ? 'ml-4' : 'mr-4' }}" x-data="{ focused: false }">
            <div class="relative">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="searchQuery"
                    @focus="focused = true"
                    @blur="setTimeout(() => focused = false, 200)"
                    placeholder="{{ __('Search products, orders, users...') }}"
                    class="w-full px-4 py-2 {{ app()->getLocale() === 'ar' ? 'pr-10' : 'pl-10' }} border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
                
                <!-- Search Icon / Loading Spinner -->
                @if($searchLoading)
                    <svg class="absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                @else
                    <svg class="absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                @endif

                <!-- Clear Button -->
                @if($searchQuery)
                    <button 
                        wire:click="clearSearch"
                        class="absolute {{ app()->getLocale() === 'ar' ? 'left-3' : 'right-3' }} top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                @endif

                <!-- Search Results Dropdown -->
                @if($showSearchResults && count($searchResults) > 0)
                    <div class="absolute {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }} mt-2 w-full bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-96 overflow-y-auto">
                        @foreach($searchResults as $result)
                            <a 
                                href="{{ $result['url'] }}"
                                wire:navigate
                                class="flex items-center px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                            >
                                <!-- Icon -->
                                <div class="flex-shrink-0 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}">
                                    @if($result['icon'] === 'package')
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                    @elseif($result['icon'] === 'folder')
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                        </svg>
                                    @elseif($result['icon'] === 'shopping-cart')
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    @elseif($result['icon'] === 'user')
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    @elseif($result['icon'] === 'tag')
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                    @elseif($result['icon'] === 'gift')
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                                        </svg>
                                    @elseif($result['icon'] === 'document')
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    @endif
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $result['title'] }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $result['subtitle'] }}</p>
                                </div>

                                <!-- Type Badge -->
                                <div class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }}">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        @if($result['type'] === 'product') bg-blue-100 text-blue-800
                                        @elseif($result['type'] === 'category') bg-green-100 text-green-800
                                        @elseif($result['type'] === 'brand') bg-purple-100 text-purple-800
                                        @elseif($result['type'] === 'order') bg-yellow-100 text-yellow-800
                                        @elseif($result['type'] === 'customer') bg-indigo-100 text-indigo-800
                                        @elseif($result['type'] === 'coupon') bg-pink-100 text-pink-800
                                        @elseif($result['type'] === 'page') bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($result['type']) }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @elseif($showSearchResults && count($searchResults) === 0 && $searchQuery)
                    <div class="absolute {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }} mt-2 w-full bg-white rounded-lg shadow-lg border border-gray-200 z-50 p-4">
                        <p class="text-sm text-gray-500 text-center">{{ __('No results found for') }} "{{ $searchQuery }}"</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Side Actions -->
        <div class="flex items-center gap-4">
            <!-- Language Switcher -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center text-gray-700 hover:text-gray-900">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                    </svg>
                    <span class="{{ app()->getLocale() === 'ar' ? 'mr-1' : 'ml-1' }}">{{ strtoupper(app()->getLocale()) }}</span>
                </button>
                <div x-show="open" @click.away="open = false" class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-48 bg-white rounded-md shadow-lg z-50">
                    @foreach($languages as $language)
                        <button 
                            wire:click="changeLanguage('{{ $language->code }}')"
                            class="block w-full text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                            {{ $language->native_name }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Quick Actions -->
            <a href="/" target="_blank" class="text-gray-500 hover:text-gray-700" title="{{ __('View Store') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
            </a>

            <!-- Notifications -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="relative text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    @if($pendingOrders > 0 || $unreadContacts > 0)
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ $pendingOrders + $unreadContacts }}
                        </span>
                    @endif
                </button>
                <div x-show="open" @click.away="open = false" class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-80 bg-white rounded-md shadow-lg z-50">
                    <div class="p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('Notifications') }}</h3>
                        @if($pendingOrders > 0)
                            <a href="/admin/orders?status=pending" wire:navigate class="block p-3 hover:bg-gray-50 rounded">
                                <p class="text-sm font-medium text-gray-900">{{ __(':count pending orders', ['count' => $pendingOrders]) }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Click to view pending orders') }}</p>
                            </a>
                        @endif
                        @if($unreadContacts > 0)
                            <a href="/admin/contacts" wire:navigate class="block p-3 hover:bg-gray-50 rounded">
                                <p class="text-sm font-medium text-gray-900">{{ __(':count new messages', ['count' => $unreadContacts]) }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Click to view messages') }}</p>
                            </a>
                        @endif
                        @if($pendingOrders === 0 && $unreadContacts === 0)
                            <p class="text-sm text-gray-500">{{ __('No new notifications') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- User Menu -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center text-gray-700 hover:text-gray-900">
                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                        <span class="text-sm font-semibold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    </div>
                </button>
                <div x-show="open" @click.away="open = false" class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-48 bg-white rounded-md shadow-lg z-50">
                    <div class="px-4 py-3 border-b">
                        <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                    </div>
                    <a href="/account/profile" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        {{ __('My Profile') }}
                    </a>
                    <button wire:click="logout" class="block w-full text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        {{ __('Logout') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>