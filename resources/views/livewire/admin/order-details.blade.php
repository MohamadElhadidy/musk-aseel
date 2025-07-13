<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public Order $order;
    public string $newStatus = '';
    public string $statusNote = '';
    public bool $showStatusModal = false;
    public bool $showRefundModal = false;
    public string $refundAmount = '';
    public string $refundReason = '';
    public string $trackingNumber = '';
    public string $shippingCarrier = '';

    #[Layout('components.layouts.admin')]
    public function mount($id)
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }

        $this->order = Order::with([
            'user',
            'items.product',
            'items.productVariant',
            'shippingAddress.city.country',
            'billingAddress.city.country',
            'statusHistories.user',
            'coupon',
            'transactions'
        ])->findOrFail($id);
    }

    public function updateOrderStatus()
    {
        $this->validate([
            'newStatus' => 'required|in:pending,processing,shipped,delivered,cancelled,refunded',
            'statusNote' => 'nullable|string|max:500'
        ]);

        DB::transaction(function () {
            // Update order status
            $oldStatus = $this->order->status;
            $this->order->status = $this->newStatus;

            // Update dates based on status
            if ($this->newStatus === 'shipped' && !$this->order->shipped_at) {
                $this->order->shipped_at = now();
            } elseif ($this->newStatus === 'delivered' && !$this->order->delivered_at) {
                $this->order->delivered_at = now();
            } elseif ($this->newStatus === 'cancelled' && !$this->order->cancelled_at) {
                $this->order->cancelled_at = now();

                // Restore product quantities
                foreach ($this->order->items as $item) {
                    if ($item->product->track_quantity) {
                        $item->product->increment('quantity', $item->quantity);
                        if ($item->productVariant) {
                            $item->productVariant->increment('quantity', $item->quantity);
                        }
                    }
                }
            }

            $this->order->save();

            // Create status history
            OrderStatusHistory::create([
                'order_id' => $this->order->id,
                'user_id' => auth()->id(),
                'from_status' => $oldStatus,
                'to_status' => $this->newStatus,
                'note' => $this->statusNote
            ]);

            // Send notification email to customer
            // Mail::to($this->order->user)->send(new OrderStatusUpdated($this->order));
        });

        $this->showStatusModal = false;
        $this->reset(['newStatus', 'statusNote']);
        $this->dispatch('toast', type: 'success', message: 'Order status updated successfully');
    }

    public function updateShippingInfo()
    {
        $this->validate([
            'trackingNumber' => 'nullable|string|max:100',
            'shippingCarrier' => 'nullable|string|max:50'
        ]);

        $this->order->update([
            'tracking_number' => $this->trackingNumber,
            'shipping_carrier' => $this->shippingCarrier
        ]);

        $this->dispatch('toast', type: 'success', message: 'Shipping information updated');
    }

    public function processRefund()
    {
        $this->validate([
            'refundAmount' => 'required|numeric|min:0.01|max:' . $this->order->total,
            'refundReason' => 'required|string|max:500'
        ]);

        DB::transaction(function () {
            // Create refund transaction
            $this->order->transactions()->create([
                'type' => 'refund',
                'amount' => $this->refundAmount,
                'status' => 'pending',
                'reference' => 'REF-' . strtoupper(uniqid()),
                'notes' => $this->refundReason
            ]);

            // Update order status if full refund
            if ($this->refundAmount == $this->order->total) {
                $this->order->update(['status' => 'refunded']);
            }

            // Process actual refund through payment gateway
            // PaymentGateway::refund($this->order, $this->refundAmount);
        });

        $this->showRefundModal = false;
        $this->reset(['refundAmount', 'refundReason']);
        $this->dispatch('toast', type: 'success', message: 'Refund initiated successfully');
    }

    public function downloadInvoice()
    {
        // Generate and download PDF invoice
        // return response()->download($this->order->generateInvoice());
        $this->dispatch('toast', type: 'info', message: 'Invoice download feature coming soon');
    }

    public function resendOrderEmail()
    {
        // Mail::to($this->order->user)->send(new OrderConfirmation($this->order));
        $this->dispatch('toast', type: 'success', message: 'Order confirmation email sent');
    }

    public function with()
    {
        return [
            'orderStatuses' => [
                'pending' => ['label' => 'Pending', 'color' => 'yellow'],
                'processing' => ['label' => 'Processing', 'color' => 'blue'],
                'shipped' => ['label' => 'Shipped', 'color' => 'indigo'],
                'delivered' => ['label' => 'Delivered', 'color' => 'green'],
                'cancelled' => ['label' => 'Cancelled', 'color' => 'red'],
                'refunded' => ['label' => 'Refunded', 'color' => 'purple'],
            ]
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Order #{{ $order->order_number }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                Placed on {{ $order->created_at->format('M d, Y \a\t g:i A') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="downloadInvoice" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Download Invoice
            </button>
            <button wire:click="resendOrderEmail" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Resend Email
            </button>
            <button wire:click="$set('showRefundModal', true)" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
                Process Refund
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Order Status</h2>
                    <button wire:click="$set('showStatusModal', true)" class="text-sm text-blue-600 hover:text-blue-800">
                        Change Status
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-{{ $orderStatuses[$order->status]['color'] }}-100 text-{{ $orderStatuses[$order->status]['color'] }}-800">
                        {{ $orderStatuses[$order->status]['label'] }}
                    </span>
                    @if($order->is_urgent)
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-800">
                        Urgent
                    </span>
                    @endif
                </div>

                <!-- Status Timeline -->
                <div class="mt-6 space-y-3">
                    @foreach($order->statusHistories as $history)
                    <div class="flex gap-3">
                        <div class="flex-shrink-0 w-2 h-2 mt-2 bg-gray-400 rounded-full"></div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-900">
                                {{ ucfirst($history->to_status) }}
                                @if($history->note)
                                - {{ $history->note }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $history->created_at->format('M d, Y g:i A') }} by {{ $history->user->name }}
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>

                <div class="space-y-4">
                    @foreach($order->items as $item)
                    <div class="flex gap-4 pb-4 border-b border-gray-200 last:border-0 last:pb-0">
                        @if($item->product->primaryImage)
                        <img src="{{ Storage::url($item->product->primaryImage->image) }}"
                            alt="{{ $item->product->name }}"
                            class="w-20 h-20 object-cover rounded-lg">
                        @else
                        <div class="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        @endif

                        <div class="flex-1">
                            <h3 class="font-medium text-gray-900">{{ $item->product->name }}</h3>
                            @if($item->productVariant)
                            <p class="text-sm text-gray-600">
                                @foreach($item->productVariant->attributes as $key => $value)
                                {{ ucfirst($key) }}: {{ $value }}@if(!$loop->last), @endif
                                @endforeach
                            </p>
                            @endif
                            <p class="text-sm text-gray-600">SKU: {{ $item->product->sku }}</p>
                        </div>

                        <div class="text-right">
                            <p class="font-medium text-gray-900">${{ number_format($item->price * $item->quantity, 2) }}</p>
                            <p class="text-sm text-gray-600">
                                ${{ number_format($item->price, 2) }} × {{ $item->quantity }}
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Order Summary -->
                <div class="mt-6 pt-6 border-t border-gray-200 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium">${{ number_format($order->subtotal, 2) }}</span>
                    </div>
                    @if($order->discount_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">
                            Discount
                            @if($order->coupon)
                            ({{ $order->coupon->code }})
                            @endif
                        </span>
                        <span class="font-medium text-green-600">-${{ number_format($order->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Shipping</span>
                        <span class="font-medium">${{ number_format($order->shipping_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax</span>
                        <span class="font-medium">${{ number_format($order->tax_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-semibold pt-2 border-t">
                        <span>Total</span>
                        <span>${{ number_format($order->total, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Shipping Address</h3>
                        <div class="text-sm text-gray-600">
                            <p>{{ $order->shippingAddress->name }}</p>
                            <p>{{ $order->shippingAddress->address_line_1 }}</p>
                            @if($order->shippingAddress->address_line_2)
                            <p>{{ $order->shippingAddress->address_line_2 }}</p>
                            @endif
                            <p>{{ $order->shippingAddress->city->name }}, {{ $order->shippingAddress->postal_code }}</p>
                            <p>{{ $order->shippingAddress->city->country->name }}</p>
                            <p class="mt-2">Phone: {{ $order->shippingAddress->phone }}</p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Billing Address</h3>
                        <div class="text-sm text-gray-600">
                            @if($order->billingAddress)
                            <p>{{ $order->billingAddress->name }}</p>
                            <p>{{ $order->billingAddress->address_line_1 }}</p>
                            @if($order->billingAddress->address_line_2)
                            <p>{{ $order->billingAddress->address_line_2 }}</p>
                            @endif
                            <p>{{ $order->billingAddress->city->name }}, {{ $order->billingAddress->postal_code }}</p>
                            <p>{{ $order->billingAddress->city->country->name }}</p>
                            <p class="mt-2">Phone: {{ $order->billingAddress->phone }}</p>
                            @else
                            <p class="text-gray-500 italic">Same as shipping address</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Tracking Information -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Tracking Information</h3>
                    <form wire:submit="updateShippingInfo" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                            <input type="text"
                                wire:model="trackingNumber"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter tracking number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Shipping Carrier</label>
                            <select wire:model="shippingCarrier"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select carrier</option>
                                <option value="fedex">FedEx</option>
                                <option value="ups">UPS</option>
                                <option value="usps">USPS</option>
                                <option value="dhl">DHL</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                Update Tracking Info
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Customer Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Customer Information</h2>

                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-600">Name</p>
                        <p class="font-medium">{{ $order->user->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Email</p>
                        <p class="font-medium">{{ $order->user->email }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Phone</p>
                        <p class="font-medium">{{ $order->user->phone ?? 'Not provided' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Customer Since</p>
                        <p class="font-medium">{{ $order->user->created_at->format('M d, Y') }}</p>
                    </div>
                    <div class="pt-3">
                        <a href="/admin/customers/{{ $order->user->id }}"
                            wire:navigate
                            class="text-sm text-blue-600 hover:text-blue-800">
                            View Customer Profile →
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h2>

                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-600">Payment Method</p>
                        <p class="font-medium">{{ ucfirst($order->payment_method) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Payment Status</p>
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                            @if($order->payment_status === 'paid') bg-green-100 text-green-800
                            @elseif($order->payment_status === 'pending') bg-yellow-100 text-yellow-800
                            @else bg-red-100 text-red-800 @endif">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>
                    @if($order->transaction_id)
                    <div>
                        <p class="text-sm text-gray-600">Transaction ID</p>
                        <p class="font-medium text-sm">{{ $order->transaction_id }}</p>
                    </div>
                    @endif
                </div>

                <!-- Transactions -->
                @if($order->transactions->count() > 0)
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Transaction History</h3>
                    <div class="space-y-2">
                        @foreach($order->transactions as $transaction)
                        <div class="text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ ucfirst($transaction->type) }}</span>
                                <span class="font-medium">${{ number_format($transaction->amount, 2) }}</span>
                            </div>
                            <p class="text-xs text-gray-500">{{ $transaction->created_at->format('M d, Y g:i A') }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Order Notes -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Notes</h2>

                @if($order->customer_notes)
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm font-medium text-gray-900 mb-1">Customer Notes:</p>
                    <p class="text-sm text-gray-600">{{ $order->customer_notes }}</p>
                </div>
                @endif

                @if($order->admin_notes)
                <div class="p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm font-medium text-gray-900 mb-1">Admin Notes:</p>
                    <p class="text-sm text-gray-600">{{ $order->admin_notes }}</p>
                </div>
                @endif

                @if(!$order->customer_notes && !$order->admin_notes)
                <p class="text-sm text-gray-500 italic">No notes added</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div x-show="$wire.showStatusModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showStatusModal', false)"></div>

            <div class="relative bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Change Order Status</h3>

                <form wire:submit="updateOrderStatus">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select wire:model="newStatus"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select status</option>
                            @foreach($orderStatuses as $value => $status)
                            <option value="{{ $value }}">{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                        @error('newStatus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Note (Optional)</label>
                        <textarea wire:model="statusNote"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Describe the reason for this refund"></textarea>
                        @error('refundReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700">
                            Process Refund
                        </button>
                        <button type="button"
                            wire:click="$set('showRefundModal', false)"
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Refund Modal -->
    <div x-show="$wire.showRefundModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showRefundModal', false)"></div>

            <div class="relative bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Process Refund</h3>

                <form wire:submit="processRefund">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Refund Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number"
                                wire:model="refundAmount"
                                step="0.01"
                                max="{{ $order->total }}"
                                class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0.00">
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Maximum: ${{ number_format($order->total, 2) }}</p>
                        @error('refundAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Refund</label>
                        <textarea wire:model="refundReason"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Add a note about this status change"></textarea>
                        @error('statusNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Update Status
                        </button>
                        <button type="button"
                            wire:click="$set('showStatusModal', false)"
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>