<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ShippingMethod;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderStatusUpdated;
use Livewire\Attributes\Layout;

new class extends Component
{
    public $orderId;
    public ?Order $order = null;
    
    // Status update form
    public string $newStatus = '';
    public string $statusNote = '';
    public bool $notifyCustomer = true;
    
    // Tracking info
    public string $trackingNumber = '';
    public string $trackingUrl = '';
    
    // Refund form
    public bool $showRefundForm = false;
    public string $refundAmount = '';
    public string $refundReason = '';
    public array $refundItems = [];
    
    // Notes
    public string $adminNote = '';
    
    // Modals
    public bool $showTrackingModal = false;
    public bool $showCustomerModal = false;
    public bool $showAddressModal = false;
    public string $editingAddress = ''; // 'shipping' or 'billing'

    #[Layout('components.layouts.admin')]
    public function mount($orderId)
    {
        if (!auth()->user()?->is_admin) {
            $this->redirect('/', navigate: true);
        }
        
        $this->orderId = $orderId;
        $this->loadOrder();
    }

    public function loadOrder()
    {
        $this->order = Order::with([
            'user',
            'items.product',
            'items.variant',
            'shippingAddress.city.country',
            'billingAddress.city.country',
            'payment',
            'statusHistories.user',
            'shippingMethod',
            'coupon'
        ])->findOrFail($this->orderId);
        
        $this->newStatus = $this->order->status;
        $this->trackingNumber = $this->order->tracking_number ?? '';
        $this->trackingUrl = $this->order->tracking_url ?? '';
    }

    public function updateStatus()
    {
        $this->validate([
            'newStatus' => 'required|in:pending,processing,shipped,delivered,cancelled,refunded',
            'statusNote' => 'nullable|string|max:500'
        ]);

        if ($this->newStatus === $this->order->status) {
            $this->dispatch('toast', 
                type: 'info',
                message: __('Status is already :status', ['status' => $this->newStatus])
            );
            return;
        }

        // Update order status
        $oldStatus = $this->order->status;
        $this->order->update(['status' => $this->newStatus]);

        // Create status history
        OrderStatusHistory::create([
            'order_id' => $this->order->id,
            'status' => $this->newStatus,
            'note' => $this->statusNote,
            'user_id' => auth()->id()
        ]);

        // Send notification if requested
        if ($this->notifyCustomer && $this->order->user) {
            Mail::to($this->order->user->email)->queue(new OrderStatusUpdated($this->order));
        }

        // Handle status-specific actions
        $this->handleStatusActions($oldStatus, $this->newStatus);

        $this->reset(['statusNote']);
        $this->loadOrder();
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Order status updated successfully')
        );
    }

    private function handleStatusActions($oldStatus, $newStatus)
    {
        // Restore stock if cancelling or refunding
        if (in_array($newStatus, ['cancelled', 'refunded']) && !in_array($oldStatus, ['cancelled', 'refunded'])) {
            foreach ($this->order->items as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                } else {
                    $item->product->increment('stock', $item->quantity);
                }
            }
        }

        // Deduct stock if moving from cancelled/refunded to active status
        if (in_array($oldStatus, ['cancelled', 'refunded']) && !in_array($newStatus, ['cancelled', 'refunded'])) {
            foreach ($this->order->items as $item) {
                if ($item->variant) {
                    $item->variant->decrement('stock', $item->quantity);
                } else {
                    $item->product->decrement('stock', $item->quantity);
                }
            }
        }
    }

    public function updateTracking()
    {
        $this->validate([
            'trackingNumber' => 'nullable|string|max:255',
            'trackingUrl' => 'nullable|url|max:500'
        ]);

        $this->order->update([
            'tracking_number' => $this->trackingNumber,
            'tracking_url' => $this->trackingUrl
        ]);

        $this->showTrackingModal = false;
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Tracking information updated')
        );
    }

    public function processRefund()
    {
        $this->validate([
            'refundAmount' => 'required|numeric|min:0|max:' . $this->order->total,
            'refundReason' => 'required|string|max:500'
        ]);

        // Process refund through payment gateway
        // This is a placeholder - implement actual refund logic based on payment method
        
        $this->order->update([
            'status' => 'refunded',
            'refund_amount' => $this->refundAmount,
            'refund_reason' => $this->refundReason,
            'refunded_at' => now()
        ]);

        OrderStatusHistory::create([
            'order_id' => $this->order->id,
            'status' => 'refunded',
            'note' => __('Refund processed: :amount :currency. Reason: :reason', [
                'amount' => $this->refundAmount,
                'currency' => $this->order->currency,
                'reason' => $this->refundReason
            ]),
            'user_id' => auth()->id()
        ]);

        // Restore stock for refunded items
        if (!empty($this->refundItems)) {
            foreach ($this->refundItems as $itemId => $refund) {
                if ($refund) {
                    $item = $this->order->items->find($itemId);
                    if ($item) {
                        if ($item->variant) {
                            $item->variant->increment('stock', $item->quantity);
                        } else {
                            $item->product->increment('stock', $item->quantity);
                        }
                    }
                }
            }
        }

        $this->reset(['showRefundForm', 'refundAmount', 'refundReason', 'refundItems']);
        $this->loadOrder();
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Refund processed successfully')
        );
    }

    public function addAdminNote()
    {
        $this->validate([
            'adminNote' => 'required|string|max:1000'
        ]);

        OrderStatusHistory::create([
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'note' => $this->adminNote,
            'user_id' => auth()->id(),
            'is_admin_note' => true
        ]);

        $this->reset('adminNote');
        $this->loadOrder();
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Note added successfully')
        );
    }

    public function downloadInvoice()
    {
        $pdf = Pdf::loadView('invoices.order', ['order' => $this->order]);
        
        return response()->streamDownload(
            fn() => print($pdf->output()),
            "invoice-{$this->order->order_number}.pdf"
        );
    }

    public function resendOrderEmail()
    {
        if ($this->order->user) {
            Mail::to($this->order->user->email)->queue(new OrderStatusUpdated($this->order));
            
            $this->dispatch('toast', 
                type: 'success',
                message: __('Order email sent successfully')
            );
        }
    }

    public function printPackingSlip()
    {
        $this->dispatch('print-packing-slip', orderId: $this->order->id);
    }

    
}; ?>

