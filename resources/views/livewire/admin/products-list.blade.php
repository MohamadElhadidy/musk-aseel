<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $stock = '';
    public ?int $categoryId = null;
    public ?int $brandId = null;
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }

        // Check for stock filter from URL
        $this->stock = request()->get('stock', '');
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

    public function toggleStatus($productId)
    {
        $product = Product::find($productId);
        if ($product) {
            $product->update(['is_active' => !$product->is_active]);
            
            $this->dispatch('toast', 
                type: 'success',
                message: __('Product status updated')
            );
        }
    }

    public function deleteProduct($productId)
    {
        $product = Product::find($productId);
        if ($product) {
            // Delete related data
            $product->images()->delete();
            $product->variants()->delete();
            $product->translations()->delete();
            $product->categories()->detach();
            $product->tags()->detach();
            $product->delete();
            
            $this->dispatch('toast', 
                type: 'success',
                message: __('Product deleted successfully')
            );
        }
    }

    public function with()
    {
        $query = Product::with(['brand', 'categories', 'translations']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('sku', 'like', "%{$this->search}%")
                  ->orWhereHas('translations', function ($tq) {
                      $tq->where('name', 'like', "%{$this->search}%");
                  });
            });
        }

        // Status filter
        if ($this->status !== '') {
            $query->where('is_active', $this->status === 'active');
        }

        // Stock filter
        if ($this->stock === 'low') {
            $query->where('track_quantity', true)->where('quantity', '<', 10);
        } elseif ($this->stock === 'out') {
            $query->where('track_quantity', true)->where('quantity', 0);
        }

        // Category filter
        if ($this->categoryId) {
            $query->whereHas('categories', function ($q) {
                $q->where('categories.id', $this->categoryId);
            });
        }

        // Brand filter
        if ($this->brandId) {
            $query->where('brand_id', $this->brandId);
        }

        // Sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return [
            'products' => $query->paginate(20),
            'categories' => Category::active()->get(),
            'brands' => Brand::active()->get(),
            'layout' => 'components.layouts.admin',
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Products') }}</h1>
        <a href="/admin/products/create" wire:navigate class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-2' : 'mr-2' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            {{ __('Add Product') }}
        </a>
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
                        placeholder="{{ __('SKU or name...') }}"
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
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </select>
                </div>

                <!-- Stock -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Stock') }}</label>
                    <select 
                        wire:model.live="stock"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{{ __('All Stock') }}</option>
                        <option value="low">{{ __('Low Stock') }}</option>
                        <option value="out">{{ __('Out of Stock') }}</option>
                    </select>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }}</label>
                    <select 
                        wire:model.live="categoryId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Brand -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }}</label>
                    <select 
                        wire:model.live="brandId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{{ __('All Brands') }}</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Product') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('sku')" class="flex items-center gap-1 hover:text-gray-700">
                                {{ __('SKU') }}
                                @if($sortBy === 'sku')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Category') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('price')" class="flex items-center gap-1 hover:text-gray-700">
                                {{ __('Price') }}
                                @if($sortBy === 'price')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('quantity')" class="flex items-center gap-1 hover:text-gray-700">
                                {{ __('Stock') }}
                                @if($sortBy === 'quantity')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($products as $product)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    @if($product->primary_image_url)
                                        <img class="h-10 w-10 rounded-lg object-cover" src="{{ $product->primary_image_url }}" alt="{{ $product->name }}">
                                    @else
                                        <div class="h-10 w-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="{{ app()->getLocale() === 'ar' ? 'mr-4' : 'ml-4' }}">
                                        <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                        @if($product->brand)
                                            <div class="text-sm text-gray-500">{{ $product->brand->name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $product->sku }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @foreach($product->categories->take(2) as $category)
                                    <span class="inline-block bg-gray-100 rounded-full px-2 py-1 text-xs">
                                        {{ $category->name }}
                                    </span>
                                @endforeach
                                @if($product->categories->count() > 2)
                                    <span class="text-xs text-gray-400">+{{ $product->categories->count() - 2 }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $product->formatted_price }}
                                @if($product->compare_price)
                                    <br>
                                    <span class="text-xs text-gray-500 line-through">{{ $product->formatted_compare_price }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($product->track_quantity)
                                    <span class="{{ $product->quantity < 10 ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                        {{ $product->quantity }}
                                    </span>
                                @else
                                    <span class="text-gray-400">∞</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button 
                                    wire:click="toggleStatus({{ $product->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 rounded text-xs font-medium {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}"
                                >
                                    {{ $product->is_active ? __('Active') : __('Inactive') }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-3">
                                    <a href="/products/{{ $product->slug }}" target="_blank" class="text-gray-600 hover:text-gray-900" title="{{ __('View') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    <a href="/admin/products/{{ $product->id }}/edit" wire:navigate class="text-blue-600 hover:text-blue-900" title="{{ __('Edit') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <button 
                                        wire:click="deleteProduct({{ $product->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this product?') }}"
                                        class="text-red-600 hover:text-red-900"
                                        title="{{ __('Delete') }}"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="text-gray-500">{{ __('No products found') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="px-6 py-4 border-t">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>