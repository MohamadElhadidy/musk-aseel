<?php

use Livewire\Volt\Component;
use App\Models\Cart;
use App\Models\Coupon;

new class extends Component
{
    public ?Cart $cart = null;
    public string $couponCode = '';
    public bool $showCouponForm = false;

    public function mount()
    {
        $this->cart = Cart::getCurrentCart();
        $this->cart->load(['items.product', 'items.variant', 'coupon']);
    }

    public function updateQuantity($itemId, $quantity)
    {
        $item = $this->cart->items()->find($itemId);
        
        if (!$item) {
            return;
        }

        // Check stock availability
        $maxQuantity = $item->variant 
            ? $item->variant->quantity 
            : $item->product->getAvailableQuantity();

        if ($quantity > $maxQuantity) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Only :max items available', ['max' => $maxQuantity])
            );
            return;
        }

        $this->cart->updateItemQuantity($item, $quantity);
        $this->cart->refresh();
        
        $this->dispatch('cart-updated');
        $this->dispatch('toast', 
            type: 'success',
            message: __('Cart updated')
        );
    }

    public function removeItem($itemId)
    {
        $item = $this->cart->items()->find($itemId);
        
        if ($item) {
            $this->cart->removeItem($item);
            $this->cart->refresh();
            
            $this->dispatch('cart-updated');
            $this->dispatch('toast', 
                type: 'info',
                message: __('Item removed from cart')
            );
        }
    }

    public function applyCoupon()
    {
        $this->validate([
            'couponCode' => 'required|string',
        ]);

        $coupon = Coupon::where('code', $this->couponCode)->first();

        if (!$coupon) {
            $this->addError('couponCode', __('Invalid coupon code'));
            return;
        }

        if (!$coupon->isValid()) {
            $this->addError('couponCode', __('This coupon has expired'));
            return;
        }

        if ($coupon->minimum_amount && $this->cart->subtotal < $coupon->minimum_amount) {
            $this->addError('couponCode', __('Minimum order amount is :amount', ['amount' => $coupon->minimum_amount]));
            return;
        }

        if (auth()->check() && !$coupon->canBeUsedBy(auth()->user())) {
            $this->addError('couponCode', __('You have already used this coupon'));
            return;
        }

        if ($this->cart->applyCoupon($coupon)) {
            $this->cart->refresh();
            $this->couponCode = '';
            $this->showCouponForm = false;
            
            $this->dispatch('toast', 
                type: 'success',
                message: __('Coupon applied successfully')
            );
        }
    }

    public function removeCoupon()
    {
        $this->cart->removeCoupon();
        $this->cart->refresh();
        
        $this->dispatch('toast', 
            type: 'info',
            message: __('Coupon removed')
        );
    }

    public function clearCart()
    {
        $this->cart->clear();
        
        $this->dispatch('cart-updated');
        $this->dispatch('toast', 
            type: 'info',
            message: __('Cart cleared')
        );
    }

    public function proceedToCheckout()
    {
        if (!$this->cart->canCheckout()) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Please check item availability')
            );
            return;
        }

        $this->redirect('/checkout', navigate: true);
    }

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">{{ __('Shopping Cart') }}</h1>

    @if($cart->items->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">{{ __('Cart Items') }} ({{ $cart->items_count }})</h2>
                            <button 
                                wire:click="clearCart"
                                wire:confirm="{{ __('Are you sure you want to clear your cart?') }}"
                                class="text-red-600 hover:text-red-700 text-sm"
                            >
                                {{ __('Clear Cart') }}
                            </button>
                        </div>

                        <div class="space-y-4">
                            @foreach($cart->items as $item)
                                <div class="flex gap-4 pb-4 border-b last:border-b-0" wire:key="item-{{ $item->id }}">
                                    <!-- Product Image -->
                                    <a href="/products/{{ $item->product->slug }}" wire:navigate class="flex-shrink-0">
                                        @if($item->product->primary_image_url)
                                            <img 
                                                src="{{ $item->product->primary_image_url }}" 
                                                alt="{{ $item->product->name }}"
                                                class="w-24 h-24 object-cover rounded"
                                            >
                                        @else
                                            <div class="w-24 h-24 bg-gray-200 rounded flex items-center justify-center">
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        @endif
                                    </a>

                                    <!-- Product Details -->
                                    <div class="flex-1">
                                        <h3 class="font-semibold mb-1">
                                            <a href="/products/{{ $item->product->slug }}" wire:navigate class="hover:text-blue-600">
                                                {{ $item->product->name }}
                                            </a>
                                        </h3>
                                        
                                        @if($item->variant)
                                            <p class="text-sm text-gray-600 mb-1">
                                                {{ $item->variant->attributes_string }}
                                            </p>
                                        @endif

                                        @if($item->product->brand)
                                            <p class="text-sm text-gray-500 mb-2">{{ $item->product->brand->name }}</p>
                                        @endif

                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <!-- Quantity Selector -->
                                                <div class="flex items-center border rounded">
                                                    <button 
                                                        wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})"
                                                        class="p-1 hover:bg-gray-100"
                                                        {{ $item->quantity <= 1 ? 'disabled' : '' }}
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                                        </svg>
                                                    </button>
                                                    <span class="px-3 py-1">{{ $item->quantity }}</span>
                                                    <button 
                                                        wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                                        class="p-1 hover:bg-gray-100"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <!-- Remove Button -->
                                                <button 
                                                    wire:click="removeItem({{ $item->id }})"
                                                    class="text-red-600 hover:text-red-700"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>

                                            <!-- Price -->
                                            <div class="text-right">
                                                <p class="font-semibold">
                                                    @php
                                                        $currency = session('currency') ? 
                                                            \App\Models\Currency::find(session('currency')) : 
                                                            \App\Models\Currency::getDefault();
                                                    @endphp
                                                    {{ $currency->format($item->total) }}
                                                </p>
                                                @if($item->quantity > 1)
                                                    <p class="text-sm text-gray-500">
                                                        {{ $currency->format($item->price) }} {{ __('each') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Order Summary') }}</h2>

                    <!-- Coupon -->
                    @if($cart->coupon)
                        <div class="flex justify-between items-center mb-4 p-3 bg-green-50 rounded">
                            <div>
                                <p class="font-medium text-green-800">{{ $cart->coupon->code }}</p>
                                <p class="text-sm text-green-600">{{ $cart->coupon->description }}</p>
                            </div>
                            <button 
                                wire:click="removeCoupon"
                                class="text-red-600 hover:text-red-700"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @else
                        @if($showCouponForm)
                            <form wire:submit="applyCoupon" class="mb-4">
                                <div class="flex gap-2">
                                    <input 
                                        type="text" 
                                        wire:model="couponCode"
                                        placeholder="{{ __('Enter coupon code') }}"
                                        class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    >
                                    <button 
                                        type="submit"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                    >
                                        {{ __('Apply') }}
                                    </button>
                                </div>
                                @error('couponCode')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </form>
                        @else
                            <button 
                                wire:click="$set('showCouponForm', true)"
                                class="text-blue-600 hover:text-blue-700 text-sm mb-4"
                            >
                                {{ __('Have a coupon code?') }}
                            </button>
                        @endif
                    @endif

                    <!-- Totals -->
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('Subtotal') }}</span>
                            <span>
                                @php
                                    $currency = session('currency') ? 
                                        \App\Models\Currency::find(session('currency')) : 
                                        \App\Models\Currency::getDefault();
                                @endphp
                                {{ $currency->format($cart->subtotal) }}
                            </span>
                        </div>

                        @if($cart->discount_amount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>{{ __('Discount') }}</span>
                                <span>-{{ $currency->format($cart->discount_amount) }}</span>
                            </div>
                        @endif

                        @if($cart->tax_amount > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('Tax') }}</span>
                                <span>{{ $currency->format($cart->tax_amount) }}</span>
                            </div>
                        @endif

                        <div class="border-t pt-3">
                            <div class="flex justify-between text-lg font-semibold">
                                <span>{{ __('Total') }}</span>
                                <span>{{ $currency->format($cart->total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Button -->
                    <button 
                        wire:click="proceedToCheckout"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
                    >
                        {{ __('Proceed to Checkout') }}
                    </button>

                    <!-- Continue Shopping -->
                    <a 
                        href="/categories" 
                        wire:navigate
                        class="block text-center text-blue-600 hover:text-blue-700 mt-4"
                    >
                        {{ __('Continue Shopping') }}
                    </a>
                </div>
            </div>
        </div>
    @else
        <!-- Empty Cart -->
        <div class="text-center py-16">
            <svg class="w-24 h-24 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h2 class="text-2xl font-semibold text-gray-900 mb-2">{{ __('Your cart is empty') }}</h2>
            <p class="text-gray-600 mb-8">{{ __('Looks like you haven\'t added any items to your cart yet.') }}</p>
            <a 
                href="/categories" 
                wire:navigate
                class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
            >
                {{ __('Start Shopping') }}
            </a>
        </div>
    @endif
</div>