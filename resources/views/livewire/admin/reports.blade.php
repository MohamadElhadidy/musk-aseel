<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    public string $reportType = 'sales';
    public string $dateRange = 'last_30_days';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public string $groupBy = 'day';
    public bool $showCharts = true;
    
    // Export settings
    public string $exportFormat = 'csv';

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }
        
        $this->applyDateRange();
    }

    public function updatedDateRange()
    {
        $this->applyDateRange();
    }

    protected function applyDateRange()
    {
        switch ($this->dateRange) {
            case 'today':
                $this->startDate = now()->startOfDay()->format('Y-m-d');
                $this->endDate = now()->endOfDay()->format('Y-m-d');
                break;
            case 'yesterday':
                $this->startDate = now()->subDay()->startOfDay()->format('Y-m-d');
                $this->endDate = now()->subDay()->endOfDay()->format('Y-m-d');
                break;
            case 'last_7_days':
                $this->startDate = now()->subDays(6)->startOfDay()->format('Y-m-d');
                $this->endDate = now()->endOfDay()->format('Y-m-d');
                break;
            case 'last_30_days':
                $this->startDate = now()->subDays(29)->startOfDay()->format('Y-m-d');
                $this->endDate = now()->endOfDay()->format('Y-m-d');
                break;
            case 'this_month':
                $this->startDate = now()->startOfMonth()->format('Y-m-d');
                $this->endDate = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->endDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $this->startDate = now()->startOfYear()->format('Y-m-d');
                $this->endDate = now()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
                // Keep custom dates
                break;
        }
    }

    protected function getSalesData()
    {
        $query = Order::whereBetween('created_at', [$this->startDate, $this->endDate])
            ->where('payment_status', 'paid');

        // Group by period
        switch ($this->groupBy) {
            case 'day':
                $data = $query->selectRaw('DATE(created_at) as period, COUNT(*) as total_orders, SUM(total) as total_sales')
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                break;
            case 'week':
                $data = $query->selectRaw('YEARWEEK(created_at) as period, COUNT(*) as total_orders, SUM(total) as total_sales')
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                break;
            case 'month':
                $data = $query->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, COUNT(*) as total_orders, SUM(total) as total_sales')
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                break;
        }

        return $data;
    }

    protected function getProductsData()
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$this->startDate, $this->endDate])
            ->where('orders.payment_status', 'paid')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get();
    }

    protected function getCustomersData()
    {
        return User::withCount(['orders' => function ($query) {
                $query->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->where('payment_status', 'paid');
            }])
            ->withSum(['orders' => function ($query) {
                $query->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->where('payment_status', 'paid');
            }], 'total')
            ->having('orders_count', '>', 0)
            ->orderByDesc('orders_sum_total')
            ->limit(20)
            ->get();
    }

    protected function getCategoriesData()
    {
        return DB::table('categories')
            ->join('category_product', 'categories.id', '=', 'category_product.category_id')
            ->join('order_items', 'category_product.product_id', '=', 'order_items.product_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$this->startDate, $this->endDate])
            ->where('orders.payment_status', 'paid')
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('SUM(order_items.quantity) as total_products'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    protected function getInventoryData()
    {
        return Product::select(
                'id',
                'name',
                'sku',
                'quantity',
                'price',
                DB::raw('quantity * cost as inventory_value')
            )
            ->where('track_quantity', true)
            ->orderBy('quantity', 'asc')
            ->limit(20)
            ->get();
    }

    public function exportReport()
    {
        $data = match ($this->reportType) {
            'sales' => $this->getSalesData(),
            'products' => $this->getProductsData(),
            'customers' => $this->getCustomersData(),
            'categories' => $this->getCategoriesData(),
            'inventory' => $this->getInventoryData(),
        };

        if ($this->exportFormat === 'csv') {
            return $this->exportCsv($data);
        } else {
            return $this->exportPdf($data);
        }
    }

    protected function exportCsv($data)
    {
        $filename = "{$this->reportType}-report-" . now()->format('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            
            // Headers based on report type
            switch ($this->reportType) {
                case 'sales':
                    fputcsv($handle, ['Period', 'Orders', 'Sales']);
                    foreach ($data as $row) {
                        fputcsv($handle, [$row->period, $row->total_orders, $row->total_sales]);
                    }
                    break;
                case 'products':
                    fputcsv($handle, ['SKU', 'Product', 'Quantity Sold', 'Revenue']);
                    foreach ($data as $row) {
                        fputcsv($handle, [$row->sku, $row->name, $row->total_quantity, $row->total_revenue]);
                    }
                    break;
                case 'customers':
                    fputcsv($handle, ['Customer', 'Email', 'Orders', 'Total Spent']);
                    foreach ($data as $row) {
                        fputcsv($handle, [$row->name, $row->email, $row->orders_count, $row->orders_sum_total]);
                    }
                    break;
                case 'categories':
                    fputcsv($handle, ['Category', 'Orders', 'Products Sold', 'Revenue']);
                    foreach ($data as $row) {
                        fputcsv($handle, [$row->name, $row->total_orders, $row->total_products, $row->total_revenue]);
                    }
                    break;
                case 'inventory':
                    fputcsv($handle, ['SKU', 'Product', 'Stock', 'Price', 'Value']);
                    foreach ($data as $row) {
                        fputcsv($handle, [$row->sku, $row->name, $row->quantity, $row->price, $row->inventory_value]);
                    }
                    break;
            }
            
            fclose($handle);
        }, $filename);
    }

    protected function exportPdf($data)
    {
        // In a real implementation, you would use a PDF library like DomPDF or TCPDF
        $this->dispatch('toast', type: 'info', message: 'PDF export coming soon');
    }

    public function with()
    {
        $reportData = match ($this->reportType) {
            'sales' => $this->getSalesData(),
            'products' => $this->getProductsData(),
            'customers' => $this->getCustomersData(),
            'categories' => $this->getCategoriesData(),
            'inventory' => $this->getInventoryData(),
        };

        // Calculate summary statistics
        $stats = [];
        
        if ($this->reportType === 'sales') {
            $stats = [
                'total_orders' => Order::whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->where('payment_status', 'paid')->count(),
                'total_revenue' => Order::whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->where('payment_status', 'paid')->sum('total'),
                'average_order_value' => Order::whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->where('payment_status', 'paid')->avg('total'),
                'new_customers' => User::whereBetween('created_at', [$this->startDate, $this->endDate])->count(),
            ];
        }

        return [
            'reportData' => $reportData,
            'stats' => $stats,
            'chartData' => $this->reportType === 'sales' ? $this->prepareChartData($reportData) : null,
        ];
    }

    protected function prepareChartData($data)
    {
        return [
            'labels' => $data->pluck('period')->toArray(),
            'orders' => $data->pluck('total_orders')->toArray(),
            'sales' => $data->pluck('total_sales')->toArray(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
            <p class="text-sm text-gray-600 mt-1">View and export business analytics</p>
        </div>
        <button wire:click="exportReport" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export Report
        </button>
    </div>

    <!-- Report Type Tabs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button wire:click="$set('reportType', 'sales')"
                        class="px-6 py-3 text-sm font-medium {{ $reportType === 'sales' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Sales Report
                </button>
                <button wire:click="$set('reportType', 'products')"
                        class="px-6 py-3 text-sm font-medium {{ $reportType === 'products' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Products Report
                </button>
                <button wire:click="$set('reportType', 'customers')"
                        class="px-6 py-3 text-sm font-medium {{ $reportType === 'customers' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Customers Report
                </button>
                <button wire:click="$set('reportType', 'categories')"
                        class="px-6 py-3 text-sm font-medium {{ $reportType === 'categories' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Categories Report
                </button>
                <button wire:click="$set('reportType', 'inventory')"
                        class="px-6 py-3 text-sm font-medium {{ $reportType === 'inventory' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Inventory Report
                </button>
            </nav>
        </div>

        <!-- Filters -->
        <div class="p-4 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <select wire:model.live="dateRange" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="this_year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                
                @if($dateRange === 'custom')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" 
                               wire:model="startDate" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" 
                               wire:model="endDate" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                @endif

                @if($reportType === 'sales')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                        <select wire:model.live="groupBy" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="day">Day</option>
                            <option value="week">Week</option>
                            <option value="month">Month</option>
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Export Format</label>
                    <select wire:model="exportFormat" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="csv">CSV</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="p-6">
            @if($reportType === 'sales')
                <!-- Sales Stats -->
                @if(count($stats) > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm font-medium text-gray-600">Total Orders</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_orders']) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($stats['total_revenue'], 2) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm font-medium text-gray-600">Average Order Value</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($stats['average_order_value'], 2) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm font-medium text-gray-600">New Customers</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['new_customers']) }}</p>
                        </div>
                    </div>
                @endif

                <!-- Sales Chart -->
                @if($showCharts && $chartData)
                    <div class="mb-6">
                        <canvas id="salesChart" class="w-full" style="height: 300px;"></canvas>
                    </div>
                @endif

                <!-- Sales Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $row)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row->period }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($row->total_orders) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($row->total_sales, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No sales data found for the selected period
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($reportType === 'products')
                <!-- Products Report -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity Sold</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $row)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row->sku }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $row->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($row->total_quantity) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($row->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No product sales found for the selected period
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($reportType === 'customers')
                <!-- Customers Report -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $row)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($row->orders_count) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($row->orders_sum_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No customer data found for the selected period
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($reportType === 'categories')
                <!-- Categories Report -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products Sold</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $row)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ json_decode($row->name)->en ?? $row->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($row->total_orders) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($row->total_products) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($row->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No category data found for the selected period
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($reportType === 'inventory')
                <!-- Inventory Report -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $row)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row->sku }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ json_decode($row->name)->en ?? $row->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="{{ $row->quantity <= 10 ? 'text-red-600 font-medium' : 'text-gray-900' }}">
                                            {{ number_format($row->quantity) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($row->price, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($row->inventory_value, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($row->quantity == 0)
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                                Out of Stock
                                            </span>
                                        @elseif($row->quantity <= 10)
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                                Low Stock
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                                In Stock
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No inventory data found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@if($showCharts && $chartData && $reportType === 'sales')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:navigated', function () {
            const ctx = document.getElementById('salesChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($chartData['labels']),
                        datasets: [{
                            label: 'Sales',
                            data: @json($chartData['sales']),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.1,
                            yAxisID: 'y',
                        }, {
                            label: 'Orders',
                            data: @json($chartData['orders']),
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.1,
                            yAxisID: 'y1',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                ticks: {
                                    callback: function(value) {
                                        return ' + value.toLocaleString();
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false,
                                },
                            },
                        }
                    }
                });
            }
        });
    </script>
@endif