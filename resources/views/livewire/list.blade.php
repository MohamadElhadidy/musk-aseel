<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Tag;

new class extends Component
{
    use WithPagination;

    public ?Category $category = null;
    public string $slug = '';
    public string $sortBy = 'newest';
    public array $selectedBrands = [];
    public array $selectedTags = [];
    public ?float $minPrice = null;
    public ?float $maxPrice = null;
    public bool $showFilters = false;

    public function mount($slug = null)
    {

        if ($this->category) {
            $this->slug = $slug;

            $this->category = Category::where('slug', $slug)
                ->active()
                ->firstOrFail();
        }
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function updatedSortBy()
    {
        $this->resetPage();
    }

    public function updatedSelectedBrands()
    {
        $this->resetPage();
    }

    public function updatedSelectedTags()
    {
        $this->resetPage();
    }

    public function applyPriceFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->selectedBrands = [];
        $this->selectedTags = [];
        $this->minPrice = null;
        $this->maxPrice = null;
        $this->resetPage();
    }

    public function with()
    {
        $query = Product::active()->with(['images', 'translations', 'brand']);

        // Category filter
        if ($this->category) {
            $categoryIds = collect([$this->category->id]);

            // Include subcategories
            $this->category->children->each(function ($child) use (&$categoryIds) {
                $categoryIds->push($child->id);
                $child->children->each(function ($subChild) use (&$categoryIds) {
                    $categoryIds->push($subChild->id);
                });
            });

            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        // Brand filter
        if (!empty($this->selectedBrands)) {
            $query->whereIn('brand_id', $this->selectedBrands);
        }

        // Tag filter
        if (!empty($this->selectedTags)) {
            $query->whereHas('tags', function ($q) {
                $q->whereIn('tags.id', $this->selectedTags);
            });
        }

        // Price filter
        if ($this->minPrice !== null) {
            $query->where('price', '>=', $this->minPrice);
        }
        if ($this->maxPrice !== null) {
            $query->where('price', '<=', $this->maxPrice);
        }

        // Sorting
        switch ($this->sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'name':
                $query->orderBy('id'); // Would need to join with translations for proper name sorting
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        $products = $query->paginate(12);

        // Get available brands and tags for filters
        $availableBrands = Brand::active()
            ->whereHas('products', function ($q) {
                $q->active();
                if ($this->category) {
                    $q->whereHas('categories', function ($cq) {
                        $cq->where('categories.id', $this->category->id);
                    });
                }
            })
            ->get();

        $availableTags = Tag::whereHas('products', function ($q) {
            $q->active();
            if ($this->category) {
                $q->whereHas('categories', function ($cq) {
                    $cq->where('categories.id', $this->category->id);
                });
            }
        })->get();

        return [
            'products' => $products,
            'availableBrands' => $availableBrands,
            'availableTags' => $availableTags,
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div>
    <!-- Breadcrumb -->
    <div class="bg-gray-100 py-4">
        <div class="container mx-auto px-4">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 {{ app()->getLocale() === 'ar' ? 'space-x-reverse' : '' }}">
                    <li class="inline-flex items-center">
                        <a href="/" wire:navigate class="text-gray-700 hover:text-blue-600">
                            {{ __('Home') }}
                        </a>
                    </li>
                    @if($category)
                    @if($category->parent)
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400 {{ app()->getLocale() === 'ar' ? 'transform rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="/categories/{{ $category->parent->slug }}" wire:navigate class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }} text-gray-700 hover:text-blue-600">
                                {{ $category->parent->name }}
                            </a>
                        </div>
                    </li>
                    @endif
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400 {{ app()->getLocale() === 'ar' ? 'transform rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }} text-gray-500">
                                {{ $category->name }}
                            </span>
                        </div>
                    </li>
                    @else
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400 {{ app()->getLocale() === 'ar' ? 'transform rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }} text-gray-500">
                                {{ __('All Products') }}
                            </span>
                        </div>
                    </li>
                    @endif
                </ol>
            </nav>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Filters Sidebar -->
            <aside class="lg:w-1/4">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">{{ __('Filters') }}</h3>
                        <button wire:click="clearFilters" class="text-sm text-blue-600 hover:underline">
                            {{ __('Clear all') }}
                        </button>
                    </div>

                    <!-- Mobile Filter Toggle -->
                    <button
                        wire:click="toggleFilters"
                        class="lg:hidden w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg mb-4">
                        {{ __('Show Filters') }}
                    </button>

                    <div class="{{ $showFilters ? 'block' : 'hidden' }} lg:block space-y-6">
                        <!-- Price Range -->
                        <div>
                            <h4 class="font-semibold mb-3">{{ __('Price Range') }}</h4>
                            <div class="space-y-2">
                                <input
                                    type="number"
                                    wire:model="minPrice"
                                    placeholder="{{ __('Min') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                <input
                                    type="number"
                                    wire:model="maxPrice"
                                    placeholder="{{ __('Max') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                <button
                                    wire:click="applyPriceFilter"
                                    class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">
                                    {{ __('Apply') }}
                                </button>
                            </div>
                        </div>

                        <!-- Brands -->
                        @if($availableBrands->count() > 0)
                        <div>
                            <h4 class="font-semibold mb-3">{{ __('Brands') }}</h4>
                            <div class="space-y-2">
                                @foreach($availableBrands as $brand)
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedBrands"
                                        value="{{ $brand->id }}"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }}">
                                        {{ $brand->name }}
                                    </span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Tags -->
                        @if($availableTags->count() > 0)
                        <div>
                            <h4 class="font-semibold mb-3">{{ __('Tags') }}</h4>
                            <div class="space-y-2">
                                @foreach($availableTags as $tag)
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedTags"
                                        value="{{ $tag->id }}"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }}">
                                        {{ $tag->name }}
                                    </span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </aside>

            <!-- Products Grid -->
            <div class="lg:w-3/4">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <h1 class="text-2xl font-bold mb-2 sm:mb-0">
                        {{ $category ? $category->name : __('All Products') }}
                    </h1>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-600">{{ __('Sort by:') }}</span>
                        <select
                            wire:model.live="sortBy"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="newest">{{ __('Newest') }}</option>
                            <option value="price_low">{{ __('Price: Low to High') }}</option>
                            <option value="price_high">{{ __('Price: High to Low') }}</option>
                            <option value="name">{{ __('Name') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Subcategories -->
                @if($category && $category->children->count() > 0)
                <div class="mb-6">
                    <h3 class="font-semibold mb-3">{{ __('Subcategories') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($category->children as $child)
                        <a
                            href="/categories/{{ $child->slug }}"
                            wire:navigate
                            class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                            {{ $child->name }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Products -->
                @if($products->count() > 0)
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    @foreach($products as $product)
                    <livewire:product.card :product="$product" :key="$product->id" />
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-8">
                    {{ $products->links() }}
                </div>
                @else
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('No products found') }}</h3>
                    <p class="text-gray-600">{{ __('Try adjusting your filters or search criteria') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>