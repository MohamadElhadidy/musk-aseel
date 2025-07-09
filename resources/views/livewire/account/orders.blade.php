<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Order;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $sortBy = 'latest';

    public function mount()
    {
        if (!auth()->check()) {
            $this->redirect('/login', navigate: true);
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function with()
    {
        $query = auth()->user()->orders()
            ->with(['items', 'payment']);

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhereHas('items', function ($iq) {
                      $iq->whereJsonContains('product_details->name', $this->search);
                  });
            });
        }

        // Status filter
        if ($this->status) {
            $query->where('status', $this->status);
        }

        // Sorting
        switch ($this->sortBy) {
            case 'oldest':
                $query->oldest();
                break;
            case 'highest':
                $query->orderBy('total', 'desc');
                break;
            case 'lowest':
                $query->orderBy('total', 'asc');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        return [
            'orders' => $query->paginate(10),
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6">
                <!-- User Info -->
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-semibold text-blue-600">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <h3 class="font-semibold text-gray-900">{{ auth()->user()->name }}</h3>
                    <p class="text-sm text-gray-600">{{ auth()->user()->email }}</p>
                </div>

                <!-- Navigation -->
                <nav class="space-y-1">
                    <a href="/account" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        {{ __('Dashboard') }}
                    </a>

                    <a href="/account/orders" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        {{ __('Orders') }}
                    </a>

                    <a href="/account/addresses" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        {{ __('Addresses') }}
                    </a>

                    <a href="/wishlist" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        {{ __('Wishlist') }}
                    </a>

                    <a href="/account/profile" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        {{ __('Profile Settings') }}
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('Order History') }}</h1>
                </div>

                <!-- Filters -->
                <div class="p-6 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Search -->
                        <div>
                            <input 
                                type="text" 
                                wire:model.live.debounce.300ms="search"
                                placeholder="{{ __('Search orders...') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                            >
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <select 
                                wire:model.live="status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                            >
                                <option value="">{{ __('All Statuses') }}</option>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="processing">{{ __('Processing') }}</option>
                                <option value="shipped">{{ __('Shipped') }}</option>
                                <option value="delivered">{{ __('Delivered') }}</option>
                                <option value="cancelled">{{ __('Cancelled') }}</option>
                                <option value="refunded">{{ __('Refunded') }}</option>
                            </select>
                        </div>

                        <!-- Sort -->
                        <div>
                            <select 
                                wire:model.live="sortBy"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                            >
                                <option value="latest">{{ __('Latest First') }}</option>
                                <option value="oldest">{{ __('Oldest First') }}</option>
                                <option value="highest">{{ __('Highest Amount') }}</option>
                                <option value="lowest">{{ __('Lowest Amount') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Orders List -->
                @if($orders->count() > 0)
                    <div class="divide-y divide-gray-200">
                        @foreach($orders as $order)
                            <div class="p-6 hover:bg-gray-50">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                    <div class="mb-4 md:mb-0">
                                        <div class="flex items-center mb-2">
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                {{ __('Order') }} #{{ $order->order_number }}
                                            </h3>
                                            <span class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800">
                                                {{ $order->status_label }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600">
                                            {{ __('Placed on') }} {{ $order->created_at->format('M d, Y \a\t h:i A') }}
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            {{ __(':count items', ['count' => $order->items->count()]) }} • 
                                            {{ __('Total:') }} <span class="font-semibold">{{ $order->formatted_total }}</span>
                                        </p>
                                    </div>
                                    
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <a 
                                            href="/account/orders/{{ $order->order_number }}" 
                                            wire:navigate
                                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            {{ __('View Details') }}
                                        </a>
                                        
                                        @if($order->status === 'delivered')
                                            <a 
                                                href="/account/orders/{{ $order->id }}/invoice" 
                                                wire:navigate
                                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            >
                                                {{ __('Download Invoice') }}
                                            </a>
                                        @endif
                                        
                                        @if($order->canBeCancelled())
                                            <button 
                                                wire:click="cancelOrder({{ $order->id }})"
                                                wire:confirm="{{ __('Are you sure you want to cancel this order?') }}"
                                                class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                            >
                                                {{ __('Cancel Order') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Order Items Preview -->
                                <div class="mt-4 flex -space-x-2 overflow-hidden">
                                    @foreach($order->items->take(4) as $item)
                                        @if($item->product->primary_image_url)
                                            <img 
                                                src="{{ $item->product->primary_image_url }}" 
                                                alt="{{ $item->product_details['name'] }}"
                                                class="inline-block h-12 w-12 rounded-full ring-2 ring-white object-cover"
                                            >
                                        @endif
                                    @endforeach
                                    @if($order->items->count() > 4)
                                        <div class="inline-flex items-center justify-center h-12 w-12 rounded-full ring-2 ring-white bg-gray-200 text-sm font-medium text-gray-600">
                                            +{{ $order->items->count() - 4 }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="p-6 border-t border-gray-200">
                        {{ $orders->links() }}
                    </div>
                @else
                    <div class="p-12 text-center">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No orders found') }}</h3>
                        <p class="text-gray-500 mb-6">
                            @if($search || $status)
                                {{ __('Try adjusting your filters') }}
                            @else
                                {{ __('You haven\'t placed any orders yet') }}
                            @endif
                        </p>
                        <a 
                            href="/categories" 
                            wire:navigate
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            {{ __('Start Shopping') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>