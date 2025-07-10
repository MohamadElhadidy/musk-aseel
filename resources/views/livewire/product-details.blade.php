<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Review;

new class extends Component
{
    public Product $product;
    public int $quantity = 1;
    public ?int $selectedVariantId = null;
    public bool $isWishlisted = false;
    public string $activeTab = 'description';
    public array $relatedProducts = [];
    
    // Review form
    public int $rating = 5;
    public string $comment = '';
    public bool $showReviewForm = false;

    public function mount($slug)
    {
        $this->product = Product::where('slug', $slug)
            ->active()
            ->with(['images', 'variants', 'brand', 'categories', 'reviews' => function($q) {
                $q->approved()->with('user')->latest();
            }])
            ->firstOrFail();

        // Increment views
        $this->product->incrementViews();

        // Check if wishlisted
        if (auth()->check()) {
            $this->isWishlisted = auth()->user()->isWishlisted($this->product);
        } else {
            // Check guest wishlist in session
            $guestWishlist = session('wishlist', []);
            $this->isWishlisted = in_array($this->product->id, $guestWishlist);
        }

        // Load related products
        // $this->loadRelatedProducts();

        // Select first variant if exists
        if ($this->product->hasVariants()) {
            $this->selectedVariantId = $this->product->variants->first()->id;
        }
    }

    public function loadRelatedProducts()
    {
        $categoryIds = $this->product->categories->pluck('id');
        
        $this->relatedProducts = Product::active()
            ->where('id', '!=', $this->product->id)
            ->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            })
            ->with(['images', 'translations'])
            ->take(4)
            ->get()
            ->toArray();
    }

    public function incrementQuantity()
    {
        $maxQuantity = $this->getMaxQuantity();
        if ($this->quantity < $maxQuantity) {
            $this->quantity++;
        }
    }

    public function decrementQuantity()
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    public function getMaxQuantity()
    {
        if ($this->selectedVariantId) {
            $variant = $this->product->variants->find($this->selectedVariantId);
            return $variant ? $variant->quantity : 0;
        }
        
        return $this->product->getAvailableQuantity();
    }

    public function getCurrentPrice()
    {
        if ($this->selectedVariantId) {
            $variant = $this->product->variants->find($this->selectedVariantId);
            return $variant ? $variant->price : $this->product->price;
        }
        
        return $this->product->price;
    }

    public function toggleWishlist()
    {
        if (!auth()->check()) {
            $this->redirect('/login', navigate: true);
            return;
        }

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

        $this->dispatch('wishlist-updated');
    }

    public function addToCart()
    {
        if (!$this->product->isInStock()) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Product is out of stock')
            );
            return;
        }

        if ($this->quantity > $this->getMaxQuantity()) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Requested quantity not available')
            );
            return;
        }

        $cart = Cart::getCurrentCart();
        $variant = $this->selectedVariantId ? 
            $this->product->variants->find($this->selectedVariantId) : 
            null;

        $cart->addItem($this->product, $this->quantity, $variant);

        $this->dispatch('cart-updated');
        $this->dispatch('toast', 
            type: 'success',
            message: __('Product added to cart')
        );
    }

    public function submitReview()
    {
        if (!auth()->check()) {
            $this->redirect('/login', navigate: true);
            return;
        }

        if (!auth()->user()->canReviewProduct($this->product)) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('You can only review products you have purchased')
            );
            return;
        }

        $this->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:500',
        ]);

        $review = Review::create([
            'product_id' => $this->product->id,
            'user_id' => auth()->id(),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'is_approved' => false, // Admin approval required
        ]);

        // Refresh the product to get updated reviews
        $this->product->load(['reviews' => function($q) {
            $q->approved()->with('user')->latest();
        }]);

        $this->reset(['rating', 'comment', 'showReviewForm']);
        $this->dispatch('toast', 
            type: 'success',
            message: __('Thank you for your review! It will be published after approval.')
        );
    }

    public function with()
    {
        return [
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
                    @foreach($product->categories as $category)
                        <li>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-gray-400 {{ app()->getLocale() === 'ar' ? 'transform rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <a href="/categories/{{ $category->slug }}" wire:navigate class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }} text-gray-700 hover:text-blue-600">
                                    {{ $category->name }}
                                </a>
                            </div>
                        </li>
                    @endforeach
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400 {{ app()->getLocale() === 'ar' ? 'transform rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }} text-gray-500">
                                {{ $product->name }}
                            </span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Product Images -->
            <div x-data="{ activeImage: 0 }">
                <!-- Main Image -->
                <div class="relative mb-4">
                    @foreach($product->images as $index => $image)
                        <img 
                            x-show="activeImage === {{ $index }}"
                            src="{{ asset('storage/' . $image->image) }}" 
                            alt="{{ $product->name }}"
                            class="w-full rounded-lg"
                        >
                    @endforeach

                    @if($product->discount_percentage)
                        <div class="absolute top-4 {{ app()->getLocale() === 'ar' ? 'right-4' : 'left-4' }} bg-red-500 text-white px-3 py-1 rounded-lg font-semibold">
                            -{{ $product->discount_percentage }}%
                        </div>
                    @endif
                </div>

                <!-- Thumbnail Images -->
                @if($product->images->count() > 1)
                    <div class="grid grid-cols-4 gap-2">
                        @foreach($product->images as $index => $image)
                            <button 
                                @click="activeImage = {{ $index }}"
                                :class="activeImage === {{ $index }} ? 'ring-2 ring-blue-500' : ''"
                                class="rounded-lg overflow-hidden"
                            >
                                <img 
                                    src="{{ asset('storage/' . $image->image) }}" 
                                    alt="{{ $product->name }}"
                                    class="w-full h-20 object-cover"
                                >
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Product Info -->
            <div>
                <!-- Brand -->
                @if($product->brand)
                    <p class="text-blue-600 mb-2">{{ $product->brand->name }}</p>
                @endif

                <!-- Product Name -->
                <h1 class="text-3xl font-bold mb-4">{{ $product->name }}</h1>

                <!-- Rating -->
                @if($product->reviews_count > 0)
                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-5 h-5 {{ $i <= $product->average_rating ? 'text-yellow-400 fill-current' : 'text-gray-300' }}" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            @endfor
                        </div>
                        <span class="text-gray-600">
                            {{ $product->average_rating }} ({{ $product->reviews_count }} {{ __('reviews') }})
                        </span>
                    </div>
                @endif

                <!-- Price -->
                <div class="mb-6">
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-bold text-gray-900">
                            @php
                                $currency = app('currency');
                            @endphp
                            {{ $currency->format($this->getCurrentPrice()) }}
                        </span>
                        @if($product->compare_price)
                            <span class="text-xl text-gray-500 line-through">
                                {{ $product->formatted_compare_price }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Short Description -->
                @if($product->short_description)
                    <p class="text-gray-600 mb-6">{{ $product->short_description }}</p>
                @endif

                <!-- Variants -->
                @if($product->hasVariants())
                    <div class="mb-6">
                        <h3 class="font-semibold mb-3">{{ __('Options') }}</h3>
                        <div class="space-y-3">
                            @foreach($product->variants as $variant)
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input 
                                        type="radio" 
                                        wire:model.live="selectedVariantId"
                                        value="{{ $variant->id }}"
                                        class="text-blue-600"
                                    >
                                    <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }} flex-1">
                                        <div class="flex items-center justify-between">
                                            <span>
                                                @foreach($variant->attributes as $key => $value)
                                                    {{ ucfirst($key) }}: {{ $value }}
                                                    @if(!$loop->last), @endif
                                                @endforeach
                                            </span>
                                            <span class="font-semibold">
                                                @php
                                                    $currency = app('currency');
                                                @endphp
                                                {{ $currency->format($variant->price) }}
                                            </span>
                                        </div>
                                        @if(!$variant->quantity)
                                            <span class="text-red-600 text-sm">{{ __('Out of stock') }}</span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Quantity & Add to Cart -->
                <div class="flex items-center gap-4 mb-6">
                    <div class="flex items-center border rounded-lg">
                        <button 
                            wire:click="decrementQuantity"
                            class="p-3 hover:bg-gray-100"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                            </svg>
                        </button>
                        <input 
                            type="number" 
                            wire:model="quantity"
                            min="1"
                            max="{{ $this->getMaxQuantity() }}"
                            class="w-16 text-center border-0 focus:ring-0"
                            readonly
                        >
                        <button 
                            wire:click="incrementQuantity"
                            class="p-3 hover:bg-gray-100"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                    </div>

                    <button 
                        wire:click="addToCart"
                        wire:loading.attr="disabled"
                        class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        {{ !$product->isInStock() ? 'disabled' : '' }}
                    >
                        {{ $product->isInStock() ? __('Add to Cart') : __('Out of Stock') }}
                    </button>

                    <button 
                        wire:click="toggleWishlist"
                        class="p-3 border rounded-lg hover:bg-gray-50"
                    >
                        <svg class="w-6 h-6 {{ $isWishlisted ? 'text-red-500 fill-current' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </button>
                </div>

                <!-- Stock Status -->
                <div class="flex items-center gap-2 mb-6">
                    @if($product->isInStock())
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-green-500">{{ __('In Stock') }}</span>
                        @if($product->track_quantity)
                            <span class="text-gray-500">({{ $this->getMaxQuantity() }} {{ __('available') }})</span>
                        @endif
                    @else
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span class="text-red-500">{{ __('Out of Stock') }}</span>
                    @endif
                </div>

                <!-- Product Info -->
                <div class="border-t pt-6">
                    <dl class="space-y-2">
                        <div class="flex">
                            <dt class="w-24 text-gray-600">{{ __('SKU:') }}</dt>
                            <dd class="text-gray-900">{{ $product->sku }}</dd>
                        </div>
                        @if($product->categories->count() > 0)
                            <div class="flex">
                                <dt class="w-24 text-gray-600">{{ __('Category:') }}</dt>
                                <dd>
                                    @foreach($product->categories as $category)
                                        <a href="/categories/{{ $category->slug }}" wire:navigate class="text-blue-600 hover:underline">
                                            {{ $category->name }}
                                        </a>
                                        @if(!$loop->last), @endif
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                        @if($product->tags->count() > 0)
                            <div class="flex">
                                <dt class="w-24 text-gray-600">{{ __('Tags:') }}</dt>
                                <dd>
                                    @foreach($product->tags as $tag)
                                        <span class="inline-block bg-gray-100 rounded-full px-3 py-1 text-sm text-gray-700 {{ app()->getLocale() === 'ar' ? 'ml-2' : 'mr-2' }} mb-2">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-12">
            <div class="border-b">
                <nav class="flex gap-8">
                    <button 
                        wire:click="$set('activeTab', 'description')"
                        class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'description' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    >
                        {{ __('Description') }}
                    </button>
                    <button 
                        wire:click="$set('activeTab', 'reviews')"
                        class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'reviews' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    >
                        {{ __('Reviews') }} ({{ $product->reviews_count }})
                    </button>
                </nav>
            </div>

            <div class="py-8">
                <!-- Description Tab -->
                @if($activeTab === 'description')
                    <div class="prose max-w-none">
                        {!! nl2br(e($product->description)) !!}
                    </div>
                @endif

                <!-- Reviews Tab -->
                @if($activeTab === 'reviews')
                    <div>
                        @auth
                            @if(auth()->user()->canReviewProduct($product))
                                <button 
                                    wire:click="$set('showReviewForm', true)"
                                    class="mb-6 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                                >
                                    {{ __('Write a Review') }}
                                </button>
                            @endif
                        @endauth

                        <!-- Review Form -->
                        @if($showReviewForm)
                            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                                <h3 class="font-semibold mb-4">{{ __('Write Your Review') }}</h3>
                                <form wire:submit="submitReview">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">{{ __('Rating') }}</label>
                                        <div class="flex gap-2">
                                            @for($i = 1; $i <= 5; $i++)
                                                <button 
                                                    type="button"
                                                    wire:click="$set('rating', {{ $i }})"
                                                    class="text-2xl {{ $i <= $rating ? 'text-yellow-400' : 'text-gray-300' }}"
                                                >
                                                    ★
                                                </button>
                                            @endfor
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">{{ __('Your Review') }}</label>
                                        <textarea 
                                            wire:model="comment"
                                            rows="4"
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                            required
                                        ></textarea>
                                        @error('comment')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex gap-2">
                                        <button 
                                            type="submit"
                                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                                        >
                                            {{ __('Submit Review') }}
                                        </button>
                                        <button 
                                            type="button"
                                            wire:click="$set('showReviewForm', false)"
                                            class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400"
                                        >
                                            {{ __('Cancel') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        <!-- Reviews List -->
                        @php
                            $approvedReviews = $product->reviews()->approved()->with('user')->latest()->get();
                        @endphp
                        @forelse($approvedReviews as $review)
                            <div class="border-b pb-6 mb-6">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <h4 class="font-semibold">{{ $review->user->name }}</h4>
                                        <div class="flex items-center gap-2">
                                            <div class="flex">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-yellow-400 fill-current' : 'text-gray-300' }}" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    </svg>
                                                @endfor
                                            </div>
                                            <span class="text-sm text-gray-500">{{ $review->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-gray-700">{{ $review->comment }}</p>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-8">{{ __('No reviews yet. Be the first to review this product!') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        <!-- Related Products -->
        @if(count($relatedProducts) > 0)
            <div class="mt-12">
                <h2 class="text-2xl font-bold mb-6">{{ __('Related Products') }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    @foreach($relatedProducts as $relatedProduct)
                        <livewire:product.card :product="$relatedProduct" :key="'related-'.$relatedProduct['id']" />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>