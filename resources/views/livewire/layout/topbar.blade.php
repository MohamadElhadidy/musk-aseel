<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\User;

new class extends Component
{
    public int $pendingOrdersCount = 0;
    public int $newCustomersToday = 0;
    public bool $showNotifications = false;
    public bool $showProfile = false;

    public function mount()
    {
        $this->loadNotificationCounts();
    }

    public function loadNotificationCounts()
    {
        $this->pendingOrdersCount = Order::where('status', 'pending')->count();
        $this->newCustomersToday = User::whereDate('created_at', today())->count();
    }

    public function logout()
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        
        $this->redirect('/login', navigate: true);
    }
}; ?>

<header class="bg-white shadow-sm border-b border-gray-200">
    <div class="flex items-center justify-between px-6 py-4">
        <!-- Left side -->
        <div class="flex items-center">
            <button 
                @click="$dispatch('toggle-sidebar')"
                class="text-gray-500 hover:text-gray-700 lg:hidden"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            
            <h2 class="text-xl font-semibold text-gray-800 {{ app()->getLocale() === 'ar' ? 'mr-4' : 'ml-4' }}">
                {{ __('Admin Dashboard') }}
            </h2>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-4">
            <!-- Quick Stats -->
            <div class="hidden md:flex items-center gap-6 text-sm">
                <a href="/admin/orders?status=pending" wire:navigate class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                    <span class="flex items-center justify-center w-6 h-6 bg-yellow-100 text-yellow-600 rounded-full text-xs font-semibold">
                        {{ $pendingOrdersCount }}
                    </span>
                    <span>{{ __('Pending Orders') }}</span>
                </a>
                
                <div class="flex items-center gap-2 text-gray-600">
                    <span class="flex items-center justify-center w-6 h-6 bg-green-100 text-green-600 rounded-full text-xs font-semibold">
                        {{ $newCustomersToday }}
                    </span>
                    <span>{{ __('New Customers Today') }}</span>
                </div>
            </div>

            <!-- Notifications -->
            <div class="relative" x-data="{ open: false }">
                <button 
                    @click="open = !open"
                    class="relative text-gray-500 hover:text-gray-700"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    @if($pendingOrdersCount > 0)
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full"></span>
                    @endif
                </button>

                <div 
                    x-show="open" 
                    @click.away="open = false"
                    x-transition
                    class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                >
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="font-semibold">{{ __('Notifications') }}</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        @if($pendingOrdersCount > 0)
                            <a href="/admin/orders?status=pending" wire:navigate class="block p-4 hover:bg-gray-50 border-b border-gray-100">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }}">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ __(':count pending orders', ['count' => $pendingOrdersCount]) }}
                                        </p>
                                        <p class="text-xs text-gray-500">{{ __('Click to view') }}</p>
                                    </div>
                                </div>
                            </a>
                        @endif

                        @if($newCustomersToday > 0)
                            <a href="/admin/customers?filter=today" wire:navigate class="block p-4 hover:bg-gray-50">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }}">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ __(':count new customers today', ['count' => $newCustomersToday]) }}
                                        </p>
                                        <p class="text-xs text-gray-500">{{ __('Welcome them!') }}</p>
                                    </div>
                                </div>
                            </a>
                        @endif

                        @if($pendingOrdersCount === 0 && $newCustomersToday === 0)
                            <div class="p-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="text-sm">{{ __('No new notifications') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <button 
                    @click="open = !open"
                    class="flex items-center gap-2 text-gray-700 hover:text-gray-900"
                >
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <span class="hidden md:block">{{ auth()->user()->name }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div 
                    x-show="open" 
                    @click.away="open = false"
                    x-transition
                    class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                >
                    <div class="p-2">
                        <a href="/admin/profile" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                            {{ __('Profile') }}
                        </a>
                        <a href="/admin/settings" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                            {{ __('Settings') }}
                        </a>
                        <hr class="my-2">
                        <a href="/" target="_blank" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded">
                            {{ __('View Store') }}
                        </a>
                        <hr class="my-2">
                        <button 
                            wire:click="logout"
                            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded"
                        >
                            {{ __('Logout') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>