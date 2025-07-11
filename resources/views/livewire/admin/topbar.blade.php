<?php

use Livewire\Volt\Component;
use App\Models\Language;
use App\Models\Order;
use App\Models\Contact;

new class extends Component
{
    public $languages;
    public $pendingOrders = 0;
    public $unreadContacts = 0;

    public function mount()
    {
        $this->languages = Language::active()->get();
        $this->pendingOrders = Order::where('status', 'pending')->count();
        $this->unreadContacts = Contact::where('status', 'new')->count();
    }

    public function changeLanguage($locale)
    {
        session(['locale' => $locale]);
        $this->redirect(request()->url(), navigate: true);
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
        <div class="flex-1 max-w-xl {{ app()->getLocale() === 'ar' ? 'ml-4' : 'mr-4' }}">
            <div class="relative">
                <input 
                    type="text" 
                    placeholder="{{ __('Search...') }}"
                    class="w-full px-4 py-2 {{ app()->getLocale() === 'ar' ? 'pr-10' : 'pl-10' }} border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
                <svg class="absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
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