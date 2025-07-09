<?php

use Livewire\Volt\Component;
use App\Models\Order;

new class extends Component
{
    public ?Order $order = null;

    public function mount()
    {
        if (!auth()->check()) {
            $this->redirect('/login', navigate: true);
            return;
        }

        if (! $this->order) {
            return redirect('account/orders');
        }
    }

    public function cancelOrder()
    {
        if (!$this->order->canBeCancelled()) {
            $this->dispatch(
                'toast',
                type: 'error',
                message: __('This order cannot be cancelled')
            );
            return;
        }

        $this->order->updateStatus('cancelled', __('Cancelled by customer'), auth()->id());

        // Restore product stock
        foreach ($this->order->items as $item) {
            if ($item->variant) {
                $item->variant->increment('quantity', $item->quantity);
            } else {
                $item->product->incrementQuantity($item->quantity);
            }
        }

        $this->dispatch(
            'toast',
            type: 'success',
            message: __('Order cancelled successfully')
        );

        $this->redirect('/account/orders', navigate: true);
    }

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6">
                <!-- Back Button -->
                <a href="/account/orders" wire:navigate class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-2 rotate-180' : 'mr-2' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    {{ __('Back to Orders') }}
                </a>

                <!-- Order Info -->
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">{{ __('Order Number') }}</h3>
                        <p class="mt-1 text-sm text-gray-900">#{{ $order->order_number }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">{{ __('Order Date') }}</h3>
                        <p class="mt-1 text-sm text-gray-900">{{ $order->created_at->format('M d, Y') }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">{{ __('Order Status') }}</h3>
                        <p class="mt-1">
                            <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800">
                                {{ $order->status_label }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">{{ __('Payment Method') }}</h3>
                        <p class="mt-1 text-sm text-gray-900">
                            {{ $order->payment->payment_method === 'cod' ? __('Cash on Delivery') : __('Credit/Debit Card') }}
                        </p>
                    </div>

                    @if($order->canBeCancelled())
                    <button
                        wire:click="cancelOrder"
                        wire:confirm="{{ __('Are you sure you want to cancel this order?') }}"
                        class="w-full mt-4 px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                        {{ __('Cancel Order') }}
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Order Items') }}</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                        <div class="flex gap-4 pb-4 border-b last:border-b-0">
                            @if($item->product->primary_image_url)
                            <img
                                src="{{ $item->product->primary_image_url }}"
                                alt="{{ $item->product_details['name'] }}"
                                class="w-20 h-20 object-cover rounded">
                            @else
                            <div class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            @endif

                            <div class="flex-1">
                                <h4 class="font-semibold">{{ $item->product_details['name'] }}</h4>
                                @if($item->product_details['variant'] ?? null)
                                <p class="text-sm text-gray-600">{{ $item->product_details['variant'] }}</p>
                                @endif
                                @if($item->product_details['brand'] ?? null)
                                <p class="text-sm text-gray-500">{{ $item->product_details['brand'] }}</p>
                                @endif
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ __('SKU:') }} {{ $item->product_details['sku'] }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-sm text-gray-600">
                                    {{ __('Qty:') }} {{ $item->quantity }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    @php
                                    $currency = \App\Models\Currency::where('code', $order->currency_code)->first();
                                    @endphp
                                    {{ $currency->symbol }} {{ number_format($item->price, 2) }} {{ __('each') }}
                                </p>
                                <p class="font-semibold">
                                    {{ $currency->symbol }} {{ number_format($item->total, 2) }}
                                </p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Addresses -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Billing Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Billing Address') }}</h3>
                    @php
                    $billingAddress = $order->billingAddress();
                    @endphp
                    <address class="text-sm text-gray-600 not-italic">
                        <p class="font-semibold text-gray-900">{{ $billingAddress->name }}</p>
                        <p>{{ $billingAddress->address_line_1 }}</p>
                        @if($billingAddress->address_line_2)
                        <p>{{ $billingAddress->address_line_2 }}</p>
                        @endif
                        <p>{{ $billingAddress->city }}, {{ $billingAddress->country }}</p>
                        @if($billingAddress->postal_code)
                        <p>{{ $billingAddress->postal_code }}</p>
                        @endif
                        <p class="mt-2">{{ __('Phone:') }} {{ $billingAddress->phone }}</p>
                    </address>
                </div>

                <!-- Shipping Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Shipping Address') }}</h3>
                    @php
                    $shippingAddress = $order->shippingAddress();
                    @endphp
                    <address class="text-sm text-gray-600 not-italic">
                        <p class="font-semibold text-gray-900">{{ $shippingAddress->name }}</p>
                        <p>{{ $shippingAddress->address_line_1 }}</p>
                        @if($shippingAddress->address_line_2)
                        <p>{{ $shippingAddress->address_line_2 }}</p>
                        @endif
                        <p>{{ $shippingAddress->city }}, {{ $shippingAddress->country }}</p>
                        @if($shippingAddress->postal_code)
                        <p>{{ $shippingAddress->postal_code }}</p>
                        @endif
                        <p class="mt-2">{{ __('Phone:') }} {{ $shippingAddress->phone }}</p>
                    </address>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Order Summary') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-600">{{ __('Subtotal') }}</dt>
                            <dd class="text-gray-900">{{ $currency->symbol }} {{ number_format($order->subtotal, 2) }}</dd>
                        </div>

                        @if($order->discount_amount > 0)
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-600">
                                {{ __('Discount') }}
                                @if($order->coupon_code)
                                <span class="text-xs text-gray-500">({{ $order->coupon_code }})</span>
                                @endif
                            </dt>
                            <dd class="text-green-600">-{{ $currency->symbol }} {{ number_format($order->discount_amount, 2) }}</dd>
                        </div>
                        @endif

                        @if($order->tax_amount > 0)
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-600">{{ __('Tax') }}</dt>
                            <dd class="text-gray-900">{{ $currency->symbol }} {{ number_format($order->tax_amount, 2) }}</dd>
                        </div>
                        @endif

                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-600">
                                {{ __('Shipping') }}
                                @if($order->shippingMethod)
                                <span class="text-xs text-gray-500">({{ $order->shippingMethod->name }})</span>
                                @endif
                            </dt>
                            <dd class="text-gray-900">{{ $currency->symbol }} {{ number_format($order->shipping_amount, 2) }}</dd>
                        </div>

                        <div class="border-t pt-2">
                            <div class="flex justify-between">
                                <dt class="text-base font-semibold text-gray-900">{{ __('Total') }}</dt>
                                <dd class="text-base font-semibold text-gray-900">{{ $currency->symbol }} {{ number_format($order->total, 2) }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Order Timeline') }}</h3>
                </div>
                <div class="p-6">
                    <div class="flow-root">
                        <ul class="-mb-8">
                            @foreach($order->statusHistories as $index => $history)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last)
                                    <span class="absolute top-4 {{ app()->getLocale() === 'ar' ? 'right-4' : 'left-4' }} -ml-px h-full w-0.5 bg-gray-200"></span>
                                    @endif
                                    <div class="relative flex space-x-3 {{ app()->getLocale() === 'ar' ? 'space-x-reverse' : '' }}">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-{{ $loop->first ? 'blue' : 'gray' }}-500 flex items-center justify-center ring-8 ring-white">
                                                <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4 {{ app()->getLocale() === 'ar' ? 'space-x-reverse' : '' }}">
                                            <div>
                                                <p class="text-sm text-gray-900">
                                                    {{ ucfirst(str_replace('_', ' ', $history->status)) }}
                                                    @if($history->comment)
                                                    <span class="text-gray-500">- {{ $history->comment }}</span>
                                                    @endif
                                                </p>
                                                @if($history->creator)
                                                <p class="text-xs text-gray-500">{{ __('By') }} {{ $history->creator->name }}</p>
                                                @endif
                                            </div>
                                            <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                {{ $history->created_at->format('M d, Y h:i A') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>