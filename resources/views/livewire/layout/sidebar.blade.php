<?php

use Livewire\Volt\Component;

new class extends Component
{
    public bool $sidebarOpen = true;
    public string $activeMenu = '';

    public function mount()
    {
        // Set active menu based on current route
        $currentRoute = request()->route()->getName();
        $this->activeMenu = explode('.', $currentRoute)[1] ?? 'dashboard';
    }

    public function toggleSidebar()
    {
        $this->sidebarOpen = !$this->sidebarOpen;
    }
}; ?>

<aside 
    class="bg-gray-900 text-white transition-all duration-300 {{ $sidebarOpen ? 'w-64' : 'w-16' }}"
    x-data="{ open: @entangle('sidebarOpen') }"
>
    <div class="p-4">
        <!-- Logo -->
        <div class="flex items-center justify-between mb-8">
            <a href="/admin" wire:navigate class="flex items-center">
                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <span x-show="open" class="ml-2 text-xl font-bold">{{ __('Admin') }}</span>
            </a>
            <button 
                @click="$wire.toggleSidebar()"
                class="text-gray-400 hover:text-white lg:hidden"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="space-y-1">
            <!-- Dashboard -->
            <a 
                href="/admin" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'dashboard' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Dashboard') }}</span>
            </a>

            <!-- Orders -->
            <a 
                href="/admin/orders" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'orders' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Orders') }}</span>
            </a>

            <!-- Products -->
            <a 
                href="/admin/products" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'products' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Products') }}</span>
            </a>

            <!-- Categories -->
            <a 
                href="/admin/categories" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'categories' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Categories') }}</span>
            </a>

            <!-- Customers -->
            <a 
                href="/admin/customers" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'customers' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Customers') }}</span>
            </a>

            <!-- Coupons -->
            <a 
                href="/admin/coupons" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'coupons' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Coupons') }}</span>
            </a>

            <!-- Reports -->
            <a 
                href="/admin/reports" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'reports' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Reports') }}</span>
            </a>

            <!-- Settings -->
            <a 
                href="/admin/settings" 
                wire:navigate
                class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-800 transition {{ $activeMenu === 'settings' ? 'bg-gray-800 text-white' : 'text-gray-300' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span x-show="open" class="ml-3">{{ __('Settings') }}</span>
            </a>
        </nav>
    </div>
</aside>