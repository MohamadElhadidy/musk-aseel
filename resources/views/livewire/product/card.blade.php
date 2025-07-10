<?php

use Livewire\Volt\Component;
use App\Models\Cart;
use App\Models\Product;

new class extends Component
{
    public Product $product;
    public bool $isWishlisted = false;
    public bool $addingToCart = false;

    public function mount(Product $product)
    {
        $this->product = $product->load(['reviews' => function($q) {
            $q->approved();
        }]);
        
        if (auth()->check()) {
            $this->isWishlisted = auth()->user()->isWishlisted($product);
        } else {
            // Check guest wishlist in session
            $guestWishlist = session('wishlist', []);
            $this->isWishlisted = in_array($product->id, $guestWishlist);
        }
    }

    public function toggleWishlist()
    {
        if (auth()->check()) {
            // Authenticated user wishlist
            if ($this->isWishlisted) {
                auth()->user()->wishlist()->detach($this->product);
                $this->isWishlisted = false;
                $this->dispatch('toast', 
                    type: 'info',
                    message: __('Product removed from wishlist')
                );
            } else {
                auth()->user()->wishlist()->attach($this->product);
                $this->isWishlisted = true;
                $this->dispatch('toast', 
                    type: 'success',
                    message: __('Product added to wishlist')
                );
            }
        } else {
            // Guest wishlist using session
            $guestWishlist = session('wishlist', []);
            
            if ($this->isWishlisted) {
                $guestWishlist = array_filter($guestWishlist, function($id) {
                    return $id != $this->product->id;
                });
                session(['wishlist' => array_values($guestWishlist)]);
                $this->isWishlisted = false;
                $this->dispatch('toast', 
                    type: 'info',
                    message: __('Product removed from wishlist')
                );
            } else {
                $guestWishlist[] = $this->product->id;
                session(['wishlist' => $guestWishlist]);
                $this->isWishlisted = true;
                $this->dispatch('toast', 
                    type: 'success',
                    message: __('Product added to wishlist')
                );
            }
        }

        $this->dispatch('wishlist-updated');
    }

    public function addToCart()
    {
        $this->addingToCart = true;

        if (!$this->product->isInStock()) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Product is out of stock')
            );
            $this->addingToCart = false;
            return;
        }

        $cart = Cart::getCurrentCart();
        $cart->addItem($this->product);

        $this->dispatch('cart-updated');
        $this->dispatch('toast', 
            type: 'success',
            message: __('Product added to cart')
        );

        $this->addingToCart = false;
    }
}; ?>

<div class="group relative bg-white rounded-lg shadow-sm hover:shadow-lg transition-shadow">
    <!-- Wishlist Button -->
    <button 
        wire:click="toggleWishlist"
        class="absolute top-2 {{ app()->getLocale() === 'ar' ? 'left-2' : 'right-2' }} z-10 p-2 bg-white rounded-full shadow-md hover:shadow-lg transition"
    >
        <svg class="w-5 h-5 {{ $isWishlisted ? 'text-red-500 fill-current' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
        </svg>
    </button>

    <!-- Product Image -->
    <a href="/products/{{ $product->slug }}" wire:navigate class="block aspect-square overflow-hidden rounded-t-lg">
        @if($product->primary_image_url)
            <img 
                src="{{ $product->primary_image_url }}" 
                alt="{{ $product->name }}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            >
        @else
            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        @endif

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

        <!-- Rating -->
        @if($product->reviews_count > 0)
            <div class="flex items-center gap-1 mb-2">
                <div class="flex">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="w-4 h-4 {{ $i <= $product->average_rating ? 'text-yellow-400 fill-current' : 'text-gray-300' }}" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    @endfor
                </div>
                <span class="text-xs text-gray-500">({{ $product->reviews_count }})</span>
            </div>
        @endif

        <!-- Price -->
        <div class="flex items-center gap-2 mb-3">
            <span class="text-lg font-bold text-gray-900">{{ $product->formatted_price }}</span>
            @if($product->compare_price)
                <span class="text-sm text-gray-500 line-through">{{ $product->formatted_compare_price }}</span>
            @endif
        </div>

        <!-- Add to Cart Button -->
        <button 
            wire:click="addToCart"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-not-allowed"
            class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            {{ !$product->isInStock() ? 'disabled' : '' }}
        >
            <span wire:loading.remove wire:target="addToCart">
                {{ $product->isInStock() ? __('Add to Cart') : __('Out of Stock') }}
            </span>
            <span wire:loading wire:target="addToCart">
                <svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
        </button>
    </div>
</div>