<?php

use Livewire\Volt\Component;
use App\Models\Language;
use App\Models\Currency;
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
    public $currencies;
    public $currentCurrency;
    public $pendingOrders = 0;
    public $unreadContacts = 0;
    public $notifications = [];

    // Search properties
    public $searchQuery = '';
    public $searchResults = [];
    public $showSearchResults = false;
    public $searchLoading = false;
    public $quickActions = [];

    // UI States
    public $showNotifications = false;
    public $showUserMenu = false;
    public $showCurrencyMenu = false;
    public $showLanguageMenu = false;
    public $darkMode = false;

    public function mount()
    {
        $this->languages = Language::where('is_active', true)->get();
        $this->currencies = Currency::where('is_active', true)->get();
        $this->currentCurrency = Currency::getDefault();
        $this->pendingOrders = Order::where('status', 'pending')->count();
        $this->unreadContacts = Contact::where('status', 'new')->count();
        $this->darkMode = session('dark_mode', false);
        $this->loadNotifications();
        $this->loadQuickActions();
    }

    public function loadNotifications()
    {
        $this->notifications = collect();

        // Recent orders
        $recentOrders = Order::where('status', 'pending')
            ->latest()
            ->take(3)
            ->get();

        foreach ($recentOrders as $order) {
            $this->notifications->push([
                'id' => 'order_' . $order->id,
                'type' => 'order',
                'title' => __('New Order #:number', ['number' => $order->order_number]),
                'message' => __(':customer placed an order worth :amount', [
                    'customer' => $order->user->name ?? __('Guest'),
                    'amount' => $order->formatted_total
                ]),
                'time' => $order->created_at->diffForHumans(),
                'icon' => 'shopping-bag',
                'color' => 'blue',
                'url' => '/admin/orders/' . $order->id
            ]);
        }

        // Low stock products
        $lowStockProducts = Product::where('track_quantity', true)
            ->where('quantity', '<', 10)
            ->take(2)
            ->get();

        foreach ($lowStockProducts as $product) {
            $this->notifications->push([
                'id' => 'stock_' . $product->id,
                'type' => 'stock',
                'title' => __('Low Stock Alert'),
                'message' => __(':product has only :quantity items left', [
                    'product' => $product->name,
                    'quantity' => $product->quantity
                ]),
                'time' => __('Now'),
                'icon' => 'alert-triangle',
                'color' => 'red',
                'url' => '/admin/products/' . $product->id . '/edit'
            ]);
        }

        // New messages
        $newMessages = Contact::where('status', 'new')
            ->latest()
            ->take(2)
            ->get();

        foreach ($newMessages as $message) {
            $this->notifications->push([
                'id' => 'message_' . $message->id,
                'type' => 'message',
                'title' => __('New Message'),
                'message' => __(':name sent a message: :preview', [
                    'name' => $message->name,
                    'preview' => str()->limit($message->message, 50)
                ]),
                'time' => $message->created_at->diffForHumans(),
                'icon' => 'mail',
                'color' => 'green',
                'url' => '/admin/contacts/' . $message->id
            ]);
        }
    }

    public function loadQuickActions()
    {
        $this->quickActions = [
            [
                'title' => __('Add Product'),
                'icon' => 'plus-circle',
                'url' => '/admin/products/create',
                'color' => 'blue'
            ],
            [
                'title' => __('New Order'),
                'icon' => 'shopping-cart',
                'url' => '/admin/orders/create',
                'color' => 'green'
            ],
            [
                'title' => __('Add Customer'),
                'icon' => 'user-plus',
                'url' => '/admin/customers/create',
                'color' => 'purple'
            ],
            [
                'title' => __('Reports'),
                'icon' => 'bar-chart-2',
                'url' => '/admin/reports',
                'color' => 'orange'
            ],
        ];
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
        $products = Product::whereHas('translations', function ($q) use ($query, $locale) {
            $q->where('locale', $locale)
                ->where(function ($subQ) use ($query) {
                    $subQ->where('name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
        })
            ->orWhere('sku', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->with(['translations' => function ($q) use ($locale) {
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
                'url' => '/admin/products/create?id=' . $product->id,
                'icon' => 'package',
                'image' => $product->primary_image_url
            ];
        }

        // Search Orders
        $orders = Order::where('order_number', 'like', "%{$query}%")
            ->orWhereHas('user', function ($q) use ($query) {
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
                'icon' => 'user',
                'image' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=6366F1&color=fff'
            ];
        }

        $this->searchResults = $results;
    }

    public function clearSearch()
    {
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->showSearchResults = false;
    }

    public function changeCurrency($currencyId)
    {
        $currency = Currency::find($currencyId);
        if ($currency) {
            session(['currency' => $currency->code]);
            $this->currentCurrency = $currency;
            $this->showCurrencyMenu = false;
            $this->dispatch('currency-changed');
        }
    }

    public function changeLanguage($locale)
    {
        Cookie::queue(Cookie::forever('locale', $locale));
        $this->showLanguageMenu = false;
        return redirect()->to(url()->previous());
    }

    public function toggleDarkMode()
    {
        $this->darkMode = !$this->darkMode;
        session(['dark_mode' => $this->darkMode]);
        $this->dispatch('dark-mode-toggled', $this->darkMode);
    }

    public function markNotificationRead($notificationId)
    {
        // Implementation for marking notification as read
    }

    public function logout()
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<header class="fixed top-0 right-0 left-2 lg:left-64 z-40 bg-white dark:bg-gray-800 shadow-sm transition-all duration-300"
    :class="{ 'lg:left-20': sidebarCollapsed }">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Mobile Menu Toggle -->
            <button
                @click="sidebarOpen = !sidebarOpen"
                class="lg:hidden p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            <!-- Search Bar -->
            <div class="flex-1 flex items-center max-w-2xl mx-4 lg:mx-8">
                <div class="w-full relative" x-data="{ focused: false }">
                    <div class="relative group">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="searchQuery"
                            @focus="focused = true"
                            @blur="setTimeout(() => focused = false, 200)"
                            placeholder="{{ __('Search anything...') }} ({{ __('Ctrl+K') }})"
                            class="w-full pl-12 pr-4 py-3 bg-gray-100 dark:bg-gray-700 border-0 rounded-xl 
                                   text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400
                                   focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-gray-600
                                   transition-all duration-200">

                        <!-- Search Icon / Loading -->
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                            @if($searchLoading)
                            <svg class="w-5 h-5 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            @else
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            @endif
                        </div>

                        <!-- Clear Button -->
                        @if($searchQuery)
                        <button
                            wire:click="clearSearch"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        @endif

                        <!-- Keyboard Shortcut -->
                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden sm:flex items-center gap-1">
                            <kbd class="px-2 py-1 text-xs font-semibold text-gray-500 bg-gray-200 dark:bg-gray-600 dark:text-gray-300 rounded">⌘K</kbd>
                        </div>
                    </div>

                    <!-- Search Results Dropdown -->
                    @if($showSearchResults && count($searchResults) > 0)
                    <div class="absolute left-0 right-0 mt-2 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 max-h-96 overflow-hidden">
                        <div class="overflow-y-auto max-h-96">
                            @foreach($searchResults as $result)
                            <a
                                href="{{ $result['url'] }}"
                                wire:navigate
                                class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                @if(isset($result['image']))
                                <img src="{{ $result['image'] }}" alt="" class="w-10 h-10 rounded-lg object-cover mr-3">
                                @else
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br 
                                                {{ $result['type'] === 'product' ? 'from-blue-500 to-blue-600' : '' }}
                                                {{ $result['type'] === 'order' ? 'from-green-500 to-green-600' : '' }}
                                                {{ $result['type'] === 'customer' ? 'from-purple-500 to-purple-600' : '' }}
                                                flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @if($result['icon'] === 'package')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        @elseif($result['icon'] === 'shopping-cart')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        @elseif($result['icon'] === 'user')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        @endif
                                    </svg>
                                </div>
                                @endif

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $result['title'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $result['subtitle'] }}</p>
                                </div>

                                <span class="ml-3 px-2 py-1 text-xs font-medium rounded-lg
                                            {{ $result['type'] === 'product' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : '' }}
                                            {{ $result['type'] === 'order' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : '' }}
                                            {{ $result['type'] === 'customer' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : '' }}">
                                    {{ ucfirst($result['type']) }}
                                </span>
                            </a>
                            @endforeach
                        </div>

                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Press Enter to see all results') }}
                            </p>
                        </div>
                    </div>
                    @elseif($showSearchResults && count($searchResults) === 0 && $searchQuery)
                    <div class="absolute left-0 right-0 mt-2 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No results found for') }} "{{ $searchQuery }}"</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Right Side Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Quick Actions -->
                <div class="hidden lg:flex items-center gap-1">
                    @foreach($quickActions as $action)
                    <a href="{{ $action['url'] }}"
                        wire:navigate
                        title="{{ $action['title'] }}"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-{{ $action['color'] }}-600 dark:text-gray-400"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($action['icon'] === 'plus-circle')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            @elseif($action['icon'] === 'shopping-cart')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            @elseif($action['icon'] === 'user-plus')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                            @elseif($action['icon'] === 'bar-chart-2')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            @endif
                        </svg>
                    </a>
                    @endforeach
                </div>

                <div class="h-8 w-px bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>

                <!-- Currency Switcher -->
                <div class="relative" x-data="{ open: @entangle('showCurrencyMenu') }">
                    <button @click="open = !open"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 hidden sm:block">
                            {{ $currentCurrency->code }}
                        </span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                        @click.away="open = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-2">
                        <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ __('Select Currency') }}
                            </p>
                        </div>
                        @foreach($currencies as $currency)
                        <button
                            wire:click="changeCurrency({{ $currency->id }})"
                            class="w-full flex items-center justify-between px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    <span class="text-sm font-bold text-gray-600 dark:text-gray-300">
                                        {{ $currency->symbol }}
                                    </span>
                                </div>
                                <div class="text-left">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $currency->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $currency->code }}
                                    </p>
                                </div>
                            </div>
                            @if($currentCurrency->id === $currency->id)
                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Language Switcher -->
                <div class="relative" x-data="{ open: @entangle('showLanguageMenu') }">
                    <button @click="open = !open"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 hidden sm:block">
                            {{ strtoupper(app()->getLocale()) }}
                        </span>
                    </button>

                    <div x-show="open"
                        @click.away="open = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-2">
                        @foreach($languages as $language)
                        <button
                            wire:click="changeLanguage('{{ $language->code }}')"
                            class="w-full flex items-center justify-between px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-6 rounded overflow-hidden">
                                    <img src="https://flagcdn.com/w40/{{ strtolower($language->code) }}.png"
                                        alt="{{ $language->native_name }}"
                                        class="w-full h-full object-cover">
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $language->native_name }}
                                </span>
                            </div>
                            @if(app()->getLocale() === $language->code)
                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Dark Mode Toggle -->
                <button wire:click="toggleDarkMode"
                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    @if($darkMode)
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    @else
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                    @endif
                </button>

                <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>

                <!-- Notifications -->
                <div class="relative" x-data="{ open: @entangle('showNotifications') }">
                    <button @click="open = !open"
                        class="relative p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        @if($notifications->count() > 0)
                        <span class="absolute -top-1 -right-1 flex items-center justify-center w-6 h-6">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75 animate-ping"></span>
                            <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 text-white text-xs items-center justify-center font-bold">
                                {{ $notifications->count() }}
                            </span>
                        </span>
                        @endif
                    </button>

                    <div x-show="open"
                        @click.away="open = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-96 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('Notifications') }}
                                </h3>
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 rounded-full">
                                    {{ $notifications->count() }} {{ __('New') }}
                                </span>
                            </div>
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            @forelse($notifications as $notification)
                            <a href="{{ $notification['url'] }}"
                                wire:navigate
                                wire:click="markNotificationRead('{{ $notification['id'] }}')"
                                class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-{{ $notification['color'] }}-100 dark:bg-{{ $notification['color'] }}-900 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-{{ $notification['color'] }}-600 dark:text-{{ $notification['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($notification['icon'] === 'shopping-bag')
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                            @elseif($notification['icon'] === 'alert-triangle')
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            @elseif($notification['icon'] === 'mail')
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            @endif
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $notification['title'] }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $notification['message'] }}
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                            {{ $notification['time'] }}
                                        </p>
                                    </div>
                                </div>
                            </a>
                            @empty
                            <div class="px-4 py-8 text-center">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('No new notifications') }}
                                </p>
                            </div>
                            @endforelse
                        </div>

                        @if($notifications->count() > 0)
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <button class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium">
                                {{ __('Mark all as read') }}
                            </button>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Store Link -->
                <a href="/" target="_blank"
                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                    title="{{ __('View Store') }}">
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                </a>

                <!-- User Menu -->
                <div class="relative" x-data="{ open: @entangle('showUserMenu') }">
                    <button @click="open = !open"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <img class="w-8 h-8 rounded-lg object-cover"
                            src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&background=6366F1&color=fff"
                            alt="{{ auth()->user()->name }}">
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ auth()->user()->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Administrator') }}
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                        @click.away="open = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
                        </div>

                        <div class="py-2">
                            <a href="/admin/profile" wire:navigate
                                class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                {{ __('My Profile') }}
                            </a>

                            <a href="/admin/settings" wire:navigate
                                class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                {{ __('Settings') }}
                            </a>

                            <a href="/admin/activity-log" wire:navigate
                                class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ __('Activity Log') }}
                            </a>
                        </div>

                        <div class="py-2 border-t border-gray-200 dark:border-gray-700">
                            <button wire:click="logout"
                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                {{ __('Logout') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (max-width: 640px) {
            header {
                left: 0 !important;
            }
        }
    </style>
</header>