<div>
    @if($order)
        <!-- Header -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        {{ __('Order') }} #{{ $order->order_number }}
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ __('Placed on') }} {{ $order->created_at->format('M d, Y H:i') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button 
                        wire:click="downloadInvoice"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                    >
                        {{ __('Download Invoice') }}
                    </button>
                    <button 
                        wire:click="printPackingSlip"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        {{ __('Print Packing Slip') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Order Status') }}</h2>
                    
                    <div class="mb-4">
                        <span class="px-3 py-1 text-sm font-medium rounded-full
                            @if($order->status === 'delivered') bg-green-100 text-green-800
                            @elseif($order->status === 'shipped') bg-blue-100 text-blue-800
                            @elseif($order->status === 'processing') bg-yellow-100 text-yellow-800
                            @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                            @elseif($order->status === 'refunded') bg-gray-100 text-gray-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst($order->status) }}
                        </span>
                    </div>

                    <form wire:submit="updateStatus" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Update Status') }}
                            </label>
                            <select 
                                wire:model="newStatus"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="processing">{{ __('Processing') }}</option>
                                <option value="shipped">{{ __('Shipped') }}</option>
                                <option value="delivered">{{ __('Delivered') }}</option>
                                <option value="cancelled">{{ __('Cancelled') }}</option>
                                <option value="refunded">{{ __('Refunded') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Status Note') }}
                            </label>
                            <textarea 
                                wire:model="statusNote"
                                rows="2"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="{{ __('Add a note about this status change...') }}"
                            ></textarea>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    wire:model="notifyCustomer"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="ml-2 text-sm text-gray-700">
                                    {{ __('Notify customer via email') }}
                                </span>
                            </label>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                            >
                                {{ __('Update Status') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">{{ __('Order Items') }}</h2>
                        @if($order->status === 'shipped')
                            <button 
                                wire:click="$set('showTrackingModal', true)"
                                class="text-sm text-blue-600 hover:underline"
                            >
                                {{ __('Update Tracking') }}
                            </button>
                        @endif
                    </div>

                    <div class="space-y-4">
                        @foreach($order->items as $item)
                            <div class="flex gap-4 pb-4 border-b last:border-0">
                                <img 
                                    src="{{ $item->product->featured_image }}" 
                                    alt="{{ $item->product->name }}"
                                    class="w-16 h-16 object-cover rounded"
                                >
                                <div class="flex-1">
                                    <h3 class="font-medium">
                                        {{ $item->product->getTranslatedName(app()->getLocale()) }}
                                    </h3>
                                    @if($item->variant)
                                        <p class="text-sm text-gray-500">
                                            {{ __('Variant') }}: {{ $item->variant->name }}
                                        </p>
                                    @endif
                                    <p class="text-sm text-gray-500">
                                        {{ __('SKU') }}: {{ $item->product->sku }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">
                                        {{ $item->quantity }} × {{ currency($item->price) }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ currency($item->quantity * $item->price) }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Order Summary -->
                    <div class="mt-6 pt-6 border-t space-y-2">
                        <div class="flex justify-between text-sm">
                            <span>{{ __('Subtotal') }}</span>
                            <span>{{ currency($order->subtotal) }}</span>
                        </div>
                        @if($order->discount > 0)
                            <div class="flex justify-between text-sm text-green-600">
                                <span>{{ __('Discount') }} 
                                    @if($order->coupon)
                                        ({{ $order->coupon->code }})
                                    @endif
                                </span>
                                <span>-{{ currency($order->discount) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-sm">
                            <span>{{ __('Shipping') }}</span>
                            <span>{{ currency($order->shipping_cost) }}</span>
                        </div>
                        @if($order->tax > 0)
                            <div class="flex justify-between text-sm">
                                <span>{{ __('Tax') }}</span>
                                <span>{{ currency($order->tax) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between font-semibold text-lg pt-2 border-t">
                            <span>{{ __('Total') }}</span>
                            <span>{{ currency($order->total) }}</span>
                        </div>
                        @if($order->refund_amount > 0)
                            <div class="flex justify-between text-sm text-red-600">
                                <span>{{ __('Refunded') }}</span>
                                <span>-{{ currency($order->refund_amount) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Status History -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Order History') }}</h2>
                    
                    <div class="space-y-4">
                        @foreach($order->statusHistories as $history)
                            <div class="flex gap-4">
                                <div class="flex-shrink-0">
                                    <div class="w-2 h-2 bg-blue-600 rounded-full mt-2"></div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between">
                                        <p class="font-medium">
                                            {{ ucfirst($history->status) }}
                                            @if($history->is_admin_note)
                                                <span class="text-sm text-gray-500">
                                                    ({{ __('Admin Note') }})
                                                </span>
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ $history->created_at->format('M d, Y H:i') }}
                                        </p>
                                    </div>
                                    @if($history->note)
                                        <p class="text-sm text-gray-600 mt-1">{{ $history->note }}</p>
                                    @endif
                                    @if($history->user)
                                        <p class="text-xs text-gray-500 mt-1">
                                            {{ __('By') }}: {{ $history->user->name }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Add Admin Note -->
                    <form wire:submit="addAdminNote" class="mt-6 pt-6 border-t">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Add Admin Note') }}
                        </label>
                        <div class="flex gap-2">
                            <textarea 
                                wire:model="adminNote"
                                rows="2"
                                class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="{{ __('Add internal note...') }}"
                            ></textarea>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                            >
                                {{ __('Add') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Customer Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">{{ __('Customer') }}</h2>
                        <button 
                            wire:click="$set('showCustomerModal', true)"
                            class="text-sm text-blue-600 hover:underline"
                        >
                            {{ __('View Details') }}
                        </button>
                    </div>
                    
                    @if($order->user)
                        <div class="space-y-2">
                            <p class="font-medium">{{ $order->user->name }}</p>
                            <p class="text-sm text-gray-600">{{ $order->user->email }}</p>
                            <p class="text-sm text-gray-600">{{ $order->user->phone }}</p>
                            <div class="pt-2 border-t">
                                <p class="text-sm text-gray-500">
                                    {{ __('Customer since') }}: {{ $order->user->created_at->format('M Y') }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ __('Total orders') }}: {{ $order->user->orders()->count() }}
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500">{{ __('Guest Order') }}</p>
                        <p class="text-sm text-gray-600">{{ $order->customer_email }}</p>
                    @endif
                </div>

                <!-- Shipping Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">{{ __('Shipping Address') }}</h2>
                        <button 
                            wire:click="$set('editingAddress', 'shipping'); $set('showAddressModal', true)"
                            class="text-sm text-blue-600 hover:underline"
                        >
                            {{ __('Edit') }}
                        </button>
                    </div>
                    
                    @if($order->shippingAddress)
                        <div class="text-sm space-y-1">
                            <p class="font-medium">{{ $order->shippingAddress->name }}</p>
                            <p>{{ $order->shippingAddress->address_line_1 }}</p>
                            @if($order->shippingAddress->address_line_2)
                                <p>{{ $order->shippingAddress->address_line_2 }}</p>
                            @endif
                            <p>
                                {{ $order->shippingAddress->city->name }}, 
                                {{ $order->shippingAddress->state }} 
                                {{ $order->shippingAddress->postal_code }}
                            </p>
                            <p>{{ $order->shippingAddress->city->country->name }}</p>
                            <p>{{ __('Phone') }}: {{ $order->shippingAddress->phone }}</p>
                        </div>
                    @endif
                </div>

                <!-- Billing Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">{{ __('Billing Address') }}</h2>
                        <button 
                            wire:click="$set('editingAddress', 'billing'); $set('showAddressModal', true)"
                            class="text-sm text-blue-600 hover:underline"
                        >
                            {{ __('Edit') }}
                        </button>
                    </div>
                    
                    @if($order->billingAddress)
                        <div class="text-sm space-y-1">
                            <p class="font-medium">{{ $order->billingAddress->name }}</p>
                            <p>{{ $order->billingAddress->address_line_1 }}</p>
                            @if($order->billingAddress->address_line_2)
                                <p>{{ $order->billingAddress->address_line_2 }}</p>
                            @endif
                            <p>
                                {{ $order->billingAddress->city->name }}, 
                                {{ $order->billingAddress->state }} 
                                {{ $order->billingAddress->postal_code }}
                            </p>
                            <p>{{ $order->billingAddress->city->country->name }}</p>
                            <p>{{ __('Phone') }}: {{ $order->billingAddress->phone }}</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">{{ __('Same as shipping address') }}</p>
                    @endif
                </div>

                <!-- Payment Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Payment Information') }}</h2>
                    
                    @if($order->payment)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('Method') }}:</span>
                                <span>{{ ucfirst($order->payment->payment_method) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('Status') }}:</span>
                                <span class="font-medium 
                                    @if($order->payment->status === 'completed') text-green-600
                                    @elseif($order->payment->status === 'pending') text-yellow-600
                                    @else text-red-600
                                    @endif">
                                    {{ ucfirst($order->payment->status) }}
                                </span>
                            </div>
                            @if($order->payment->transaction_id)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Transaction ID') }}:</span>
                                    <span class="font-mono text-xs">{{ $order->payment->transaction_id }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($order->status !== 'refunded' && $order->payment?->status === 'completed')
                        <button 
                            wire:click="$set('showRefundForm', true)"
                            class="mt-4 w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                        >
                            {{ __('Process Refund') }}
                        </button>
                    @endif
                </div>

                <!-- Shipping Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Shipping Information') }}</h2>
                    
                    @if($order->shippingMethod)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('Method') }}:</span>
                                <span>{{ $order->shippingMethod->getTranslatedName(app()->getLocale()) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('Cost') }}:</span>
                                <span>{{ currency($order->shipping_cost) }}</span>
                            </div>
                            @if($order->tracking_number)
                                <div class="pt-2 border-t">
                                    <p class="text-gray-600 mb-1">{{ __('Tracking Number') }}:</p>
                                    <p class="font-mono text-xs">{{ $order->tracking_number }}</p>
                                    @if($order->tracking_url)
                                        <a 
                                            href="{{ $order->tracking_url }}" 
                                            target="_blank"
                                            class="text-blue-600 hover:underline text-xs"
                                        >
                                            {{ __('Track Package') }} →
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Quick Actions') }}</h2>
                    
                    <div class="space-y-2">
                        <button 
                            wire:click="resendOrderEmail"
                            class="w-full px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                        >
                            {{ __('Resend Order Email') }}
                        </button>
                        
                        @if($order->status === 'processing')
                            <button 
                                wire:click="$set('showTrackingModal', true)"
                                class="w-full px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200"
                            >
                                {{ __('Add Tracking Info') }}
                            </button>
                        @endif
                        
                        <a 
                            href="{{ route('admin.orders') }}"
                            wire:navigate
                            class="block w-full px-4 py-2 bg-gray-600 text-white text-center rounded-lg hover:bg-gray-700"
                        >
                            {{ __('Back to Orders') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracking Modal -->
        @if($showTrackingModal)
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-md w-full">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Update Tracking Information') }}</h3>
                    
                    <form wire:submit="updateTracking">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Tracking Number') }}
                                </label>
                                <input 
                                    type="text" 
                                    wire:model="trackingNumber"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="{{ __('Enter tracking number') }}"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Tracking URL') }}
                                </label>
                                <input 
                                    type="url" 
                                    wire:model="trackingUrl"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="{{ __('https://...') }}"
                                >
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button 
                                type="button"
                                wire:click="$set('showTrackingModal', false)"
                                class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                            >
                                {{ __('Update Tracking') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Refund Modal -->
        @if($showRefundForm)
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Process Refund') }}</h3>
                    
                    <form wire:submit="processRefund">
                        <div class="space-y-4">
                            <!-- Select Items to Refund -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Select items to refund') }}
                                </label>
                                <div class="space-y-2 border rounded-lg p-4">
                                    @foreach($order->items as $item)
                                        <label class="flex items-center gap-3">
                                            <input 
                                                type="checkbox" 
                                                wire:model="refundItems.{{ $item->id }}"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            >
                                            <span class="flex-1">
                                                {{ $item->product->getTranslatedName(app()->getLocale()) }}
                                                @if($item->variant)
                                                    ({{ $item->variant->name }})
                                                @endif
                                                - {{ $item->quantity }} × {{ currency($item->price) }}
                                            </span>
                                            <span class="font-medium">
                                                {{ currency($item->quantity * $item->price) }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Refund Amount') }} <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    wire:model="refundAmount"
                                    step="0.01"
                                    max="{{ $order->total }}"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                >
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ __('Maximum refundable amount') }}: {{ currency($order->total) }}
                                </p>
                                @error('refundAmount')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Refund Reason') }} <span class="text-red-500">*</span>
                                </label>
                                <textarea 
                                    wire:model="refundReason"
                                    rows="3"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="{{ __('Explain the reason for this refund...') }}"
                                    required
                                ></textarea>
                                @error('refundReason')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <p class="text-sm text-yellow-800">
                                    <strong>{{ __('Warning') }}:</strong> 
                                    {{ __('This action will process a refund through the payment gateway and update the order status to "Refunded". Stock will be restored for selected items.') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button 
                                type="button"
                                wire:click="$set('showRefundForm', false)"
                                class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                                onclick="return confirm('{{ __('Are you sure you want to process this refund?') }}')"
                            >
                                {{ __('Process Refund') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Customer Details Modal -->
        @if($showCustomerModal && $order->user)
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">{{ __('Customer Details') }}</h3>
                        <button 
                            wire:click="$set('showCustomerModal', false)"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Customer Info -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">{{ __('Contact Information') }}</h4>
                            <div class="space-y-1 text-sm">
                                <p><span class="text-gray-600">{{ __('Name') }}:</span> {{ $order->user->name }}</p>
                                <p><span class="text-gray-600">{{ __('Email') }}:</span> {{ $order->user->email }}</p>
                                <p><span class="text-gray-600">{{ __('Phone') }}:</span> {{ $order->user->phone ?? __('Not provided') }}</p>
                                <p><span class="text-gray-600">{{ __('Registered') }}:</span> {{ $order->user->created_at->format('M d, Y') }}</p>
                                <p><span class="text-gray-600">{{ __('Preferred Language') }}:</span> {{ strtoupper($order->user->preferred_language ?? 'en') }}</p>
                            </div>
                        </div>

                        <!-- Order Statistics -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">{{ __('Order Statistics') }}</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-sm text-gray-600">{{ __('Total Orders') }}</p>
                                    <p class="text-2xl font-semibold">{{ $order->user->orders()->count() }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-sm text-gray-600">{{ __('Total Spent') }}</p>
                                    <p class="text-2xl font-semibold">{{ currency($order->user->orders()->sum('total')) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">{{ __('Recent Orders') }}</h4>
                            <div class="space-y-2">
                                @foreach($order->user->orders()->latest()->limit(5)->get() as $recentOrder)
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <div>
                                            <p class="font-medium">#{{ $recentOrder->order_number }}</p>
                                            <p class="text-sm text-gray-500">{{ $recentOrder->created_at->format('M d, Y') }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium">{{ currency($recentOrder->total) }}</p>
                                            <span class="text-xs px-2 py-1 rounded-full
                                                @if($recentOrder->status === 'delivered') bg-green-100 text-green-800
                                                @elseif($recentOrder->status === 'shipped') bg-blue-100 text-blue-800
                                                @elseif($recentOrder->status === 'processing') bg-yellow-100 text-yellow-800
                                                @elseif($recentOrder->status === 'cancelled') bg-red-100 text-red-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ ucfirst($recentOrder->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a 
                                href="{{ route('admin.customers.show', $order->user->id) }}"
                                wire:navigate
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                            >
                                {{ __('View Full Profile') }}
                            </a>
                            <button 
                                wire:click="$set('showCustomerModal', false)"
                                class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                {{ __('Close') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Address Edit Modal -->
        @if($showAddressModal)
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-lg w-full">
                    <h3 class="text-lg font-semibold mb-4">
                        {{ __('Edit :type Address', ['type' => ucfirst($editingAddress)]) }}
                    </h3>
                    
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            {{ __('Address editing functionality would be implemented here. This would include form fields for updating the address details.') }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button 
                            wire:click="$set('showAddressModal', false)"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="text-center py-12">
            <p class="text-gray-500">{{ __('Order not found') }}</p>
            <a 
                href="{{ route('admin.orders') }}"
                wire:navigate
                class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
                {{ __('Back to Orders') }}
            </a>
        </div>
    @endif

    <!-- Print Script -->
    <script>
        window.addEventListener('print-packing-slip', event => {
            // Open print dialog for packing slip
            window.open('/admin/orders/' + event.detail.orderId + '/packing-slip', '_blank');
        });
    </script>
</div>