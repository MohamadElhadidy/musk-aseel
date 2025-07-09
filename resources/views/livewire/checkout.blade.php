<?php

use Livewire\Volt\Component;
use App\Models\Cart;
use App\Models\Country;
use App\Models\City;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderAddress;
use App\Models\Payment;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public ?Cart $cart = null;
    
    // Billing Address
    public string $billing_name = '';
    public string $billing_phone = '';
    public string $billing_address_line_1 = '';
    public string $billing_address_line_2 = '';
    public ?int $billing_country_id = null;
    public ?int $billing_city_id = null;
    public string $billing_postal_code = '';
    
    // Shipping Address
    public bool $ship_to_different = false;
    public string $shipping_name = '';
    public string $shipping_phone = '';
    public string $shipping_address_line_1 = '';
    public string $shipping_address_line_2 = '';
    public ?int $shipping_country_id = null;
    public ?int $shipping_city_id = null;
    public string $shipping_postal_code = '';
    
    // Shipping & Payment
    public ?int $shipping_method_id = null;
    public string $payment_method = 'cod'; // cash on delivery
    public string $notes = '';
    
    // Data
    public $countries;
    public $billingCities = [];
    public $shippingCities = [];
    public $shippingMethods = [];
    public ?ShippingMethod $selectedShippingMethod = null;
    public $savedAddresses = [];

    public function mount()
    {
        if (!auth()->check()) {
            $this->redirect('/login', navigate: true);
            return;
        }

        $this->cart = Cart::getCurrentCart();
        
        if (!$this->cart || $this->cart->items->count() === 0) {
            $this->redirect('/cart', navigate: true);
            return;
        }

        if (!$this->cart->canCheckout()) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Some items in your cart are not available')
            );
            $this->redirect('/cart', navigate: true);
            return;
        }

        $this->countries = Country::active()->get();
        $this->savedAddresses = auth()->user()->addresses()->with('city.country')->get();
        
        // Pre-fill with default address
        $defaultAddress = auth()->user()->default_address;
        if ($defaultAddress) {
            $this->loadSavedAddress($defaultAddress->id);
        } else {
            // Pre-fill basic info
            $this->billing_name = auth()->user()->name;
            $this->billing_phone = auth()->user()->phone ?? '';
        }
    }

    public function loadSavedAddress($addressId)
    {
        $address = $this->savedAddresses->find($addressId);
        if (!$address) return;

        $this->billing_name = $address->name;
        $this->billing_phone = $address->phone;
        $this->billing_address_line_1 = $address->address_line_1;
        $this->billing_address_line_2 = $address->address_line_2 ?? '';
        $this->billing_country_id = $address->city->country_id;
        $this->billing_city_id = $address->city_id;
        $this->billing_postal_code = $address->postal_code ?? '';
        
        $this->updatedBillingCountryId();
    }

    public function updatedBillingCountryId()
    {
        $this->billingCities = City::where('country_id', $this->billing_country_id)
            ->active()
            ->get();
        $this->billing_city_id = null;
        $this->updateShippingMethods();
    }

    public function updatedShippingCountryId()
    {
        $this->shippingCities = City::where('country_id', $this->shipping_country_id)
            ->active()
            ->get();
        $this->shipping_city_id = null;
        $this->updateShippingMethods();
    }

    public function updatedBillingCityId()
    {
        $this->updateShippingMethods();
    }

    public function updatedShippingCityId()
    {
        $this->updateShippingMethods();
    }

    public function updatedShippingMethodId()
    {
        if ($this->shipping_method_id) {
            $this->selectedShippingMethod = ShippingMethod::find($this->shipping_method_id);
            $this->calculateShippingCost();
        }
    }

    private function updateShippingMethods()
    {
        $cityId = $this->ship_to_different ? 
            $this->shipping_city_id : 
            $this->billing_city_id;

        if (!$cityId) {
            $this->shippingMethods = [];
            $this->shipping_method_id = null;
            return;
        }

        // Find shipping zone for the city
        $zone = ShippingZone::whereHas('cities', function ($q) use ($cityId) {
            $q->where('cities.id', $cityId);
        })->first();

        if ($zone) {
            $this->shippingMethods = $zone->shippingMethods()
                ->where('shipping_methods.is_active', true)
                ->get();
        } else {
            // Get all shipping methods if no zone
            $this->shippingMethods = ShippingMethod::active()->get();
        }

        // Auto-select first method
        if ($this->shippingMethods->count() > 0 && !$this->shipping_method_id) {
            $this->shipping_method_id = $this->shippingMethods->first()->id;
            $this->updatedShippingMethodId();
        }
    }

    private function calculateShippingCost()
    {
        if (!$this->selectedShippingMethod) {
            $this->cart->update(['shipping_amount' => 0]);
            return;
        }

        $cityId = $this->ship_to_different ? 
            $this->shipping_city_id : 
            $this->billing_city_id;

        $zone = ShippingZone::whereHas('cities', function ($q) use ($cityId) {
            $q->where('cities.id', $cityId);
        })->first();

        $weight = 0; // Calculate from cart items if needed
        $subtotal = $this->cart->subtotal;
        
        $shippingCost = $this->selectedShippingMethod->calculateCost($weight, $subtotal, $zone);
        
        $this->cart->update(['shipping_amount' => $shippingCost]);
        $this->cart->calculateTotals();
        $this->cart->refresh();
    }

    public function placeOrder()
    {
        $this->validate([
            'billing_name' => 'required|string|max:255',
            'billing_phone' => 'required|string|max:20',
            'billing_address_line_1' => 'required|string|max:255',
            'billing_country_id' => 'required|exists:countries,id',
            'billing_city_id' => 'required|exists:cities,id',
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'payment_method' => 'required|in:cod,card',
        ]);

        if ($this->ship_to_different) {
            $this->validate([
                'shipping_name' => 'required|string|max:255',
                'shipping_phone' => 'required|string|max:20',
                'shipping_address_line_1' => 'required|string|max:255',
                'shipping_country_id' => 'required|exists:countries,id',
                'shipping_city_id' => 'required|exists:cities,id',
            ]);
        }

        DB::beginTransaction();
        
        try {
            // Create order
            $currency = session('currency') ? 
                \App\Models\Currency::find(session('currency')) : 
                \App\Models\Currency::getDefault();

            $order = Order::create([
                'user_id' => auth()->id(),
                'status' => 'pending',
                'subtotal' => $this->cart->subtotal,
                'discount_amount' => $this->cart->discount_amount,
                'tax_amount' => $this->cart->tax_amount,
                'shipping_amount' => $this->cart->shipping_amount,
                'total' => $this->cart->total,
                'currency_code' => $currency->code,
                'exchange_rate' => $currency->exchange_rate,
                'coupon_id' => $this->cart->coupon_id,
                'coupon_code' => $this->cart->coupon?->code,
                'shipping_method_id' => $this->shipping_method_id,
                'shipping_method_details' => [
                    'name' => $this->selectedShippingMethod->name,
                    'cost' => $this->cart->shipping_amount,
                ],
                'notes' => $this->notes,
            ]);

            // Create order items
            foreach ($this->cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_details' => [
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'brand' => $item->product->brand?->name,
                        'variant' => $item->variant?->attributes_string,
                    ],
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                ]);

                // Decrement product stock
                if ($item->variant) {
                    $item->variant->decrement('quantity', $item->quantity);
                } else {
                    $item->product->decrementQuantity($item->quantity);
                }
            }

            // Create billing address
            $billingCity = City::find($this->billing_city_id);
            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'billing',
                'name' => $this->billing_name,
                'phone' => $this->billing_phone,
                'address_line_1' => $this->billing_address_line_1,
                'address_line_2' => $this->billing_address_line_2,
                'city' => $billingCity->name,
                'country' => $billingCity->country->name,
                'postal_code' => $this->billing_postal_code,
            ]);

            // Create shipping address
            if ($this->ship_to_different) {
                $shippingCity = City::find($this->shipping_city_id);
                OrderAddress::create([
                    'order_id' => $order->id,
                    'type' => 'shipping',
                    'name' => $this->shipping_name,
                    'phone' => $this->shipping_phone,
                    'address_line_1' => $this->shipping_address_line_1,
                    'address_line_2' => $this->shipping_address_line_2,
                    'city' => $shippingCity->name,
                    'country' => $shippingCity->country->name,
                    'postal_code' => $this->shipping_postal_code,
                ]);
            } else {
                OrderAddress::create([
                    'order_id' => $order->id,
                    'type' => 'shipping',
                    'name' => $this->billing_name,
                    'phone' => $this->billing_phone,
                    'address_line_1' => $this->billing_address_line_1,
                    'address_line_2' => $this->billing_address_line_2,
                    'city' => $billingCity->name,
                    'country' => $billingCity->country->name,
                    'postal_code' => $this->billing_postal_code,
                ]);
            }

            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => $this->payment_method,
                'status' => 'pending',
                'amount' => $order->total,
                'currency_code' => $order->currency_code,
            ]);

            // Update coupon usage
            if ($this->cart->coupon) {
                $this->cart->coupon->increment('used_count');
                if (auth()->check()) {
                    $userCoupon = auth()->user()->coupons()
                        ->where('coupon_id', $this->cart->coupon_id)
                        ->first();
                    
                    if ($userCoupon) {
                        $userCoupon->pivot->increment('usage_count');
                    } else {
                        auth()->user()->coupons()->attach($this->cart->coupon_id, ['usage_count' => 1]);
                    }
                }
            }

            // Clear cart
            $this->cart->clear();

            DB::commit();

            // Redirect to order success page
            $this->redirect('/order-success/' . $order->order_number, navigate: true);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->dispatch('toast', 
                type: 'error',
                message: __('An error occurred while processing your order. Please try again.')
            );
        }
    }

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">{{ __('Checkout') }}</h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Checkout Form -->
        <div class="lg:col-span-2">
            <form wire:submit="placeOrder">
                <!-- Saved Addresses -->
                @if($savedAddresses->count() > 0)
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4">{{ __('Saved Addresses') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($savedAddresses as $address)
                                <div 
                                    wire:click="loadSavedAddress({{ $address->id }})"
                                    class="border rounded-lg p-4 cursor-pointer hover:border-blue-500 transition"
                                >
                                    <h4 class="font-semibold">{{ $address->name }}</h4>
                                    <p class="text-sm text-gray-600">{{ $address->phone }}</p>
                                    <p class="text-sm text-gray-600">{{ $address->full_address }}</p>
                                    @if($address->is_default)
                                        <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded mt-2">
                                            {{ __('Default') }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Billing Address -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4">{{ __('Billing Address') }}</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">{{ __('Full Name') }} <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                wire:model="billing_name"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('billing_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">{{ __('Phone') }} <span class="text-red-500">*</span></label>
                            <input 
                                type="tel" 
                                wire:model="billing_phone"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('billing_phone')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-gray-700 mb-2">{{ __('Address Line 1') }} <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                wire:model="billing_address_line_1"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('billing_address_line_1')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-gray-700 mb-2">{{ __('Address Line 2') }}</label>
                            <input 
                                type="text" 
                                wire:model="billing_address_line_2"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">{{ __('Country') }} <span class="text-red-500">*</span></label>
                            <select 
                                wire:model.live="billing_country_id"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                                <option value="">{{ __('Select Country') }}</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                            @error('billing_country_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">{{ __('City') }} <span class="text-red-500">*</span></label>
                            <select 
                                wire:model.live="billing_city_id"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                                <option value="">{{ __('Select City') }}</option>
                                @foreach($billingCities as $city)
                                    <option value="{{ $city->id }}">{{ $city->name }}</option>
                                @endforeach
                            </select>
                            @error('billing_city_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">{{ __('Postal Code') }}</label>
                            <input 
                                type="text" 
                                wire:model="billing_postal_code"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="flex items-center">
                            <input 
                                type="checkbox" 
                                wire:model.live="ship_to_different"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }}">
                                {{ __('Ship to a different address?') }}
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Shipping Address -->
                @if($ship_to_different)
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4">{{ __('Shipping Address') }}</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">{{ __('Full Name') }} <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    wire:model="shipping_name"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                @error('shipping_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2">{{ __('Phone') }} <span class="text-red-500">*</span></label>
                                <input 
                                    type="tel" 
                                    wire:model="shipping_phone"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                @error('shipping_phone')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">{{ __('Address Line 1') }} <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    wire:model="shipping_address_line_1"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                @error('shipping_address_line_1')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">{{ __('Address Line 2') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="shipping_address_line_2"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                >
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2">{{ __('Country') }} <span class="text-red-500">*</span></label>
                                <select 
                                    wire:model.live="shipping_country_id"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    <option value="">{{ __('Select Country') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                                @error('shipping_country_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2">{{ __('City') }} <span class="text-red-500">*</span></label>
                                <select 
                                    wire:model.live="shipping_city_id"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                    <option value="">{{ __('Select City') }}</option>
                                    @foreach($shippingCities as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                @error('shipping_city_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2">{{ __('Postal Code') }}</label>
                                <input 
                                    type="text" 
                                    wire:model="shipping_postal_code"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                >
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Shipping Method -->
                @if($shippingMethods && count($shippingMethods) > 0)
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4">{{ __('Shipping Method') }}</h2>
                        
                        <div class="space-y-3">
                            @foreach($shippingMethods as $method)
                                <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input 
                                        type="radio" 
                                        wire:model.live="shipping_method_id"
                                        value="{{ $method->id }}"
                                        class="mt-1"
                                    >
                                    <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }} flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-semibold">{{ $method->name }}</h4>
                                                <p class="text-sm text-gray-600">{{ $method->description }}</p>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    {{ __('Estimated delivery: :min-:max business days', [
                                                        'min' => $method->min_days,
                                                        'max' => $method->max_days
                                                    ]) }}
                                                </p>
                                            </div>
                                            <span class="font-semibold">
                                                @php
                                                    $currency = session('currency') ? 
                                                        \App\Models\Currency::find(session('currency')) : 
                                                        \App\Models\Currency::getDefault();
                                                @endphp
                                                {{ $currency->format($method->base_cost) }}
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('shipping_method_id')
                            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <!-- Payment Method -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4">{{ __('Payment Method') }}</h2>
                    
                    <div class="space-y-3">
                        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input 
                                type="radio" 
                                wire:model="payment_method"
                                value="cod"
                                class="text-blue-600"
                            >
                            <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }}">
                                <h4 class="font-semibold">{{ __('Cash on Delivery') }}</h4>
                                <p class="text-sm text-gray-600">{{ __('Pay when you receive your order') }}</p>
                            </div>
                        </label>

                        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input 
                                type="radio" 
                                wire:model="payment_method"
                                value="card"
                                class="text-blue-600"
                            >
                            <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }}">
                                <h4 class="font-semibold">{{ __('Credit/Debit Card') }}</h4>
                                <p class="text-sm text-gray-600">{{ __('Pay securely with your card') }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Order Notes -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4">{{ __('Order Notes') }} ({{ __('Optional') }})</h2>
                    <textarea 
                        wire:model="notes"
                        rows="3"
                        placeholder="{{ __('Notes about your order, e.g. special notes for delivery') }}"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                    ></textarea>
                </div>
            </form>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                <h2 class="text-xl font-semibold mb-4">{{ __('Order Summary') }}</h2>

                <!-- Cart Items -->
                <div class="space-y-3 mb-6">
                    @foreach($cart->items as $item)
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <h4 class="font-medium text-sm">{{ $item->product->name }}</h4>
                                @if($item->variant)
                                    <p class="text-xs text-gray-600">{{ $item->variant->attributes_string }}</p>
                                @endif
                                <p class="text-xs text-gray-500">{{ __('Qty') }}: {{ $item->quantity }}</p>
                            </div>
                            <div class="text-right">
                                @php
                                    $currency = session('currency') ? 
                                        \App\Models\Currency::find(session('currency')) : 
                                        \App\Models\Currency::getDefault();
                                @endphp
                                <p class="font-medium text-sm">{{ $currency->format($item->total) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Totals -->
                <div class="space-y-2 border-t pt-4">
                    <div class="flex justify-between text-sm">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ $currency->format($cart->subtotal) }}</span>
                    </div>

                    @if($cart->discount_amount > 0)
                        <div class="flex justify-between text-sm text-green-600">
                            <span>{{ __('Discount') }}</span>
                            <span>-{{ $currency->format($cart->discount_amount) }}</span>
                        </div>
                    @endif

                    @if($cart->tax_amount > 0)
                        <div class="flex justify-between text-sm">
                            <span>{{ __('Tax') }}</span>
                            <span>{{ $currency->format($cart->tax_amount) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between text-sm">
                        <span>{{ __('Shipping') }}</span>
                        <span>{{ $currency->format($cart->shipping_amount) }}</span>
                    </div>

                    <div class="border-t pt-2 mt-2">
                        <div class="flex justify-between text-lg font-semibold">
                            <span>{{ __('Total') }}</span>
                            <span>{{ $currency->format($cart->total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Place Order Button -->
                <button 
                    wire:click="placeOrder"
                    wire:loading.attr="disabled"
                    class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition mt-6 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove>{{ __('Place Order') }}</span>
                    <span wire:loading>{{ __('Processing...') }}</span>
                </button>

                <!-- Security Info -->
                <div class="mt-4 text-xs text-gray-500 text-center">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    {{ __('Your payment information is secure') }}
                </div>
            </div>
        </div>
    </div>
</div>