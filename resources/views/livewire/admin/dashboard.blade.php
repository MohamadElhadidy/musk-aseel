<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Review;
use App\Models\Currency;
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
    public $monthlyRevenueData = [];
    public $salesByCategory = [];
    public $topCountries = [];
    public $activityFeed = [];
    public $performanceMetrics = [];

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
        $currency = Currency::getDefault();

        // Calculate stats with comparisons
        $currentMonth = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();

        $currentRevenue = Order::whereIn('status', ['delivered', 'completed'])
            ->whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->sum('total');

        $lastRevenue = Order::whereIn('status', ['delivered', 'completed'])
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->sum('total');

        $revenueChange = $lastRevenue > 0 ? (($currentRevenue - $lastRevenue) / $lastRevenue) * 100 : 0;

        $this->stats = [
            'total_revenue' => [
                'value' => $currentRevenue,
                'formatted' => $currency->format($currentRevenue),
                'change' => $revenueChange,
                'change_type' => $revenueChange >= 0 ? 'increase' : 'decrease'
            ],
            'total_orders' => [
                'value' => Order::whereMonth('created_at', $currentMonth->month)->count(),
                'last_month' => Order::whereMonth('created_at', $lastMonth->month)->count(),
            ],
            'total_customers' => [
                'value' => User::where('is_admin', false)->count(),
                'new_this_month' => User::where('is_admin', false)
                    ->whereMonth('created_at', $currentMonth->month)
                    ->count()
            ],
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'low_stock_products' => Product::where('track_quantity', true)->where('quantity', '<', 10)->count(),
            'out_of_stock' => Product::where('track_quantity', true)->where('quantity', 0)->count(),
            'pending_reviews' => Review::where('is_approved', false)->count(),
            'active_coupons' => \App\Models\Coupon::where('is_active', true)->count(),
            'conversion_rate' => $this->calculateConversionRate(),
            'average_order_value' => Order::whereIn('status', ['delivered', 'completed'])->avg('total') ?? 0,
        ];

        // Recent orders with more details
        $this->recentOrders = Order::with(['user', 'items.product'])
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($order) use ($currency) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->user ? [
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($order->user->name) . '&background=6366F1&color=fff'
                    ] : null,
                    'total' => $currency->format($order->total),
                    'status' => $order->status,
                    'status_color' => $this->getStatusColor($order->status),
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at,
                    'time_ago' => $order->created_at->diffForHumans()
                ];
            });

        // Top selling products with images
        $this->topProducts = Product::select('products.id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['delivered', 'completed'])
            ->groupBy('products.id')
            ->orderByDesc('total_sold')
            ->take(6)
            ->get()
            ->map(function ($product) use ($currency) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->primary_image_url,
                    'price' => $currency->format($product->price),
                    'total_sold' => $product->total_sold,
                    'revenue' => $currency->format($product->price * $product->total_sold),
                    'stock' => $product->track_quantity ? $product->quantity : null,
                    'stock_status' => $this->getStockStatus($product)
                ];
            });

        // Revenue chart data (last 7 days)
        $this->revenueData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayRevenue = Order::whereDate('created_at', $date)
                ->whereIn('status', ['delivered', 'completed'])
                ->sum('total');

            $this->revenueData[] = [
                'date' => $date->format('M d'),
                'day' => $date->format('D'),
                'revenue' => $dayRevenue,
                'orders' => Order::whereDate('created_at', $date)->count()
            ];
        }

        // Monthly revenue (last 12 months)
        $this->monthlyRevenueData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthRevenue = Order::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->whereIn('status', ['delivered', 'completed'])
                ->sum('total');

            $this->monthlyRevenueData[] = [
                'month' => $date->format('M'),
                'year' => $date->format('Y'),
                'revenue' => $monthRevenue
            ];
        }

        // Order status distribution with colors
        $this->orderStatusData = Order::select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => ucfirst($item->status),
                    'count' => $item->count,
                    'color' => $this->getStatusColor($item->status),
                    'percentage' => round(($item->count / Order::count()) * 100, 1)
                ];
            });

        // Sales by category
        $this->salesByCategory = DB::table('categories')
            ->select('categories.id', 'category_translations.name', DB::raw('COUNT(DISTINCT order_items.order_id) as orders_count'), DB::raw('SUM(order_items.quantity * order_items.price) as revenue'))
            ->join('category_translations', function ($join) {
                $join->on('categories.id', '=', 'category_translations.category_id')
                    ->where('category_translations.locale', '=', app()->getLocale());
            })
            ->join('category_product', 'categories.id', '=', 'category_product.category_id')
            ->join('order_items', 'category_product.product_id', '=', 'order_items.product_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['delivered', 'completed'])
            ->where('categories.is_active', true)
            ->groupBy('categories.id', 'category_translations.name')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(function ($category) use ($currency) {
                return [
                    'name' => $category->name,
                    'orders' => $category->orders_count,
                    'revenue' => $currency->format($category->revenue),
                    'revenue_raw' => $category->revenue
                ];
            });
$countryCodes = [
    'Egypt' => 'eg',
    'United States' => 'us',
    'Germany' => 'de',
    'France' => 'fr',
    'Saudi Arabia' => 'sa',
    'المملكة العربية السعودية' => 'sa',
    'United Kingdom' => 'gb',
    'Canada' => 'ca',
    'India' => 'in',
    'Australia' => 'au',
    // Add more as needed
];

// Top countries
$this->topCountries = Order::with('shippingAddress')
    ->whereIn('status', ['delivered', 'completed'])
    ->get()
    ->filter(fn($order) => $order->shippingAddress && $order->shippingAddress->country && $order->shippingAddress->city)
    ->groupBy(fn($order) => $order->shippingAddress->country)
    ->map(function ($orders, $country) {
        $topCity = collect($orders)
            ->groupBy(fn($order) => $order->shippingAddress->city)
            ->sortByDesc(fn($group) => $group->count())
            ->keys()
            ->first();

        return [
            'country' => $country,
            'top_city' => $topCity,
            'orders_count' => $orders->count(),
            'revenue' => $orders->sum('total'),
        ];
    })
    ->sortByDesc('revenue')
    ->take(5)
    ->map(function ($item) use ($currency, $countryCodes) {
        $code = $countryCodes[$item['country']] ?? 'xx'; // fallback 'xx' if country not found
        return [
            'country' => $item['country'],
            'city' => $item['top_city'],
            'orders' => $item['orders_count'],
            'revenue' => $currency->format($item['revenue']),
            'flag' => 'https://flagcdn.com/w40/' . $code . '.png',
        ];
    })
    ->values();




        // Activity feed
        $this->loadActivityFeed();

        // Performance metrics
        $this->performanceMetrics = [
            'page_views' => rand(10000, 50000), // Replace with actual analytics
            'bounce_rate' => rand(20, 40),
            'avg_session' => rand(2, 5) . ':' . rand(10, 59),
            'conversion_rate' => round(rand(10, 40) / 10, 1)
        ];
    }

    private function loadActivityFeed()
    {
        $activities = collect();

        // Recent orders
        $recentOrders = Order::with('user')->latest()->take(3)->get();
        foreach ($recentOrders as $order) {
            $activities->push([
                'type' => 'order',
                'icon' => 'shopping-cart',
                'color' => 'blue',
                'title' => 'New order #' . $order->order_number,
                'description' => ($order->user->name ?? 'Guest') . ' placed an order',
                'time' => $order->created_at->diffForHumans(),
                'timestamp' => $order->created_at
            ]);
        }

        // Recent reviews
        $recentReviews = Review::with(['user', 'product'])->latest()->take(2)->get();
        foreach ($recentReviews as $review) {
            $activities->push([
                'type' => 'review',
                'icon' => 'star',
                'color' => 'yellow',
                'title' => 'New review (' . $review->rating . ' stars)',
                'description' => $review->user->name . ' reviewed ' . $review->product->name,
                'time' => $review->created_at->diffForHumans(),
                'timestamp' => $review->created_at
            ]);
        }

        // Recent registrations
        $recentUsers = User::where('is_admin', false)->latest()->take(2)->get();
        foreach ($recentUsers as $user) {
            $activities->push([
                'type' => 'user',
                'icon' => 'user-plus',
                'color' => 'green',
                'title' => 'New customer registered',
                'description' => $user->name . ' joined the store',
                'time' => $user->created_at->diffForHumans(),
                'timestamp' => $user->created_at
            ]);
        }

        $this->activityFeed = $activities->sortByDesc('timestamp')->take(10)->values();
    }

    private function calculateConversionRate()
    {
        // This is a simplified calculation - implement based on your analytics
        $totalVisits = 1000; // Get from analytics
        $completedOrders = Order::whereIn('status', ['delivered', 'completed'])->count();

        return $totalVisits > 0 ? round(($completedOrders / $totalVisits) * 100, 2) : 0;
    }

    private function getStatusColor($status)
    {
        return match ($status) {
            'pending' => '#F59E0B',
            'processing' => '#3B82F6',
            'shipped' => '#8B5CF6',
            'delivered' => '#10B981',
            'completed' => '#10B981',
            'cancelled' => '#EF4444',
            'refunded' => '#6B7280',
            default => '#6B7280'
        };
    }

    private function getStockStatus($product)
    {
        if (!$product->track_quantity) {
            return ['status' => 'unlimited', 'color' => 'gray', 'text' => 'Unlimited'];
        }

        if ($product->quantity == 0) {
            return ['status' => 'out', 'color' => 'red', 'text' => 'Out of Stock'];
        } elseif ($product->quantity < 10) {
            return ['status' => 'low', 'color' => 'yellow', 'text' => 'Low Stock'];
        } else {
            return ['status' => 'in', 'color' => 'green', 'text' => 'In Stock'];
        }
    }

    public function refreshData()
    {
        $this->loadDashboardData();
        $this->dispatch('data-refreshed');
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Page Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ __('Dashboard') }}</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Welcome back, :name', ['name' => auth()->user()->name]) }} • {{ now()->format('l, F j, Y') }}
                    </p>
                </div>
                <button wire:click="refreshData"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    {{ __('Refresh') }}
                </button>
            </div>
        </div>
    </div>

    <div class="px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <!-- Revenue Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full opacity-10"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-2">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="flex items-center text-sm {{ $stats['total_revenue']['change_type'] === 'increase' ? 'text-green-600' : 'text-red-600' }}">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                @if($stats['total_revenue']['change_type'] === 'increase')
                                <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                @else
                                <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                @endif
                            </svg>
                            {{ number_format(abs($stats['total_revenue']['change']), 1) }}%
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Monthly Revenue') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_revenue']['formatted'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">{{ __('vs last month') }}</p>
                </div>
            </div>

            <!-- Orders Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-green-400 to-green-600 rounded-full opacity-10"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-2">
                        <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-sm font-medium text-orange-600 dark:text-orange-400">
                                {{ $stats['pending_orders'] }} {{ __('pending') }}
                            </span>
                            <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                {{ $stats['processing_orders'] }} {{ __('processing') }}
                            </span>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Total Orders') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_orders']['value']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                        {{ $stats['total_orders']['last_month'] }} {{ __('last month') }}
                    </p>
                </div>
            </div>

            <!-- Customers Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full opacity-10"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-2">
                        <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">
                            +{{ $stats['total_customers']['new_this_month'] }} {{ __('new') }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Total Customers') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_customers']['value']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">{{ __('Active users') }}</p>
                </div>
            </div>

            <!-- Products Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-red-400 to-red-600 rounded-full opacity-10"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-2">
                        <div class="p-2 bg-red-100 dark:bg-red-900 rounded-lg">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-sm font-medium text-yellow-600 dark:text-yellow-400">
                                {{ $stats['low_stock_products'] }} {{ __('low') }}
                            </span>
                            <span class="text-sm font-medium text-red-600 dark:text-red-400">
                                {{ $stats['out_of_stock'] }} {{ __('out') }}
                            </span>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Active Products') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['active_products']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                        {{ number_format($stats['total_products']) }} {{ __('total products') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs opacity-75">{{ __('Conversion Rate') }}</p>
                        <p class="text-xl font-bold">{{ $stats['conversion_rate'] }}%</p>
                    </div>
                    <svg class="w-8 h-8 opacity-25" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>

            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs opacity-75">{{ __('Avg Order Value') }}</p>
                        <p class="text-xl font-bold">{{ Currency::getDefault()->format($stats['average_order_value']) }}</p>
                    </div>
                    <svg class="w-8 h-8 opacity-25" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>

            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs opacity-75">{{ __('Active Coupons') }}</p>
                        <p class="text-xl font-bold">{{ $stats['active_coupons'] }}</p>
                    </div>
                    <svg class="w-8 h-8 opacity-25" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 5a3 3 0 015-2.236A3 3 0 0114.83 6H16a2 2 0 110 4h-5V9a1 1 0 10-2 0v1H4a2 2 0 110-4h1.17C5.06 5.687 5 5.35 5 5zm4 1V5a1 1 0 10-1 1h1zm3 0a1 1 0 10-1-1v1h1z" clip-rule="evenodd"></path>
                        <path d="M9 11H3v5a2 2 0 002 2h4v-7zM11 18h4a2 2 0 002-2v-5h-6v7z"></path>
                    </svg>
                </div>
            </div>

            <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl p-4 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs opacity-75">{{ __('Pending Reviews') }}</p>
                        <p class="text-xl font-bold">{{ $stats['pending_reviews'] }}</p>
                    </div>
                    <svg class="w-8 h-8 opacity-25" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Revenue Chart -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Revenue Overview') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Last 7 days performance') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="px-3 py-1 text-sm bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400 rounded-lg">
                            {{ __('Week') }}
                        </button>
                        <button class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            {{ __('Month') }}
                        </button>
                        <button class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            {{ __('Year') }}
                        </button>
                    </div>
                </div>

                <div x-data 
    x-init="
        const ctx = $refs.chart.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {{ Js::from(array_column($revenueData, 'date')) }},
                datasets: [{
                    label: '{{ __("Revenue") }}',
                    data: {{ Js::from(array_column($revenueData, 'revenue')) }},
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14 },
                        bodyFont: { size: 14 },
                        callbacks: {
                            label: function(context) {
                                return '{{ Currency::getDefault()->symbol }}' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            font: { size: 12 },
                            callback: function(value) {
                                return '{{ Currency::getDefault()->symbol }}' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    "
      class="h-80">
                    <canvas x-ref="chart"></canvas>
                </div>

            </div>

            <!-- Order Status Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">{{ __('Order Status') }}</h3>
<div class="h-64"
     x-data="{}"
     x-init="
        const ctx = $refs.chart.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: {{ Js::from($orderStatusData->pluck('status')) }},
                datasets: [{
                    data: {{ Js::from($orderStatusData->pluck('count')) }},
                    backgroundColor: {{ Js::from($orderStatusData->pluck('color')) }},
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label(context) {
                                return context.label + ': ' + context.parsed + ' orders';
                            }
                        }
                    }
                }
            }
        });
     "
