<?php
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

new class extends Component
{
    use WithPagination;
    
    public string $query = '';
    public string $sortBy = 'relevance';
    public array $selectedCategories = [];
    public array $selectedBrands = [];
    public ?float $minPrice = null;
    public ?float $maxPrice = null;
    public bool $inStockOnly = false;
    public string $view = 'grid'; // grid or list
    
    public function mount()
    {
        $this->query = request()->get('q', '');
    }
    
    public function updatedQuery()
    {
        $this->resetPage();
    }
    
    public function updatedSortBy()
    {
        $this->resetPage();
    }
    
    public function updatedSelectedCategories()
    {
        $this->resetPage();
    }
    
    public function updatedSelectedBrands()
    {
        $this->resetPage();
    }
    
    public function updatedInStockOnly()
    {
        $this->resetPage();
    }
    
    public function applyPriceFilter()
    {
        $this->resetPage();
    }
    
    public function clearFilters()
    {
        $this->selectedCategories = [];
        $this->selectedBrands = [];
        $this->minPrice = null;
        $this->maxPrice = null;
        $this->inStockOnly = false;
        $this->resetPage();
    }
    
    public function toggleView($view)
    {
        $this->view = $view;
    }
    
    public function with()
    {
        $productsQuery = Product::active()
            ->with(['images', 'translations', 'brand', 'categories']);
        
        // Search query
        if ($this->query) {
            $productsQuery->where(function ($q) {
                // Search in translations
                $q->whereHas('translations', function ($tq) {
                    $tq->where('locale', app()->getLocale())
                       ->where(function ($sq) {
                           $sq->where('name', 'like', "%{$this->query}%")
                              ->orWhere('description', 'like', "%{$this->query}%")
                              ->orWhere('short_description', 'like', "%{$this->query}%");
                       });
                })
                // Search in SKU
                ->orWhere('sku', 'like', "%{$this->query}%")
                // Search in brand name
                ->orWhereHas('brand', function ($bq) {
                    $bq->where('name', 'like', "%{$this->query}%");
                })
                // Search in category names
                ->orWhereHas('categories.translations', function ($cq) {
                    $cq->where('locale', app()->getLocale())
                       ->where('name', 'like', "%{$this->query}%");
                });
            });
        }
        
        // Category filter
        if (!empty($this->selectedCategories)) {
            $productsQuery->whereHas('categories', function ($q) {
                $q->whereIn('categories.id', $this->selectedCategories);
            });
        }
        
        // Brand filter
        if (!empty($this->selectedBrands)) {
            $productsQuery->whereIn('brand_id', $this->selectedBrands);
        }
        
        // Price filter
        if ($this->minPrice !== null) {
            $productsQuery->where('price', '>=', $this->minPrice);
        }
        
        if ($this->maxPrice !== null) {
            $productsQuery->where('price', '<=', $this->maxPrice);
        }
        
        // Stock filter
        if ($this->inStockOnly) {
            $productsQuery->where('quantity', '>', 0);
        }
        
        // Sorting
        switch ($this->sortBy) {
            case 'price_low_high':
                $productsQuery->orderBy('price', 'asc');
                break;
            case 'price_high_low':
                $productsQuery->orderBy('price', 'desc');
                break;
            case 'name_a_z':
                $productsQuery->orderByTranslation('name', 'asc');
                break;
            case 'name_z_a':
                $productsQuery->orderByTranslation('name', 'desc');
                break;
            case 'newest':
                $productsQuery->latest();
                break;
            case 'relevance':
            default:
                if ($this->query) {
                    // Order by relevance based on where the match was found
                    $productsQuery->orderByRaw("
                        CASE 
                            WHEN sku LIKE ? THEN 1
                            WHEN EXISTS (
                                SELECT 1 FROM product_translations 
                                WHERE product_id = products.id 
                                AND locale = ? 
                                AND name LIKE ?
                            ) THEN 2
                            ELSE 3
                        END
                    ", ["%{$this->query}%", app()->getLocale(), "%{$this->query}%"]);
                }
                $productsQuery->orderBy('is_featured', 'desc')->latest();
                break;
        }
        
        // Get categories and brands for filters
        $categoriesQuery = Category::active()
            ->withCount(['products' => function ($q) {
                $q->active();
                if ($this->query) {
                    $q->where(function ($pq) {
                        $pq->whereHas('translations', function ($tq) {
                            $tq->where('locale', app()->getLocale())
                               ->where('name', 'like', "%{$this->query}%");
                        })
                        ->orWhere('sku', 'like', "%{$this->query}%");
                    });
                }
            }])
            ->having('products_count', '>', 0);
            
        $brandsQuery = Brand::active()
            ->withCount(['products' => function ($q) {
                $q->active();
                if ($this->query) {
                    $q->where(function ($pq) {
                        $pq->whereHas('translations', function ($tq) {
                            $tq->where('locale', app()->getLocale())
                               ->where('name', 'like', "%{$this->query}%");
                        })
                        ->orWhere('sku', 'like', "%{$this->query}%");
                    });
                }
            }])
            ->having('products_count', '>', 0);
        
        // Get price range
        $priceRange = Product::active()
            ->when($this->query, function ($q) {
                $q->where(function ($pq) {
                    $pq->whereHas('translations', function ($tq) {
                        $tq->where('locale', app()->getLocale())
                           ->where('name', 'like', "%{$this->query}%");
                    })
                    ->orWhere('sku', 'like', "%{$this->query}%");
                });
            })
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();
        
        return [
            'products' => $productsQuery->paginate(24),
            'categories' => $categoriesQuery->get(),
            'brands' => $brandsQuery->get(),
            'priceRange' => $priceRange,
            'totalResults' => $productsQuery->count(),
        ];
    }
}; ?>

<div>
    <!-- Search Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    @if($query)
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ __('Search Results for') }}: "{{ $query }}"
                        </h1>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ trans_choice('product.found_count', $totalResults, ['count' => $totalResults]) }}
                        </p>
                    @else
                        <h1 class="text-2xl font-bold text-gray-900">{{ __('All Products') }}</h1>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ trans_choice('product.total_count', $totalResults, ['count' => $totalResults]) }}
                        </p>
                    @endif
                </div>
                
                <div class="flex items-center gap-4">
                    <!-- View Toggle -->
                    <div class="flex items-center gap-2">
                        <button wire:click="toggleView('grid')" 
                                class="p-2 rounded-lg {{ $view === 'grid' ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                        </button>
                        <button wire:click="toggleView('list')" 
                                class="p-2 rounded-lg {{ $view === 'list' ? 'bg-gray-200 text-gray-900' : 'text-gray-500 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Sort Dropdown -->
                    <select wire:model.live="sortBy" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="relevance">{{ __('Sort by Relevance') }}</option>
                        <option value="newest">{{ __('Newest First') }}</option>
                        <option value="price_low_high">{{ __('Price: Low to High') }}</option>
                        <option value="price_high_low">{{ __('Price: High to Low') }}</option>
                        <option value="name_a_z">{{ __('Name: A to Z') }}</option>
                        <option value="name_z_a">{{ __('Name: Z to A') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Filters Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('Filters') }}</h2>
                        @if(count($selectedCategories) > 0 || count($selectedBrands) > 0 || $minPrice || $maxPrice || $inStockOnly)
                            <button wire:click="clearFilters" 
                                    class="text-sm text-blue-600 hover:text-blue-800">
                                {{ __('Clear All') }}
                            </button>
                        @endif
                    </div>

                    <!-- Categories Filter -->
                    @if($categories->count() > 0)
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">{{ __('Categories') }}</h3>
                            <div class="space-y-2">
                                @foreach($categories as $category)
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               wire:model.live="selectedCategories" 
                                               value="{{ $category->id }}"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">
                                            {{ $category->name }} ({{ $category->products_count }})
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Brands Filter -->
                    @if($brands->count() > 0)
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">{{ __('Brands') }}</h3>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                @foreach($brands as $brand)
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               wire:model.live="selectedBrands" 
                                               value="{{ $brand->id }}"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">
                                            {{ $brand->name }} ({{ $brand->products_count }})
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Price Range Filter -->
                    @if($priceRange && $priceRange->min_price !== $priceRange->max_price)
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">{{ __('Price Range') }}</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-600">{{ __('Min Price') }}</label>
                                    <input type="number" 
                                           wire:model.lazy="minPrice" 
                                           min="{{ $priceRange->min_price }}"
                                           max="{{ $priceRange->max_price }}"
                                           placeholder="{{ number_format($priceRange->min_price, 2) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600">{{ __('Max Price') }}</label>
                                    <input type="number" 
                                           wire:model.lazy="maxPrice" 
                                           min="{{ $priceRange->min_price }}"
                                           max="{{ $priceRange->max_price }}"
                                           placeholder="{{ number_format($priceRange->max_price, 2) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <button wire:click="applyPriceFilter" 
                                        class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                                    {{ __('Apply') }}
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Stock Filter -->
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   wire:model.live="inStockOnly" 
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">{{ __('In Stock Only') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Products Grid/List -->
            <div class="lg:col-span-3">
                @if($products->count() > 0)
                    @if($view === 'grid')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($products as $product)
                                <livewire:product-card :product="$product" :key="$product->id" />
                            @endforeach
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($products as $product)
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                                    <div class="flex gap-4">
                                        @if($product->primaryImage)
                                            <img src="{{ $product->primaryImage->image_url }}" 
                                                 alt="{{ $product->name }}"
                                                 class="w-32 h-32 object-cover rounded-lg">
                                        @else
                                            <div class="w-32 h-32 bg-gray-200 rounded-lg flex items-center justify-center">
                                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif
                                        
                                        <div class="flex-1">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900">
                                                        <a href="{{ route('products.show', $product->slug) }}" 
                                                           wire:navigate
                                                           class="hover:text-blue-600">
                                                            {{ $product->name }}
                                                        </a>
                                                    </h3>
                                                    
                                                    @if($product->brand)
                                                        <p class="text-sm text-gray-600">{{ $product->brand->name }}</p>
                                                    @endif
                                                    
                                                    <p class="text-sm text-gray-600 mt-2">{{ Str::limit($product->description, 150) }}</p>
                                                    
                                                    <div class="flex items-center gap-4 mt-2">
                                                        <livewire:product-rating :product="$product" :key="'rating-'.$product->id" />
                                                        
                                                        @if($product->quantity > 0)
                                                            <span class="text-sm text-green-600">{{ __('In Stock') }}</span>
                                                        @else
                                                            <span class="text-sm text-red-600">{{ __('Out of Stock') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                
                                                <div class="text-right ml-4">
                                                    @if($product->compare_price > $product->price)
                                                        <p class="text-sm text-gray-500 line-through">
                                                            {{ format_currency($product->compare_price) }}
                                                        </p>
                                                    @endif
                                                    <p class="text-xl font-bold text-gray-900">
                                                        {{ format_currency($product->price) }}
                                                    </p>
                                                    
                                                    <div class="mt-3 flex gap-2">
                                                        <livewire:add-to-cart-button 
                                                            :product="$product" 
                                                            :key="'cart-'.$product->id"
                                                            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700" />
                                                        
                                                        <livewire:wishlist-button 
                                                            :product="$product" 
                                                            :key="'wishlist-'.$product->id"
                                                            class="p-2 border border-gray-300 rounded-lg hover:bg-gray-50" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Pagination -->
                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @else
                    <!-- No Results -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('No products found') }}</h3>
                        <p class="text-gray-600 mb-6">
                            @if($query)
                                {{ __('Try adjusting your search or filters to find what you\'re looking for.') }}
                            @else
                                {{ __('No products match the selected filters.') }}
                            @endif
                        </p>
                        @if(count($selectedCategories) > 0 || count($selectedBrands) > 0 || $minPrice || $maxPrice || $inStockOnly)
                            <button wire:click="clearFilters" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                {{ __('Clear All Filters') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>