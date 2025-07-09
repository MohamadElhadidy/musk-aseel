<?php

use Livewire\Volt\Component;
use App\Models\Order;

new class extends Component
{
    public ?Order $order = null;
    public string $orderNumber;

    public function mount($orderNumber)
    {
        $this->orderNumber = $orderNumber;
        
        if (auth()->check()) {
            $this->order = Order::where('order_number', $orderNumber)
                ->where('user_id', auth()->id())
                ->with(['items.product', 'addresses', 'shippingMethod'])
                ->first();
        }

        if (!$this->order) {
            $this->redirect('/', navigate: true);
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
    <div class="max-w-3xl mx-auto">
        <!-- Success Message -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Order Placed Successfully!') }}</h1>
            <p class="text-gray-600">{{ __('Thank you for your order. We\'ve sent a confirmation email to') }} {{ auth()->user()->email }}</p>
        </div>

        <!-- Order Details -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="border-b pb-4 mb-4">
                <h2 class="text-xl font-semibold mb-2">{{ __('Order Details') }}</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">{{ __('Order Number:') }}</span>
                        <p class="font-semibold">{{ $order->order_number }}</p>
                    </div>
                    <div>
                        <span class="text-gray-600">{{ __('Order Date:') }}</span>
                        <p class="font-semibold">{{ $order->created_at->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <span class="text-gray-600">{{ __('Payment Method:') }}</span>
                        <p class="font-semibold">
                            {{ $order->payment->payment_method === 'cod' ? __('Cash on Delivery') : __('Credit/Debit Card') }}
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-600">{{ __('Order Status:') }}</span>
                        <p class="font-semibold">
                            <span class="inline-block px-2 py-1 bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800 rounded text-xs">
                                {{ $order->status_label }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="mb-4">
                <h3 class="font-semibold mb-2">{{ __('Shipping Information') }}</h3>
                @php
                    $shippingAddress = $order->shippingAddress();
                @endphp
                <p class="text-sm text-gray-600">
                    {{ $shippingAddress->name }}<br>
                    {{ $shippingAddress->address_line_1 }}<br>
                    @if($shippingAddress->address_line_2)
                        {{ $shippingAddress->address_line_2 }}<br>
                    @endif
                    {{ $shippingAddress->city }}, {{ $shippingAddress->country }}<br>
                    @if($shippingAddress->postal_code)
                        {{ $shippingAddress->postal_code }}<br>
                    @endif
                    {{ __('Phone:') }} {{ $shippingAddress->phone }}
                </p>
            </div>

            <!-- Delivery Information -->
            @if($order->shippingMethod)
                <div class="mb-4">
                    <h3 class="font-semibold mb-2">{{ __('Delivery Information') }}</h3>
                    <p class="text-sm text-gray-600">
                        {{ $order->shippingMethod->name }}<br>
                        {{ __('Estimated delivery: :min-:max business days', [
                            'min' => $order->shippingMethod->min_days,
                            'max' => $order->shippingMethod->max_days
                        ]) }}
                    </p>
                </div>
            @endif
        </div>

        <!-- Order Items -->
        <div class="