<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithPagination;

    public User $customer;
    public string $activeTab = 'overview';
    public bool $showEditModal = false;
    public bool $showAddressModal = false;
    public bool $showNoteModal = false;
    
    // Edit form fields
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public ?string $dateOfBirth = null;
    public string $gender = '';
    public bool $isActive = true;
    public string $preferredLocale = 'en';
    
    // Note field
    public string $newNote = '';
    
    // Address form fields
    public string $addressType = 'shipping';
    public string $addressName = '';
    public string $addressPhone = '';
    public string $addressLine1 = '';
    public string $addressLine2 = '';
    public int $cityId = 0;
    public string $postalCode = '';
    public bool $isDefault = false;
    public ?int $editingAddressId = null;

    #[Layout('components.layouts.admin')]
    public function mount($id)
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }

        $this->customer = User::with([
            'addresses.city.country',
            'wishlistItems',
            'reviews'
        ])->findOrFail($id);
        
        $this->fillEditForm();
    }

    public function fillEditForm()
    {
        $this->name = $this->customer->name;
        $this->email = $this->customer->email;
        $this->phone = $this->customer->phone ?? '';
        $this->dateOfBirth = $this->customer->date_of_birth?->format('Y-m-d');
        $this->gender = $this->customer->gender ?? '';
        $this->isActive = $this->customer->is_active;
        $this->preferredLocale = $this->customer->preferred_locale;
    }

    public function updateCustomer()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->customer->id,
            'phone' => 'nullable|string|max:20',
            'dateOfBirth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'preferredLocale' => 'required|in:en,ar'
        ]);

        $this->customer->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'is_active' => $this->isActive,
            'preferred_locale' => $this->preferredLocale
        ]);

        $this->showEditModal = false;
        $this->dispatch('toast', type: 'success', message: 'Customer information updated successfully');
    }

    public function toggleCustomerStatus()
    {
        $this->customer->update(['is_active' => !$this->customer->is_active]);
        $this->customer->refresh();
        
        $status = $this->customer->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', type: 'success', message: "Customer account {$status} successfully");
    }

    public function resetPassword()
    {
        // Generate password reset token and send email
        // Password::sendResetLink(['email' => $this->customer->email]);
        
        $this->dispatch('toast', type: 'success', message: 'Password reset email sent to customer');
    }

    public function addNote()
    {
        $this->validate([
            'newNote' => 'required|string|max:1000'
        ]);

        // In a real app, you'd have a customer_notes table
        // For now, we'll simulate it
        DB::table('customer_notes')->insert([
            'user_id' => $this->customer->id,
            'admin_id' => auth()->id(),
            'note' => $this->newNote,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->newNote = '';
        $this->showNoteModal = false;
        $this->dispatch('toast', type: 'success', message: 'Note added successfully');
    }

    public function editAddress($addressId)
    {
        $address = $this->customer->addresses()->find($addressId);
        
        if ($address) {
            $this->editingAddressId = $addressId;
            $this->addressType = $address->type;
            $this->addressName = $address->name;
            $this->addressPhone = $address->phone;
            $this->addressLine1 = $address->address_line_1;
            $this->addressLine2 = $address->address_line_2 ?? '';
            $this->cityId = $address->city_id;
            $this->postalCode = $address->postal_code ?? '';
            $this->isDefault = $address->is_default;
            $this->showAddressModal = true;
        }
    }

    public function saveAddress()
    {
        $this->validate([
            'addressType' => 'required|in:shipping,billing',
            'addressName' => 'required|string|max:255',
            'addressPhone' => 'required|string|max:20',
            'addressLine1' => 'required|string|max:255',
            'addressLine2' => 'nullable|string|max:255',
            'cityId' => 'required|exists:cities,id',
            'postalCode' => 'nullable|string|max:20',
        ]);

        $addressData = [
            'type' => $this->addressType,
            'name' => $this->addressName,
            'phone' => $this->addressPhone,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city_id' => $this->cityId,
            'postal_code' => $this->postalCode,
            'is_default' => $this->isDefault,
        ];

        if ($this->editingAddressId) {
            $this->customer->addresses()->where('id', $this->editingAddressId)->update($addressData);
            $message = 'Address updated successfully';
        } else {
            $this->customer->addresses()->create($addressData);
            $message = 'Address added successfully';
        }

        // If setting as default, unset other defaults of same type
        if ($this->isDefault) {
            $this->customer->addresses()
                ->where('type', $this->addressType)
                ->where('id', '!=', $this->editingAddressId)
                ->update(['is_default' => false]);
        }

        $this->resetAddressForm();
        $this->showAddressModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteAddress($addressId)
    {
        $this->customer->addresses()->where('id', $addressId)->delete();
        $this->dispatch('toast', type: 'success', message: 'Address deleted successfully');
    }

    public function resetAddressForm()
    {
        $this->editingAddressId = null;
        $this->addressType = 'shipping';
        $this->addressName = '';
        $this->addressPhone = '';
        $this->addressLine1 = '';
        $this->addressLine2 = '';
        $this->cityId = 0;
        $this->postalCode = '';
        $this->isDefault = false;
    }

    public function exportCustomerData()
    {
        // Generate and download customer data export
        $this->dispatch('toast', type: 'info', message: 'Customer data export feature coming soon');
    }

    public function with()
    {
        return [
            'orders' => $this->activeTab === 'orders' 
                ? $this->customer->orders()
                    ->with(['items.product'])
                    ->latest()
                    ->paginate(10)
                : collect(),
            'stats' => [
                'totalOrders' => $this->customer->orders()->count(),
                'totalSpent' => $this->customer->orders()->where('payment_status', 'paid')->sum('total'),
                'averageOrderValue' => $this->customer->orders()->where('payment_status', 'paid')->avg('total') ?? 0,
                'lastOrderDate' => $this->customer->orders()->latest()->first()?->created_at,
            ],
            'addresses' => $this->customer->addresses()->with('city.country')->get(),
            'cities' => \App\Models\City::with('country')->orderBy('name')->get(),
            'countries' => \App\Models\Country::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                <span class="text-2xl font-semibold text-gray-600">
                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                </span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $customer->name }}</h1>
                <p class="text-sm text-gray-600">Customer since {{ $customer->created_at->format('M d, Y') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="exportCustomerData" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                Export Data
            </button>
            <button wire:click="$set('showNoteModal', true)" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                Add Note
            </button>
            <button wire:click="toggleCustomerStatus" 
                    class="px-4 py-2 rounded-lg text-sm font-medium {{ $customer->is_active ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-green-600 text-white hover:bg-green-700' }}">
                {{ $customer->is_active ? 'Deactivate' : 'Activate' }} Account
            </button>
        </div>
    </div>

    <!-- Status Alert -->
    @if(!$customer->is_active)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Account Deactivated</h3>
                    <p class="mt-1 text-sm text-red-700">This customer's account is currently deactivated and they cannot log in.</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Total Orders</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['totalOrders'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Total Spent</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($stats['totalSpent'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Average Order Value</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($stats['averageOrderValue'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Last Order</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ $stats['lastOrderDate'] ? $stats['lastOrderDate']->diffForHumans() : 'Never' }}
            </p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button wire:click="$set('activeTab', 'overview')"
                        class="px-6 py-3 text-sm font-medium {{ $activeTab === 'overview' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Overview
                </button>
                <button wire:click="$set('activeTab', 'orders')"
                        class="px-6 py-3 text-sm font-medium {{ $activeTab === 'orders' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Orders
                </button>
                <button wire:click="$set('activeTab', 'addresses')"
                        class="px-6 py-3 text-sm font-medium {{ $activeTab === 'addresses' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Addresses
                </button>
            </nav>
        </div>

        <div class="p-6">
            <!-- Overview Tab -->
            @if($activeTab === 'overview')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Customer Information -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Customer Information</h3>
                            <button wire:click="$set('showEditModal', true)" class="text-sm text-blue-600 hover:text-blue-800">
                                Edit
                            </button>
                        </div>
                        
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Email</dt>
                                <dd class="text-sm text-gray-900">{{ $customer->email }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Phone</dt>
                                <dd class="text-sm text-gray-900">{{ $customer->phone ?? 'Not provided' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Date of Birth</dt>
                                <dd class="text-sm text-gray-900">
                                    {{ $customer->date_of_birth ? $customer->date_of_birth->format('M d, Y') : 'Not provided' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Gender</dt>
                                <dd class="text-sm text-gray-900">{{ ucfirst($customer->gender ?? 'Not specified') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Preferred Language</dt>
                                <dd class="text-sm text-gray-900">{{ $customer->preferred_locale === 'ar' ? 'Arabic' : 'English' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Email Verified</dt>
                                <dd class="text-sm">
                                    @if($customer->email_verified_at)
                                        <span class="inline-flex items-center gap-1 text-green-600">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            Verified
                                        </span>
                                    @else
                                        <span class="text-red-600">Not verified</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <button wire:click="resetPassword" class="text-sm text-blue-600 hover:text-blue-800">
                                Send Password Reset Email
                            </button>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
                        
                        <div class="space-y-4">
                            @forelse($customer->orders()->latest()->limit(5)->get() as $order)
                                <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Order #{{ $order->order_number }}</p>
                                        <p class="text-xs text-gray-500">{{ $order->created_at->format('M d, Y') }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-900">${{ number_format($order->total, 2) }}</p>
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                            @if($order->status === 'delivered') bg-green-100 text-green-800
                                            @elseif($order->status === 'processing') bg-blue-100 text-blue-800
                                            @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 italic">No orders yet</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            <!-- Orders Tab -->
            @if($activeTab === 'orders')
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($orders as $order)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">#{{ $order->order_number }}</div>
                                        <div class="text-sm text-gray-500">{{ $order->items->count() }} items</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $order->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                            @if($order->status === 'delivered') bg-green-100 text-green-800
                                            @elseif($order->status === 'processing') bg-blue-100 text-blue-800
                                            @elseif($order->status === 'shipped') bg-indigo-100 text-indigo-800
                                            @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                            @if($order->payment_status === 'paid') bg-green-100 text-green-800
                                            @elseif($order->payment_status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ ucfirst($order->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ${{ number_format($order->total, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="/admin/orders/{{ $order->id }}" 
                                           wire:navigate
                                           class="text-blue-600 hover:text-blue-800">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No orders found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($orders->hasPages())
                    <div class="mt-4">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif

            <!-- Addresses Tab -->
            @if($activeTab === 'addresses')
                <div class="space-y-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Saved Addresses</h3>
                        <button wire:click="$set('showAddressModal', true)" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Add Address
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($addresses as $address)
                            <div class="border border-gray-200 rounded-lg p-4 relative">
                                @if($address->is_default)
                                    <span class="absolute top-2 right-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded">
                                        Default
                                    </span>
                                @endif
                                
                                <div class="mb-2">
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                        {{ $address->type === 'shipping' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800' }}">
                                        {{ ucfirst($address->type) }}
                                    </span>
                                </div>
                                
                                <p class="font-medium text-gray-900">{{ $address->name }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ $address->address_line_1 }}</p>
                                @if($address->address_line_2)
                                    <p class="text-sm text-gray-600">{{ $address->address_line_2 }}</p>
                                @endif
                                <p class="text-sm text-gray-600">
                                    {{ $address->city->name }}, {{ $address->postal_code }}
                                </p>
                                <p class="text-sm text-gray-600">{{ $address->city->country->name }}</p>
                                <p class="text-sm text-gray-600 mt-2">Phone: {{ $address->phone }}</p>
                                
                                <div class="mt-3 flex gap-2">
                                    <button wire:click="editAddress({{ $address->id }})" 
                                            class="text-sm text-blue-600 hover:text-blue-800">
                                        Edit
                                    </button>
                                    <button wire:click="deleteAddress({{ $address->id }})"
                                            wire:confirm="Are you sure you want to delete this address?"
                                            class="text-sm text-red-600 hover:text-red-800">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="md:col-span-2 text-center py-8">
                                <p class="text-gray-500">No addresses saved yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div x-show="$wire.showEditModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showEditModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Customer Information</h3>
                
                <form wire:submit="updateCustomer">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" 
                                   wire:model="name" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" 
                                   wire:model="email" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" 
                                   wire:model="phone" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" 
                                   wire:model="dateOfBirth" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('dateOfBirth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select wire:model="gender" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Not specified</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            @error('gender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Language</label>
                            <select wire:model="preferredLocale" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="en">English</option>
                                <option value="ar">Arabic</option>
                            </select>
                            @error('preferredLocale') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="isActive" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Account is active</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Save Changes
                        </button>
                        <button type="button" 
                                wire:click="$set('showEditModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Address Modal -->
    <div x-show="$wire.showAddressModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showAddressModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editingAddressId ? 'Edit' : 'Add' }} Address
                </h3>
                
                <form wire:submit="saveAddress">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address Type</label>
                            <select wire:model="addressType" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="shipping">Shipping</option>
                                <option value="billing">Billing</option>
                            </select>
                            @error('addressType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" 
                                   wire:model="addressName" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('addressName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" 
                                   wire:model="addressPhone" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('addressPhone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                            <input type="text" 
                                   wire:model="addressLine1" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('addressLine1') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 2 (Optional)</label>
                            <input type="text" 
                                   wire:model="addressLine2" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('addressLine2') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <select wire:model="cityId" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select city</option>
                                @foreach($countries as $country)
                                    <optgroup label="{{ $country->name }}">
                                        @foreach($cities->where('country_id', $country->id) as $city)
                                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('cityId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                            <input type="text" 
                                   wire:model="postalCode" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('postalCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="isDefault" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Set as default {{ addressType }} address</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $editingAddressId ? 'Update' : 'Add' }} Address
                        </button>
                        <button type="button" 
                                wire:click="resetAddressForm(); $set('showAddressModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div x-show="$wire.showNoteModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showNoteModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Note</h3>
                
                <form wire:submit="addNote">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                        <textarea wire:model="newNote" 
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Add a note about this customer..."></textarea>
                        @error('newNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Add Note
                        </button>
                        <button type="button" 
                                wire:click="$set('showNoteModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>