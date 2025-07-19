<?php

use Livewire\Volt\Component;

new class extends Component
{
    public $menuItems = [];
    public $currentPath = '';
    public $expandedMenus = [];
    public $sidebarCollapsed = true;

    public function mount()
    {
        $this->currentPath = request()->path();
        $this->loadMenuItems();
        $this->sidebarCollapsed = session('sidebar_collapsed', false);
    }

    public function loadMenuItems()
    {
        $this->menuItems = [
            [
                'title' => __('Dashboard'),
                'icon' => '<path d="M13 21V11h8v10h-8zM3 13V3h8v10H3zm6-2V5H5v6h4zM3 21v-6h8v6H3zm2-2h4v-2H5v2zm10 0h4v-6h-4v6zM13 3h8v6h-8V3zm2 2v2h4V5h-4z"/>',
                'route' => '/admin',
                'active' => $this->currentPath === 'admin',
                'gradient' => 'from-blue-500 to-indigo-600'
            ],
            [
                'title' => __('E-Commerce'),
                'icon' => '<path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0020 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>',
                'gradient' => 'from-purple-500 to-pink-600',
                'submenu' => [
                    [
                        'section' => __('Products'),
                        'items' => [
                            ['title' => __('All Products'), 'route' => '/admin/products', 'icon' => 'grid'],
                            ['title' => __('Add Product'), 'route' => '/admin/products/create', 'icon' => 'plus'],
                            ['title' => __('Categories'), 'route' => '/admin/categories', 'icon' => 'folder'],
                            ['title' => __('Brands'), 'route' => '/admin/brands', 'icon' => 'tag'],
                            ['title' => __('Tags'), 'route' => '/admin/tags', 'icon' => 'hash'],
                            ['title' => __('Reviews'), 'route' => '/admin/reviews', 'icon' => 'star'],
                        ]
                    ],
                    [
                        'section' => __('Orders'),
                        'items' => [
                            ['title' => __('All Orders'), 'route' => '/admin/orders', 'icon' => 'shopping-bag'],
                            ['title' => __('Pending'), 'route' => '/admin/orders?status=pending', 'icon' => 'clock', 'badge' => \App\Models\Order::where('status', 'pending')->count()],
                            ['title' => __('Processing'), 'route' => '/admin/orders?status=processing', 'icon' => 'refresh'],
                            ['title' => __('Completed'), 'route' => '/admin/orders?status=completed', 'icon' => 'check-circle'],
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Customers'),
                'icon' => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
                'route' => '/admin/customers',
                'active' => str_starts_with($this->currentPath, 'admin/customers'),
                'gradient' => 'from-green-500 to-teal-600'
            ],
            [
                'title' => __('Marketing'),
                'icon' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
                'gradient' => 'from-yellow-500 to-orange-600',
                'submenu' => [
                    [
                        'section' => __('Campaigns'),
                        'items' => [
                            ['title' => __('Coupons'), 'route' => '/admin/coupons', 'icon' => 'gift'],
                            ['title' => __('Email Campaigns'), 'route' => '/admin/email-campaigns', 'icon' => 'mail'],
                            ['title' => __('Newsletter'), 'route' => '/admin/newsletter', 'icon' => 'send'],
                        ]
                    ],
                    [
                        'section' => __('Content'),
                        'items' => [
                            ['title' => __('Sliders'), 'route' => '/admin/sliders', 'icon' => 'image'],
                            ['title' => __('Banners'), 'route' => '/admin/banners', 'icon' => 'layout'],
                            ['title' => __('Landing Pages'), 'route' => '/admin/landing-pages', 'icon' => 'layers'],
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Analytics'),
                'icon' => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
                'route' => '/admin/analytics',
                'gradient' => 'from-red-500 to-pink-600',
                'submenu' => [
                    [
                        'section' => __('Reports'),
                        'items' => [
                            ['title' => __('Sales Report'), 'route' => '/admin/reports/sales', 'icon' => 'trending-up'],
                            ['title' => __('Product Performance'), 'route' => '/admin/reports/products', 'icon' => 'bar-chart'],
                            ['title' => __('Customer Insights'), 'route' => '/admin/reports/customers', 'icon' => 'pie-chart'],
                            ['title' => __('Traffic Analytics'), 'route' => '/admin/reports/traffic', 'icon' => 'activity'],
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Localization'),
                'icon' => '<path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>',
                'gradient' => 'from-indigo-500 to-purple-600',
                'badge' => 'NEW',
                'submenu' => [
                    [
                        'section' => __('Regional Settings'),
                        'items' => [
                            ['title' => __('Countries'), 'route' => '/admin/countries', 'icon' => 'globe'],
                            ['title' => __('Cities'), 'route' => '/admin/cities', 'icon' => 'map-pin'],
                            ['title' => __('Currencies'), 'route' => '/admin/currencies', 'icon' => 'dollar-sign'],
                            ['title' => __('Languages'), 'route' => '/admin/languages', 'icon' => 'message-circle'],
                            ['title' => __('Translations'), 'route' => '/admin/translations', 'icon' => 'type'],
                        ]
                    ],
                    [
                        'section' => __('Shipping'),
                        'items' => [
                            ['title' => __('Shipping Zones'), 'route' => '/admin/shipping-zones', 'icon' => 'truck'],
                            ['title' => __('Shipping Methods'), 'route' => '/admin/shipping-methods', 'icon' => 'package'],
                            ['title' => __('Tax Rules'), 'route' => '/admin/tax-rules', 'icon' => 'percent'],
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Content'),
                'icon' => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
                'gradient' => 'from-cyan-500 to-blue-600',
                'submenu' => [
                    [
                        'section' => __('Pages'),
                        'items' => [
                            ['title' => __('All Pages'), 'route' => '/admin/pages', 'icon' => 'file-text'],
                            ['title' => __('Add Page'), 'route' => '/admin/pages/create', 'icon' => 'file-plus'],
                            ['title' => __('Blog Posts'), 'route' => '/admin/blog', 'icon' => 'edit'],
                            ['title' => __('FAQs'), 'route' => '/admin/faqs', 'icon' => 'help-circle'],
                        ]
                    ]
                ]
            ],
            [
                'title' => __('Settings'),
                'icon' => '<path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>',
                'route' => '/admin/settings',
                'gradient' => 'from-gray-600 to-gray-800',
                'submenu' => [
                    [
                        'section' => __('General'),
                        'items' => [
                            ['title' => __('Site Settings'), 'route' => '/admin/settings', 'icon' => 'settings'],
                            ['title' => __('Payment Methods'), 'route' => '/admin/payment-methods', 'icon' => 'credit-card'],
                            ['title' => __('Email Settings'), 'route' => '/admin/email-settings', 'icon' => 'mail'],
                            ['title' => __('Social Media'), 'route' => '/admin/social-media', 'icon' => 'share-2'],
                        ]
                    ],
                    [
                        'section' => __('Advanced'),
                        'items' => [
                            ['title' => __('API Keys'), 'route' => '/admin/api-keys', 'icon' => 'key'],
                            ['title' => __('Webhooks'), 'route' => '/admin/webhooks', 'icon' => 'link'],
                            ['title' => __('Backup'), 'route' => '/admin/backup', 'icon' => 'database'],
                            ['title' => __('Logs'), 'route' => '/admin/logs', 'icon' => 'file-text'],
                        ]
                    ]
                ]
            ],
        ];
    }

    public function toggleMenu($index)
    {
        if (in_array($index, $this->expandedMenus)) {
            $this->expandedMenus = array_diff($this->expandedMenus, [$index]);
        } else {
            $this->expandedMenus[] = $index;
        }
    }

    public function toggleSidebar()
    {
        $this->sidebarCollapsed = !$this->sidebarCollapsed;
        session(['sidebar_collapsed' => $this->sidebarCollapsed]);
    }

    public function isActive($item)
    {
        if (isset($item['submenu'])) {
            foreach ($item['submenu'] as $section) {
                foreach ($section['items'] as $subitem) {
                    if ($this->currentPath === ltrim($subitem['route'], '/')) {
                        return true;
                    }
                }
            }
        }
        return $item['active'] ?? false;
    }

    public function isMenuExpanded($index)
    {
        return in_array($index, $this->expandedMenus) || $this->isActive($this->menuItems[$index]);
    }
}; ?>

<aside
    class="fixed  fixed bottom-0  border-r h-screen  z-30 inset-y-0 left-0 z-50 flex flex-col bg-gray-900 transition-all duration-300 shadow-2xl "
    
    @mouseenter="hovering = true"
    @mouseleave="hovering = false" x-cloak>


    <!-- :class="{ 
        'w-64': !@js($sidebarCollapsed), 
        'w-20': @js($sidebarCollapsed),
        '-translate-x-full lg:translate-x-0': !sidebarOpen,
        'translate-x-0': sidebarOpen
    }"
    x-data="{ 
        sidebarOpen: window.innerWidth >= 1024,
        hovering: false 
    }" -->
    <!-- Logo Section -->
    <div class="flex items-center justify-between h-16 px-4 bg-gray-800 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                </div>
                <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-gray-800"></div>
            </div>
            @if(!$sidebarCollapsed)
            <div>
                <h2 class="text-lg font-bold text-white">{{ config('app.name') }}</h2>
                <p class="text-xs text-gray-400">{{ __('Admin Panel') }}</p>
            </div>
            @endif
        </div>
        <!-- <button
            wire:click="toggleSidebar;$store.sidebar.open = !$store.sidebar.open;"
            class="lg:block hidden p-1 rounded-lg hover:bg-gray-700 transition-colors">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @if($sidebarCollapsed)
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                @else
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                @endif
            </svg>
        </button> -->
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto custom-scrollbar ">
        @foreach($menuItems as $index => $item)
        <div x-data="{ localOpen: @js($this->isMenuExpanded($index)) }" x-cloak>
            @if(isset($item['submenu']))
            <!-- Parent Menu Item -->
            <button
                @click="localOpen = !localOpen"
                class="w-full group relative flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 
                               {{ $this->isActive($item) 
                                  ? 'bg-gradient-to-r ' . $item['gradient'] . ' text-white shadow-lg' 
                                  : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                <!-- Icon with gradient background -->
                <div class="flex items-center justify-center w-10 h-10 rounded-lg 
                                    {{ $this->isActive($item) 
                                       ? 'bg-white/20' 
                                       : 'bg-gradient-to-br ' . $item['gradient'] . ' opacity-80 group-hover:opacity-100' }}">
                    <svg class="w-5 h-5 {{ $this->isActive($item) ? 'text-white' : 'text-white' }}"
                        fill="currentColor" viewBox="0 0 24 24">
                        {!! $item['icon'] !!}
                    </svg>
                </div>

                @if(!$sidebarCollapsed)
                <span class="ml-3 flex-1 text-left">{{ $item['title'] }}</span>

                @if(isset($item['badge']))
                <span class="ml-2 px-2 py-1 text-xs font-bold bg-red-500 text-white rounded-full animate-pulse">
                    {{ $item['badge'] }}
                </span>
                @endif

                <!-- Dropdown Arrow -->
                <svg class="w-4 h-4 ml-2 transition-transform duration-200"
                    :class="{ 'rotate-180': localOpen }"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                @endif
            </button>

            <!-- Submenu -->
            @if(!$sidebarCollapsed)
            <div x-show="localOpen"
                x-collapse
                class="mt-1 space-y-1">
                @foreach($item['submenu'] as $section)
                <div class="ml-12 mb-2">
                    <p class="px-3 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        {{ $section['section'] }}
                    </p>
                    @foreach($section['items'] as $subitem)
                    <a href="{{ $subitem['route'] }}"
                        wire:navigate
                        class="group flex items-center px-3 py-2 text-sm rounded-lg transition-all duration-200
                                              {{ $this->currentPath === ltrim($subitem['route'], '/') 
                                                 ? 'bg-gray-800 text-white' 
                                                 : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        <span class="mr-3 text-gray-500 group-hover:text-gray-300">
                            @if($subitem['icon'] === 'grid')
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            @elseif($subitem['icon'] === 'plus')
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            @endif
                        </span>
                        {{ $subitem['title'] }}
                        @if(isset($subitem['badge']) && $subitem['badge'] > 0)
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-0.5 animate-pulse">
                            {{ $subitem['badge'] }}
                        </span>
                        @endif
                    </a>
                    @endforeach
                </div>
                @endforeach
            </div>
            @endif
            @else
            <!-- Single Menu Item -->
            <a href="{{ $item['route'] }}"
                wire:navigate
                class="group relative flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 
                              {{ $item['active'] 
                                 ? 'bg-gradient-to-r ' . $item['gradient'] . ' text-white shadow-lg' 
                                 : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                <!-- Icon with gradient background -->
                <div class="flex items-center justify-center w-10 h-10 rounded-lg 
                                    {{ $item['active'] 
                                       ? 'bg-white/20' 
                                       : 'bg-gradient-to-br ' . $item['gradient'] . ' opacity-80 group-hover:opacity-100' }}">
                    <svg class="w-5 h-5 {{ $item['active'] ? 'text-white' : 'text-white' }}"
                        fill="currentColor" viewBox="0 0 24 24">
                        {!! $item['icon'] !!}
                    </svg>
                </div>

                @if(!$sidebarCollapsed)
                <span class="ml-3">{{ $item['title'] }}</span>

                @if(isset($item['badge']))
                <span class="ml-auto px-2 py-1 text-xs font-bold bg-red-500 text-white rounded-full animate-pulse">
                    {{ $item['badge'] }}
                </span>
                @endif
                @endif
            </a>
            @endif
        </div>
        @endforeach
    </nav>

    <!-- User Section -->
    <div class="p-4 border-t border-gray-700">
        <div class="flex items-center space-x-3">
            <div class="relative flex-shrink-0">
                <img class="w-10 h-10 rounded-xl object-cover"
                    src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&background=5B21B6&color=fff"
                    alt="{{ auth()->user()->name }}">
                <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-gray-900"></div>
            </div>
            @if(!$sidebarCollapsed)
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                <p class="text-xs text-gray-400 truncate">{{ __('Administrator') }}</p>
            </div>
            <button class="p-1 rounded-lg hover:bg-gray-800 transition-colors group">
                <svg class="w-5 h-5 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </button>
            @endif
        </div>
    </div>
</aside>