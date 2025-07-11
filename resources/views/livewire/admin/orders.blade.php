<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Order;
use App\Models\User;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $customerId = null;
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }

        // Check for status filter from URL
        $this->status = request()->get('status', '');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updateOrderStatus($orderId, $status)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->updateStatus($status, null, auth()->id());
            
            $this->dispatch('toast', 
                type: 'success',
                message: __('Order status updated')
            );
        }
    }

    public function exportOrders()
    {
        // Export logic here
        $this->dispatch('toast', 
            type: 'info',
            message: __('Export feature coming soon')
        );
    }

    public function with()
    {
        $query = Order::with(['user', 'items', 'payment']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhereHas('user', function ($uq) {
                      $uq->where('name', 'like', "%{$this->search}%")
                         ->orWhere('email', 'like', "%{$this->search}%");
                  });
            });
        }

        // Status filter
        if ($this->status) {
            $query->where('status', $this->status);
        }

        // Date filters
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // Customer filter
        if ($this->customerId) {
            $query->where('user_id', $this->customerId);
        }

        // Sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        // Get total amounts by status for summary
        $summary = Order::select('status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(total) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'orders' => $query->paginate(20),
            'customers' => User::where('is_admin', false)->orderBy('name')->get(),
            'summary' => $summary,
            'layout' => 'components.layouts.admin',
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Orders') }}</h1>
        <button wire:click="exportOrders" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-2' : 'mr-2' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            {{ __('Export Orders') }}
        </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        @php
            $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
            $currency = \App\Models\Currency::getDefault();
        @endphp
        @foreach($statuses as $statusKey)
            @php
                $data = $summary->get($statusKey);
                $count = $data ? $data->count : 0;
                $total = $data ? $data->total : 0;
            @endphp
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">{{ ucfirst($statusKey) }}</p>
                        <p class="text-lg font-semibold">{{ $count }}</p>
                        <p class="text-xs text-gray-500">{{ $currency->format($total) }}</p>
                    </div>
                    <div class="p-2 bg-{{ \App\Models\Order::first()->status === $statusKey ? \App\Models\Order::first()->status_color : 'gray' }}-100 rounded-full">
                        <svg class="w-4 h-4 text-{{ \App\Models\Order::first()->status === $statusKey ? \App\Models\Order::first()->status_color : 'gray' }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Search') }}</label>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Order # or customer...') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                    <select 
                        wire:model.live="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{{ __('All Status') }}</option>
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="processing">{{ __('Processing') }}</option>
                        <option value="shipped">{{ __('Shipped') }}</option>
                        <option value="delivered">{{ __('Delivered') }}</option>
                        <option value="cancelled">{{ __('Cancelled') }}</option>
                        <option value="refunded">{{ __('Refunded') }}</option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('From Date') }}</label>
                    <input 
                        type="date" 
                        wire:model.live="dateFrom"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('To Date') }}</label>
                    <input 
                        type="date" 
                        wire:model.live="dateTo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Customer -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Customer') }}</label>
                    <select 
                        wire:model.live="customerId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{{ __('All Customers') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('order_number')" class="flex items-center gap-1 hover:text-gray-700">
                                {{ __('Order') }}
                                @if($sortBy === 'order_number')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Customer') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Items') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('total')" class="flex items-center gap-1 hover:text-gray-700">
                                {{ __('Total') }}
                                @if($sortBy === 'total')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Payment') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('created_at')" class="flex items-center gap-1 hover:text-gray-700">
                                {{ __('Date') }}
                                @if($sortBy === 'created_at')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($orders as $order)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">#{{ $order->order_number }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $order->user->name }}</div>
                                <div class="text-sm text-gray-500">{{ $order->user->email }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex -space-x-2">
                                    @foreach($order->items->take(3) as $item)
                                        @if($item->product->primary_image_url)
                                            <img class="h-8 w-8 rounded-full ring-2 ring-white object-cover" src="{{ $item->product->primary_image_url }}" alt="">
                                        @endif
                                    @endforeach
                                    @if($order->items->count() > 3)
                                        <div class="h-8 w-8 rounded-full ring-2 ring-white bg-gray-200 flex items-center justify-center">
                                            <span class="text-xs font-medium text-gray-600">+{{ $order->items->count() - 3 }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ $order->items->count() }} {{ __('items') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $order->formatted_total }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $order->payment->payment_method === 'cod' ? __('COD') : __('Card') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $order->payment->status_color }}-100 text-{{ $order->payment->status_color }}-800">
                                        {{ ucfirst($order->payment->status) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <select 
                                    wire:change="updateOrderStatus({{ $order->id }}, $event.target.value)"
                                    class="text-sm rounded-full px-3 py-1
                                    bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800
                                    border border-{{ $order->status_color }}-200
                                    focus:outline-none focus:ring-2 focus:ring-{{ $order->status_color }}-500 focus:border-{{ $order->status_color }}-500"
                                >
                                    <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                                    <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>{{ __('Processing') }}</option>
                                    <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>{{ __('Shipped') }}</option>
                                    <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>{{ __('Delivered') }}</option>
                                    <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                                    <option value="refunded" {{ $order->status === 'refunded' ? 'selected' : '' }}>{{ __('Refunded') }}</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $order->created_at->format('M d, Y') }}
                                <div class="text-xs text-gray-400">{{ $order->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <a href="/admin/orders/{{ $order->id }}" class="text-blue-600 hover:text-blue-900">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                {{ __('No orders found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $orders->links() }}
        </div>
    </div>
</div>
                                    