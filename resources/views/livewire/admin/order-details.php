<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\OrderStatusHistory;

new class extends Component
{
    public ?Order $order = null;
    public $orderId;
    
    // Status update
    public string $newStatus = '';
    public string $statusComment = '';
    public bool $showStatusModal = false;
    
    // Tracking info
    public string $trackingNumber = '';
    public string $trackingCarrier = '';
    public bool $showTrackingModal = false;

    public function mount($orderId)
    {
        $this->orderId = $orderId;
        $this->order = Order::with([
            'user',
            'items.product',
            'addresses',
            'payment',
            'shippingMethod',
            'statusHistories.creator',
            'coupon'
        ])->findOrFail($orderId);
    }

    public function updateStatus()
    {
        $this->validate([
            'newStatus' => 'required|in:pending,processing,shipped,delivered,cancelled,refunded',
            'statusComment' => 'nullable|string|max:500'
        ]);

        $this->order->updateStatus($this->newStatus, $this->statusComment, auth()->id());

        // Handle stock restoration for cancelled orders
        if ($this->newStatus === 'cancelled' && $this->order->status !== 'cancelled') {
            foreach ($this->order->items as $item) {
                if ($item->product_variant_id) {
                    $item->variant->increment('quantity', $item->quantity);
                } else {
                    $item->product->incrementQuantity($item->quantity);
                }
            }
        }

        // Update payment status
        if (in_array($this->newStatus, ['delivered', 'completed'])) {
            $this->order->payment->update(['status' => 'completed']);
        } elseif ($this->newStatus === 'cancelled') {
            $this->order->payment->update(['status' => 'cancelled']);
        } elseif ($this->newStatus === 'refunded') {
            $this->order->payment->update(['status' => 'refunded']);
        }

        $this->order->refresh();
        $this->showStatusModal = false;
        $this->reset(['newStatus', 'statusComment']);

        $this->dispatch('toast', 
            type: 'success',
            message: __('Order status updated successfully')
        );
    }

    public function updateTracking()
    {
        $this->validate([
            'trackingNumber' => 'required|string|max:255',
            'trackingCarrier' => 'required|string|max:255'
        ]);

        $this->order->update([
            'tracking_number' => $this->trackingNumber,
            'tracking_carrier' => $this->trackingCarrier
        ]);

        $this->showTrackingModal = false;

        $this->dispatch('toast', 
            type: 'success',
            message: __('Tracking information updated')
        );
    }

    public function printInvoice()
    {
        return $this->redirect('/admin/orders/' . $this->order->id . '/invoice', navigate: true);
    }

    public function printPackingSlip()
    {
        return $this->redirect('/admin/orders/' . $this->order->id . '/packing-slip', navigate: true);
    }

    public function refundOrder()
    {
        if (!in_array($this->order->status, ['delivered', 'completed'])) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Only delivered orders can be refunded')
            );
            return;
        }

        $this->showStatusModal = true;
        $this->newStatus = 'refunded';
    }

    public function with()
    {
        return [
            'layout' => 'admin.layout',
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('Order Details') }}</h1>
                <p class="text-gray-600">{{ __('Order #:number', ['number' => $order->order_number]) }}</p>
            </div>
            <div class="flex gap-2">
                <button 
                    wire:click="printInvoice"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                >
                    {{ __('Print Invoice') }}
                </button>
                <button 
                    wire:click="printPackingSlip"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                >
                    {{ __('Packing Slip') }}
                </button>
                <a 
                    href="/admin/orders" 
                    wire:navigate
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                >
                    {{ __('Back to Orders') }}
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h2 class="text-lg font-semibold">{{ __('Order Items') }}</h2>
                </div>
                <div class="p-6">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left pb-2">{{ __('Product') }}</th>
                                <th class="text-center pb-2">{{ __('Quantity') }}</th>
                                <th class="text-right pb-2">{{ __('Price') }}</th>
                                <th class="text-right pb-2">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="border-b">
                                    <td class="py-4">
                                        <div class="flex items-center">
                                            @if($item->product->primary_image_url)
                                                <img 
                                                    src="{{ $item->product->primary_image_url }}" 
                                                    alt="{{ $item->product_details['name'] }}"
                                                    class="w-12 h-12 object-cover rounded mr-4"
                                                >
                                            @endif
                                            <div>
                                                <p class="font-medium">{{ $item->product_details['name'] }}</p>
                                                <p class="text-sm text-gray-600">{{ $item->product_details['sku'] }}</p>
                                                @if($item->product_details['variant'] ?? null)
                                                    <p class="text-sm text-gray-500">{{ $item->product_details['variant'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-right">
                                        @php
                                            $currency = \App\Models\Currency::where('code', $order->currency_code)->first();
                                        @endphp
                                        {{ $currency->symbol }} {{ number_format($item->price, 2) }}
                                    </td>
                                    <td class="text-right font-medium">
                                        {{ $currency->symbol }} {{ number_format($item->total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h2 class="text-lg font-semibold">{{ __('Customer Information') }}</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-medium mb-2">{{ __('Contact Details') }}</h3>
                            <p class="text-gray-600">{{ $order->user->name }}</p>
                            <p class="text-gray-600">{{ $order->user->email }}</p>
                            <p class="text-gray-600">{{ $order->user->phone }}</p>
                        </div>
                        <div>
                            <h3 class="font-medium mb-2">{{ __('Customer Stats') }}</h3>
                            <p class="text-gray-600">{{ __('Total Orders:') }} {{ $order->user->orders_count }}</p>
                            <p class="text-gray-600">{{ __('Total Spent:') }} {{ $currency->format($order->user->total_spent) }}</p>
                            <p class="text-gray-600">{{ __('Member Since:') }} {{ $order->user->created_at->format('M Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Addresses -->
            <div class="grid grid-cols-2 gap-6">
                <!-- Billing Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">{{ __('Billing Address') }}</h3>
                    @php $billing = $order->billingAddress(); @endphp
                    <address class="text-gray-600 not-italic">
                        <p class="font-medium text-gray-900">{{ $billing->name }}</p>
                        <p>{{ $billing->phone }}</p>
                        <p>{{ $billing->address_line_1 }}</p>
                        @if($billing->address_line_2)
                            <p>{{ $billing->address_line_2 }}</p>
                        @endif
                        <p>{{ $billing->city }}, {{ $billing->country }}</p>
                        @if($billing->postal_code)
                            <p>{{ $billing->postal_code }}</p>
                        @endif
                    </address>
                </div>

                <!-- Shipping Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">{{ __('Shipping Address') }}</h3>
                    @php $shipping = $order->shippingAddress(); @endphp
                    <address class="text-gray-600 not-italic">
                        <p class="font-medium text-gray-900">{{ $shipping->name }}</p>
                        <p>{{ $shipping->phone }}</p>
                        <p>{{ $shipping->address_line_1 }}</p>
                        @if($shipping->address_line_2)
                            <p>{{ $shipping->address_line_2 }}</p>
                        @endif
                        <p>{{ $shipping->city }}, {{ $shipping->country }}</p>
                        @if($shipping->postal_code)
                            <p>{{ $shipping->postal_code }}</p>
                        @endif
                    </address>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Order Status -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-4">{{ __('Order Status') }}</h3>
                <div class="mb-4">
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800">
                        {{ $order->status_label }}
                    </span>
                </div>
                <button 
                    wire:click="$set('showStatusModal', true)"
                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    {{ __('Update Status') }}
                </button>
                @if($order->status === 'delivered')
                    <button 
                        wire:click="refundOrder"
                        class="w-full mt-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                    >
                        {{ __('Process Refund') }}
                    </button>
                @endif
            </div>

            <!-- Payment Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-4">{{ __('Payment Information') }}</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('Method:') }}</dt>
                        <dd class="font-medium">
                            {{ $order->payment->payment_method === 'cod' ? __('Cash on Delivery') : __('Card') }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('Status:') }}</dt>
                        <dd>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-{{ $order->payment->status_color }}-100 text-{{ $order->payment->status_color }}-800">
                                {{ ucfirst($order->payment->status) }}
                            </span>
                        </dd>
                    </div>
                    @if($order->payment->transaction_id)
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Transaction:') }}</dt>
                            <dd class="font-mono text-sm">{{ $order->payment->transaction_id }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Shipping Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-4">{{ __('Shipping Information') }}</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('Method:') }}</dt>
                        <dd class="font-medium">{{ $order->shippingMethod->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('Cost:') }}</dt>
                        <dd class="font-medium">{{ $currency->symbol }} {{ number_format($order->shipping_amount, 2) }}</dd>
                    </div>
                    @if($order->tracking_number)
                        <div>
                            <dt class="text-gray-600 mb-1">{{ __('Tracking:') }}</dt>
                            <dd class="font-mono text-sm">{{ $order->tracking_number }}</dd>
                            <dd class="text-sm text-gray-600">{{ $order->tracking_carrier }}</dd>
                        </div>
                    @endif
                </dl>
                <button 
                    wire:click="$set('showTrackingModal', true)"
                    class="w-full mt-4 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                >
                    {{ __('Update Tracking') }}
                </button>
            </div>

            <!-- Order Summary -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-4">{{ __('Order Summary') }}</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('Subtotal:') }}</dt>
                        <dd>{{ $currency->symbol }} {{ number_format($order->subtotal, 2) }}</dd>
                    </div>
                    @if($order->discount_amount > 0)
                        <div class="flex justify-between text-green-600">
                            <dt>{{ __('Discount:') }}</dt>
                            <dd>-{{ $currency->symbol }} {{ number_format($order->discount_amount, 2) }}</dd>
                        </div>
                    @endif
                    @if($order->tax_amount > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('Tax:') }}</dt>
                            <dd>{{ $currency->symbol }} {{ number_format($order->tax_amount, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('Shipping:') }}</dt>
                        <dd>{{ $currency->symbol }} {{ number_format($order->shipping_amount, 2) }}</dd>
                    </div>
                    <div class="border-t pt-2">
                        <div class="flex justify-between text-lg font-semibold">
                            <dt>{{ __('Total:') }}</dt>
                            <dd>{{ $currency->symbol }} {{ number_format($order->total, 2) }}</dd>
                        </div>
                    </div>
                </dl>
            </div>

            <!-- Order Timeline -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-4">{{ __('Order Timeline') }}</h3>
                <div class="space-y-3">
                    @foreach($order->statusHistories as $history)
                        <div class="text-sm">
                            <p class="font-medium">{{ ucfirst($history->status) }}</p>
                            @if($history->comment)
                                <p class="text-gray-600">{{ $history->comment }}</p>
                            @endif
                            <p class="text-gray-500">
                                {{ $history->created_at->format('M d, Y h:i A') }}
                                @if($history->creator)
                                    by {{ $history->creator->name }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    @if($showStatusModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">{{ __('Update Order Status') }}</h3>
                
                <form wire:submit="updateStatus">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('New Status') }}</label>
                        <select 
                            wire:model="newStatus"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            required
                        >
                            <option value="">{{ __('Select Status') }}</option>
                            <option value="pending">{{ __('Pending') }}</option>
                            <option value="processing">{{ __('Processing') }}</option>
                            <option value="shipped">{{ __('Shipped') }}</option>
                            <option value="delivered">{{ __('Delivered') }}</option>
                            <option value="cancelled">{{ __('Cancelled') }}</option>
                            <option value="refunded">{{ __('Refunded') }}</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Comment (Optional)') }}</label>
                        <textarea 
                            wire:model="statusComment"
                            rows="3"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                        ></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button 
                            type="submit"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            {{ __('Update Status') }}
                        </button>
                        <button 
                            type="button"
                            wire:click="$set('showStatusModal', false)"
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                        >
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Tracking Update Modal -->
    @if($showTrackingModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">{{ __('Update Tracking Information') }}</h3>
                
                <form wire:submit="updateTracking">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Tracking Number') }}</label>
                        <input 
                            type="text"
                            wire:model="trackingNumber"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            required
                        >
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Carrier') }}</label>
                        <input 
                            type="text"
                            wire:model="trackingCarrier"
                            placeholder="e.g., FedEx, UPS, DHL"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            required
                        >
                    </div>

                    <div class="flex gap-2">
                        <button 
                            type="submit"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            {{ __('Update Tracking') }}
                        </button>
                        <button 
                            type="button"
                            wire:click="$set('showTrackingModal', false)"
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                        >
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>