>
    <canvas x-ref="chart"></canvas>
</div>



                <div class="mt-4 space-y-2">
                    @foreach($orderStatusData as $status)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $status['color'] }}"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $status['status'] }}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $status['count'] }} ({{ $status['percentage'] }}%)
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Recent Orders & Top Products -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Recent Orders -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Recent Orders') }}</h3>
                        <a href="/admin/orders" wire:navigate class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                            {{ __('View All') }} →
                        </a>
                    </div>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($recentOrders as $order)
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @if($order['customer'])
                                <img src="{{ $order['customer']['avatar'] }}" alt="{{ $order['customer']['name'] }}"
                                    class="w-10 h-10 rounded-full">
                                @else
                                <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                @endif
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        #{{ $order['order_number'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $order['customer']['name'] ?? __('Guest Order') }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $order['total'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $order['time_ago'] }}</p>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                style="background-color: {{ $order['status_color'] }}20; color: {{ $order['status_color'] }}">
                                {{ ucfirst($order['status']) }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $order['items_count'] }} {{ __('items') }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Top Products -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Top Selling Products') }}</h3>
                        <a href="/admin/products" wire:navigate class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                            {{ __('View All') }} →
                        </a>
                    </div>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($topProducts as $product)
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center gap-3">
                            @if($product['image'])
                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}"
                                class="w-12 h-12 rounded-lg object-cover">
                            @else
                            <div class="w-12 h-12 rounded-lg bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            @endif
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $product['name'] }}</p>
                                <div class="flex items-center gap-4 mt-1">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $product['total_sold'] }} {{ __('sold') }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('Revenue') }}: {{ $product['revenue'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $product['price'] }}</p>
                                @if($product['stock'] !== null)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $product['stock_status']['color'] === 'green' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : '' }}
                                            {{ $product['stock_status']['color'] === 'yellow' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' }}
                                            {{ $product['stock_status']['color'] === 'red' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : '' }}">
                                    {{ $product['stock'] }} {{ __('left') }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Additional Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Sales by Category -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">{{ __('Sales by Category') }}</h3>
                <div class="space-y-4">
                    @foreach($salesByCategory as $category)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $category['name'] }}</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $category['revenue'] }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full"
                                style="width: {{ ($category['revenue_raw'] / $salesByCategory->max('revenue_raw')) * 100 }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                            {{ $category['orders'] }} {{ __('orders') }}
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Top Countries -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">{{ __('Top Countries') }}</h3>
                <div class="space-y-3">
                    @foreach($topCountries as $country)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="{{ $country['flag'] }}" alt="{{ $country['country'] }}"
                                class="w-8 h-6 rounded object-cover">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white"> {{$country['city']}}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $country['orders'] }} {{ __('orders') }}
                                </p>
                            </div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $country['revenue'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">{{ __('Recent Activity') }}</h3>
                <div class="space-y-4">
                    @foreach($activityFeed as $activity)
                    <div class="flex gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-{{ $activity['color'] }}-100 dark:bg-{{ $activity['color'] }}-900 
                                        flex items-center justify-center">
                            <svg class="w-4 h-4 text-{{ $activity['color'] }}-600 dark:text-{{ $activity['color'] }}-400"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($activity['icon'] === 'shopping-cart')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                @elseif($activity['icon'] === 'star')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                @elseif($activity['icon'] === 'user-plus')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                @endif
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $activity['title'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $activity['description'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $activity['time'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

