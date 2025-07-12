<?php

use Livewire\Volt\Component;

new class extends Component
{
    public $menuItems = [];
    public $currentPath = '';

    public function mount()
    {
        $this->currentPath = request()->path();
        
        $this->menuItems = [
            [
                'title' => __('Dashboard'),
                'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                'route' => '/admin',
                'active' => $this->currentPath === 'admin'
            ],
            [
                'title' => __('Products'),
                'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                'route' => '/admin/products',
                'active' => str_starts_with($this->currentPath, 'admin/products'),
                'submenu' => [
                    ['title' => __('All Products'), 'route' => '/admin/products'],
                    ['title' => __('Add Product'), 'route' => '/admin/products/create'],
                    ['title' => __('Categories'), 'route' => '/admin/categories'],
                    ['title' => __('Brands'), 'route' => '/admin/brands'],
                    ['title' => __('Tags'), 'route' => '/admin/tags'],
                ]
            ],
            [
                'title' => __('Orders'),
                'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
                'route' => '/admin/orders',
                'active' => str_starts_with($this->currentPath, 'admin/orders'),
                'badge' => \App\Models\Order::where('status', 'pending')->count()
            ],
            [
                'title' => __('Customers'),
                'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                'route' => '/admin/customers',
                'active' => str_starts_with($this->currentPath, 'admin/customers')
            ],
            [
                'title' => __('Reviews'),
                'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                'route' => '/admin/reviews',
                'active' => str_starts_with($this->currentPath, 'admin/reviews')
            ],
            [
                'title' => __('Marketing'),
                'icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
                'route' => '/admin/marketing',
                'active' => str_starts_with($this->currentPath, 'admin/marketing'),
                'submenu' => [
                    ['title' => __('Coupons'), 'route' => '/admin/coupons'],
                    ['title' => __('Sliders'), 'route' => '/admin/sliders'],
                    ['title' => __('Banners'), 'route' => '/admin/banners'],
                    ['title' => __('Newsletter'), 'route' => '/admin/newsletter'],
                ]
            ],
            [
                'title' => __('Content'),
                'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'route' => '/admin/pages',
                'active' => str_starts_with($this->currentPath, 'admin/pages') || str_starts_with($this->currentPath, 'admin/faqs'),
                'submenu' => [
                    ['title' => __('Pages'), 'route' => '/admin/pages'],
                    ['title' => __('FAQs'), 'route' => '/admin/faqs'],
                ]
            ],
            [
                'title' => __('Settings'),
                'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                'route' => '/admin/settings',
                'active' => str_starts_with($this->currentPath, 'admin/settings'),
                'submenu' => [
                    ['title' => __('General'), 'route' => '/admin/settings'],
                    ['title' => __('Shipping'), 'route' => '/admin/shipping'],
                    ['title' => __('Translations'), 'route' => '/admin/translations'],
                ]
            ],
            [
                'title' => __('Reports'),
                'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'route' => '/admin/reports',
                'active' => str_starts_with($this->currentPath, 'admin/reports')
            ],
        ];
    }

    public function isActive($item)
    {
        if (isset($item['submenu'])) {
            foreach ($item['submenu'] as $subitem) {
                if ($this->currentPath === ltrim($subitem['route'], '/')) {
                    return true;
                }
            }
        }
        return $item['active'];
    }
}; ?>

<aside 
    class="w-64 bg-gray-900 text-white flex flex-col transition-all duration-300"
    :class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }"
    x-show="sidebarOpen || window.innerWidth >= 1024"
    x-data="{ sidebarOpen: true }"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    @click.away="if (window.innerWidth < 1024) sidebarOpen = false"
>
    <!-- Logo -->
    <div class="p-4 border-b border-gray-800">
        <h2 class="text-xl font-bold">{{ __('Admin Panel') }}</h2>
        <p class="text-sm text-gray-400">{{ config('app.name') }}</p>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4">
        @foreach($menuItems as $item)
            <div x-data="{ open: {{ isset($item['submenu']) && $this->isActive($item) ? 'true' : 'false' }} }">
                @if(isset($item['submenu']))
                    <button 
                        @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-800 transition {{ $this->isActive($item) ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
                    >
                        <div class="flex items-center">
                            <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                            </svg>
                            <span>{{ $item['title'] }}</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="bg-gray-800">
                        @foreach($item['submenu'] as $subitem)
                            <a 
                                href="{{ $subitem['route'] }}" 
                                wire:navigate
                                class="block px-12 py-2 text-sm {{ $this->currentPath === ltrim($subitem['route'], '/') ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white' }} hover:bg-gray-700 transition"
                            >
                                {{ $subitem['title'] }}
                            </a>
                        @endforeach
                    </div>
                @else
                    <a 
                        href="{{ $item['route'] }}" 
                        wire:navigate
                        class="flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-800 transition {{ $item['active'] ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
                    >
                        <div class="flex items-center">
                            <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                            </svg>
                            <span>{{ $item['title'] }}</span>
                        </div>
                        @if(isset($item['badge']) && $item['badge'] > 0)
                            <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endif
            </div>
        @endforeach
    </nav>

    <!-- User Info -->
    <div class="p-4 border-t border-gray-800">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center">
                <span class="text-sm font-semibold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            </div>
            <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }}">
                <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                <p class="text-xs text-gray-400">{{ __('Administrator') }}</p>
            </div>
        </div>
    </div>
</aside>