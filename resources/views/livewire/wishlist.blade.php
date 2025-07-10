<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Cart;
use App\Models\Product;

new class extends Component
{
    use WithPagination;

    public array $guestWishlist = [];

    public function mount()
    {
        // Load guest wishlist from session
        $this->guestWishlist = session('wishlist', []);
    }

    public function removeFromWishlist($productId)
    {
        if (auth()->check()) {
            auth()->user()->wishlist()->detach($productId);
        } else {
            $this->guestWishlist = array_filter($this->guestWishlist, function($id) use ($productId) {
                return $id != $productId;
            });
            session(['wishlist' => $this->guestWishlist]);
        }
        
        $this->dispatch('wishlist-updated');
        $this->dispatch('toast', 
            type: 'info',
            message: __('Product removed from wishlist')
        );
    }

    public function addToCart($productId)
    {
        $product = Product::find($productId);
        
        if (!$product) {
            return;
        }

        if (!$product->isInStock()) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Product is out of stock')
            );
            return;
        }

        $cart = Cart::getCurrentCart();
        $cart->addItem($product);

        $this->dispatch('cart-updated');
        $this->dispatch('toast', 
            type: 'success',
            message: __('Product added to cart')
        );
    }

    public function moveAllToCart()
    {
        $cart = Cart::getCurrentCart();
        $addedCount = 0;
        $outOfStockCount = 0;

        $products = $this->getWishlistProducts();

        foreach ($products as $product) {
            if ($product->isInStock()) {
                $cart->addItem($product);
                $addedCount++;
            } else {
                $outOfStockCount++;
            }
        }

        if ($addedCount > 0) {
            $this->dispatch('cart-updated');
            $this->dispatch('toast', 
                type: 'success',
                message: __(':count products added to cart', ['count' => $addedCount])
            );
        }

        if ($outOfStockCount > 0) {
            $this->dispatch('toast', 
                type: 'warning',
                message: __(':count products are out of stock', ['count' => $outOfStockCount])
            );
        }
    }

    public function clearWishlist()
    {
        if (auth()->check()) {
            auth()->user()->wishlist()->detach();
        } else {
            $this->guestWishlist = [];
            session(['wishlist' => []]);
        }
        
        $this->dispatch('wishlist-updated');
        $this->dispatch('toast', 
            type: 'info',
            message: __('Wishlist cleared')
        );
    }

    private function getWishlistProducts()
    {
        if (auth()->check()) {
            return auth()->user()->wishlist;
        } else {
            return Product::whereIn('id', $this->guestWishlist)->get();
        }
    }

    public function with()
    {
        $products = $this->getWishlistProducts();
        
        // Paginate manually for guest wishlist
        if (!auth()->check()) {
            $perPage = 12;
            $page = request()->get('page', 1);
            $offset = ($page - 1) * $perPage;
            
            $items = $products->slice($offset, $perPage);
            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $products->count(),
                $perPage,
                $page,
                ['path' => request()->url()]
            );
        } else {
            $products = auth()->user()->wishlist()->paginate(12);
        }

        return [
            'products' => $products,
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">{{ __('My Wishlist') }}</h1>
        @if($products->count() > 0)
            <div class="flex gap-2">
                <button 
                    wire:click="moveAllToCart"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                >
                    {{ __('Add All to Cart') }}
                </button>
                <button 
                    wire:click="clearWishlist"
                    wire:confirm="{{ __('Are you sure you want to clear your wishlist?') }}"
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
                >
                    {{ __('Clear Wishlist') }}
                </button>
            </div>
        @endif
    </div>

    @if($products->count() > 0)
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($products as $product)
                <div class="bg-white rounded-lg shadow-sm hover:shadow-lg transition-shadow" wire:key="product-{{ $product->id }}">
                    <!-- Product Image -->
                    <a href="/products/{{ $product->slug }}" wire:navigate class="block aspect-square overflow-hidden rounded-t-lg relative">
                        @if($product->primary_image_url)
                            <img 
                                src="{{ $product->primary_image_url }}" 
                                alt="{{ $product->name }}"
                                class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                            >
                        @else
                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif

                        <!-- Remove from Wishlist -->
                        <button 
                            wire:click.stop="removeFromWishlist({{ $product->id }})"
                            class="absolute top-2 {{ app()->getLocale() === 'ar' ? 'left-2' : 'right-2' }} p-2 bg-white rounded-full shadow-md hover:shadow-lg transition"
                        >
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>

                        <!-- Discount Badge -->
                        @if($product->discount_percentage)
                            <div class="absolute top-2 {{ app()->getLocale() === 'ar' ? 'right-2' : 'left-2' }} bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold">
                                -{{ $product->discount_percentage }}%
                            </div>
                        @endif

                        <!-- Out of Stock Overlay -->
                        @if(!$product->isInStock())
                            <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                                <span class="bg-white text-gray-900 px-4 py-2 rounded font-semibold">
                                    {{ __('Out of Stock') }}
                                </span>
                            </div>
                        @endif
                    </a>

                    <!-- Product Info -->
                    <div class="p-4">
                        <!-- Brand -->
                        @if($product->brand)
                            <p class="text-xs text-gray-500 mb-1">{{ $product->brand->name }}</p>
                        @endif

                        <!-- Product Name -->
                        <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">
                            <a href="/products/{{ $product->slug }}" wire:navigate class="hover:text-blue-600">
                                {{ $product->name }}
                            </a>
                        </h3>

                        <!-- Price -->
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-lg font-bold text-gray-900">{{ $product->formatted_price }}</span>
                            @if($product->compare_price)
                                <span class="text-sm text-gray-500 line-through">{{ $product->formatted_compare_price }}</span>
                            @endif
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <button 
                                wire:click="addToCart({{ $product->id }})"
                                class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                {{ !$product->isInStock() ? 'disabled' : '' }}
                            >
                                {{ $product->isInStock() ? __('Add to Cart') : __('Out of Stock') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $products->links() }}
        </div>
    @else
        <!-- Empty Wishlist -->
        <div class="text-center py-16">
            <svg class="w-24 h-24 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
            <h2 class="text-2xl font-semibold text-gray-900 mb-2">{{ __('Your wishlist is empty') }}</h2>
            <p class="text-gray-600 mb-8">{{ __('Save your favorite products to buy them later.') }}</p>
            @if(!auth()->check())
                <p class="text-sm text-gray-500 mb-8">
                    <a href="/login" wire:navigate class="text-blue-600 hover:text-blue-700">{{ __('Login') }}</a>
                    {{ __('to sync your wishlist across devices') }}
                </p>
            @endif
            <a 
                href="/categories" 
                wire:navigate
                class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
            >
                {{ __('Browse Products') }}
            </a>
        </div>
    @endif
</div>