<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

new class extends Component
{
    public $stats = [];
    public $recentOrders = [];
    public $topProducts = [];
    public $revenueData = [];
    public $orderStatusData = [];
    protected static string $layout = 'layouts.admin';


    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }

        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        // Calculate stats
        $this->stats = [
            'total_revenue' => Order::whereIn('status', ['delivered', 'completed'])->sum('total'),
            'total_orders' => Order::count(),
            'total_customers' => User::where('is_admin', false)->count(),
            'total_products' => Product::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'low_stock_products' => Product::where('track_quantity', true)->where('quantity', '<', 10)->count(),
            'pending_reviews' => Review::where('is_approved', false)->count(),
            'active_coupons' => \App\Models\Coupon::where('is_active', true)->count(),
        ];

        // Recent orders
        $this->recentOrders = Order::with(['user', 'items'])
            ->latest()
            ->take(5)
            ->get();

        // Top selling products
        $this->topProducts = Product::select('products.id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->groupBy('products.id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();


        // Revenue chart data (last 7 days)
        $this->revenueData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $this->revenueData[] = [
                'date' => $date->format('M d'),
                'revenue' => Order::whereDate('created_at', $date)
                    ->whereIn('status', ['delivered', 'completed'])
                    ->sum('total')
            ];
        }

        // Order status distribution
        $this->orderStatusData = Order::select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => ucfirst($item->status),
                    'count' => $item->count,
                    'color' => $this->getStatusColor($item->status)
                ];
            });
    }

    private function getStatusColor($status)
    {
        return match ($status) {
            'pending' => '#F59E0B',
            'processing' => '#3B82F6',
            'shipped' => '#8B5CF6',
            'delivered' => '#10B981',
            'cancelled' => '#EF4444',
            'refunded' => '#6B7280',
            default => '#6B7280'
        };
    }
}; ?>


<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Dashboard') }}</h1>
        <p class="text-gray-600">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Revenue -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">{{ __('Total Revenue') }}</p>
                    @php
                    $currency = \App\Models\Currency::getDefault();
                    @endphp
                    <p class="text-2xl font-bold text-gray-900">{{ $currency->format($stats['total_revenue']) }}</p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">{{ __('Total Orders') }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_orders']) }}</p>
                    @if($stats['pending_orders'] > 0)
                    <p class="text-xs text-orange-600 mt-1">{{ __(':count pending', ['count' => $stats['pending_orders']]) }}</p>
                    @endif
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Customers -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">{{ __('Total Customers') }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_customers']) }}</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Products -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">{{ __('Total Products') }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_products']) }}</p>
                    @if($stats['low_stock_products'] > 0)
                    <p class="text-xs text-red-600 mt-1">{{ __(':count low stock', ['count' => $stats['low_stock_products']]) }}</p>
                    @endif
                </div>
                <div class="p-3 bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <a href="/admin/products/create" wire:navigate class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center">
            <svg class="w-8 h-8 text-blue-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            <p class="text-sm font-medium text-gray-900">{{ __('Add Product') }}</p>
        </a>

        <a href="/admin/orders?status=pending" wire:navigate class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center relative">
            @if($stats['pending_orders'] > 0)
            <span class="absolute top-2 right-2 bg-red-500 text-white text-xs rounded-full px-2 py-1">
                {{ $stats['pending_orders'] }}
            </span>
            @endif
            <svg class="w-8 h-8 text-orange-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-sm font-medium text-gray-900">{{ __('Pending Orders') }}</p>
        </a>

        <a href="/admin/reviews?status=pending" wire:navigate class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center relative">
            @if($stats['pending_reviews'] > 0)
            <span class="absolute top-2 right-2 bg-yellow-500 text-white text-xs rounded-full px-2 py-1">
                {{ $stats['pending_reviews'] }}
            </span>
            @endif
            <svg class="w-8 h-8 text-yellow-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
            </svg>
            <p class="text-sm font-medium text-gray-900">{{ __('Pending Reviews') }}</p>
        </a>

        <a href="/admin/products?stock=low" wire:navigate class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center relative">
            @if($stats['low_stock_products'] > 0)
            <span class="absolute top-2 right-2 bg-red-500 text-white text-xs rounded-full px-2 py-1">
                {{ $stats['low_stock_products'] }}
            </span>
            @endif
            <svg class="w-8 h-8 text-red-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <p class="text-sm font-medium text-gray-900">{{ __('Low Stock') }}</p>
        </a>
    </div>

    <!-- Charts and Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Revenue Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Revenue (Last 7 Days)') }}</h3>
            <div class="h-64" x-data="revenueChart">
                <canvas x-ref="chart"></canvas>
            </div>
        </div>

        <!-- Order Status Distribution -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Order Status Distribution') }}</h3>
            <div class="h-64" x-data="orderStatusChart">
                <canvas x-ref="chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="p-6 border-b">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('Recent Orders') }}</h3>
                <a href="/admin/orders" wire:navigate class="text-sm text-blue-600 hover:text-blue-700">
                    {{ __('View All') }}
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Order') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Customer') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Total') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Date') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Action') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentOrders as $order)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #{{ $order->order_number }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $order->user->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $order->formatted_total }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $order->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="/admin/orders/{{ $order->id }}" wire:navigate class="text-blue-600 hover:text-blue-900">
                                {{ __('View') }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">{{ __('Top Selling Products') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Product') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('SKU') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Total Sold') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Stock') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Action') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($topProducts as $product)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                @if($product->primary_image_url)
                                <img class="h-10 w-10 rounded-full object-cover" src="{{ $product->primary_image_url }}" alt="{{ $product->name }}">
                                @else
                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                @endif
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $product->sku }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ number_format($product->total_sold) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($product->track_quantity)
                            <span class="{{ $product->quantity < 10 ? 'text-red-600 font-semibold' : '' }}">
                                {{ $product->quantity }}
                            </span>
                            @else
                            <span class="text-gray-400">∞</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="/admin/products/{{ $product->id }}/edit" wire:navigate class="text-blue-600 hover:text-blue-900">
                                {{ __('Edit') }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Revenue Chart
    document.addEventListener('alpine:init', () => {
        Alpine.data('revenueChart', () => ({
            init() {
                const ctx = this.$refs.chart.getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json(array_column($revenueData, 'date')),
                        datasets: [{
                            label: '{{ __("Revenue") }}',
                            data: @json(array_column($revenueData, 'revenue')),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '{{ $currency->symbol }}' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }));

        // Order Status Chart
        Alpine.data('orderStatusChart', () => ({
            init() {
                const ctx = this.$refs.chart.getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($orderStatusData->pluck('status')),
                        datasets: [{
                            data: @json($orderStatusData->pluck('count')),
                            backgroundColor: @json($orderStatusData->pluck('color'))
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }));
    });
</script>
@endpush