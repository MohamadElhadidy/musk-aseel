<?php

use Livewire\Volt\Component;
use App\Models\UserAddress;
use App\Models\Country;
use App\Models\City;

new class extends Component
{
    public $addresses;
    public $countries;
    public $cities = [];
    
    // Form fields
    public ?int $editingAddressId = null;
    public string $type = 'shipping';
    public string $name = '';
    public string $phone = '';
    public string $address_line_1 = '';
    public string $address_line_2 = '';
    public ?int $country_id = null;
    public ?int $city_id = null;
    public string $postal_code = '';
    public bool $is_default = false;
    
    public bool $showForm = false;

    public function mount()
    {
        if (!auth()->check()) {
            $this->redirect('/login', navigate: true);
            return;
        }

        $this->loadAddresses();
        $this->countries = Country::active()->get();
    }

    public function loadAddresses()
    {
        $this->addresses = auth()->user()->addresses()
            ->with('city.country')
            ->get();
    }

    public function updatedCountryId()
    {
        $this->cities = City::where('country_id', $this->country_id)
            ->active()
            ->get();
        $this->city_id = null;
    }

    public function createAddress()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editAddress($addressId)
    {
        $address = $this->addresses->find($addressId);
        
        if (!$address) {
            return;
        }

        $this->editingAddressId = $address->id;
        $this->type = $address->type;
        $this->name = $address->name;
        $this->phone = $address->phone;
        $this->address_line_1 = $address->address_line_1;
        $this->address_line_2 = $address->address_line_2 ?? '';
        $this->country_id = $address->city->country_id;
        $this->updatedCountryId();
        $this->city_id = $address->city_id;
        $this->postal_code = $address->postal_code ?? '';
        $this->is_default = $address->is_default;
        
        $this->showForm = true;
    }

    public function saveAddress()
    {
        $this->validate([
            'type' => 'required|in:shipping,billing',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $data = [
            'type' => $this->type,
            'name' => $this->name,
            'phone' => $this->phone,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city_id' => $this->city_id,
            'postal_code' => $this->postal_code,
        ];

        if ($this->is_default) {
            // Remove default from other addresses of same type
            auth()->user()->addresses()
                ->where('type', $this->type)
                ->update(['is_default' => false]);
            
            $data['is_default'] = true;
        }

        if ($this->editingAddressId) {
            auth()->user()->addresses()
                ->where('id', $this->editingAddressId)
                ->update($data);
            
            $message = __('Address updated successfully');
        } else {
            auth()->user()->addresses()->create($data);
            $message = __('Address added successfully');
        }

        $this->loadAddresses();
        $this->resetForm();
        
        $this->dispatch('toast', 
            type: 'success',
            message: $message
        );
    }

    public function deleteAddress($addressId)
    {
        auth()->user()->addresses()
            ->where('id', $addressId)
            ->delete();

        $this->loadAddresses();
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Address deleted successfully')
        );
    }

    public function setDefault($addressId)
    {
        $address = auth()->user()->addresses()->find($addressId);
        
        if (!$address) {
            return;
        }

        // Remove default from other addresses of same type
        auth()->user()->addresses()
            ->where('type', $address->type)
            ->update(['is_default' => false]);

        // Set this as default
        $address->update(['is_default' => true]);

        $this->loadAddresses();
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Default address updated')
        );
    }

    public function resetForm()
    {
        $this->editingAddressId = null;
        $this->type = 'shipping';
        $this->name = auth()->user()->name;
        $this->phone = auth()->user()->phone ?? '';
        $this->address_line_1 = '';
        $this->address_line_2 = '';
        $this->country_id = null;
        $this->city_id = null;
        $this->postal_code = '';
        $this->is_default = false;
        $this->showForm = false;
        $this->cities = [];
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
                <!-- User Info -->
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-semibold text-blue-600">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <h3 class="font-semibold text-gray-900">{{ auth()->user()->name }}</h3>
                    <p class="text-sm text-gray-600">{{ auth()->user()->email }}</p>
                </div>

                <!-- Navigation -->
                <nav class="space-y-1">
                    <a href="/account" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        {{ __('Dashboard') }}
                    </a>

                    <a href="/account/orders" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        {{ __('Orders') }}
                    </a>

                    <a href="/account/addresses" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        {{ __('Addresses') }}
                    </a>

                    <a href="/wishlist" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        {{ __('Wishlist') }}
                    </a>

                    <a href="/account/profile" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        {{ __('Profile Settings') }}
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold text-gray-900">{{ __('Address Book') }}</h1>
                        @if(!$showForm)
                            <button 
                                wire:click="createAddress"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                            >
                                <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-2' : 'mr-2' }} -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                {{ __('Add New Address') }}
                            </button>
                        @endif
                    </div>
                </div>

                @if($showForm)
                    <!-- Address Form -->
                    <form wire:submit="saveAddress" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Address Type -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Address Type') }}</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center">
                                        <input 
                                            type="radio" 
                                            wire:model="type"
                                            value="shipping"
                                            class="text-blue-600"
                                        >
                                        <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }}">{{ __('Shipping Address') }}</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input 
                                            type="radio" 
                                            wire:model="type"
                                            value="billing"
                                            class="text-blue-600"
                                        >
                                        <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }}">{{ __('Billing Address') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Full Name') }} <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="name"
                                    wire:model="name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Phone') }} <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="tel" 
                                    id="phone"
                                    wire:model="phone"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Address Line 1 -->
                            <div class="md:col-span-2">
                                <label for="address_line_1" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Address Line 1') }} <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="address_line_1"
                                    wire:model="address_line_1"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('address_line_1')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Address Line 2 -->
                            <div class="md:col-span-2">
                                <label for="address_line_2" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Address Line 2') }}
                                </label>
                                <input 
                                    type="text" 
                                    id="address_line_2"
                                    wire:model="address_line_2"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>

                            <!-- Country -->
                            <div>
                                <label for="country_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Country') }} <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    id="country_id"
                                    wire:model.live="country_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                    <option value="">{{ __('Select Country') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- City -->
                            <div>
                                <label for="city_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('City') }} <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    id="city_id"
                                    wire:model="city_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                    <option value="">{{ __('Select City') }}</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                @error('city_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Postal Code -->
                            <div>
                                <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Postal Code') }}
                                </label>
                                <input 
                                    type="text" 
                                    id="postal_code"
                                    wire:model="postal_code"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>

                            <!-- Default Address -->
                            <div class="md:col-span-2">
                                <label class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        wire:model="is_default"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <span class="{{ app()->getLocale() === 'ar' ? 'mr-2' : 'ml-2' }} text-sm text-gray-700">
                                        {{ __('Set as default :type address', ['type' => $type === 'shipping' ? __('shipping') : __('billing')]) }}
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="mt-6 flex justify-end gap-3">
                            <button 
                                type="button"
                                wire:click="resetForm"
                                class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                            >
                                {{ $editingAddressId ? __('Update Address') : __('Save Address') }}
                            </button>
                        </div>
                    </form>
                @else
                    <!-- Address List -->
                    @if($addresses->count() > 0)
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($addresses as $address)
                                    <div class="border rounded-lg p-4 relative">
                                        @if($address->is_default)
                                            <span class="absolute top-2 {{ app()->getLocale() === 'ar' ? 'left-2' : 'right-2' }} inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ __('Default') }}
                                            </span>
                                        @endif

                                        <div class="mb-3">
                                            <h4 class="font-semibold text-gray-900">{{ $address->name }}</h4>
                                            <p class="text-sm text-gray-600">{{ $address->phone }}</p>
                                        </div>

                                        <address class="text-sm text-gray-600 not-italic mb-3">
                                            <p>{{ $address->address_line_1 }}</p>
                                            @if($address->address_line_2)
                                                <p>{{ $address->address_line_2 }}</p>
                                            @endif
                                            <p>{{ $address->city->name }}, {{ $address->city->country->name }}</p>
                                            @if($address->postal_code)
                                                <p>{{ $address->postal_code }}</p>
                                            @endif
                                        </address>

                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ $address->type === 'shipping' ? __('Shipping') : __('Billing') }}
                                            </span>
                                        </div>

                                        <div class="mt-4 flex gap-2">
                                            <button 
                                                wire:click="editAddress({{ $address->id }})"
                                                class="text-sm text-blue-600 hover:text-blue-700"
                                            >
                                                {{ __('Edit') }}
                                            </button>
                                            
                                            @if(!$address->is_default)
                                                <span class="text-gray-300">|</span>
                                                <button 
                                                    wire:click="setDefault({{ $address->id }})"
                                                    class="text-sm text-gray-600 hover:text-gray-700"
                                                >
                                                    {{ __('Set as Default') }}
                                                </button>
                                            @endif
                                            
                                            <span class="text-gray-300">|</span>
                                            <button 
                                                wire:click="deleteAddress({{ $address->id }})"
                                                wire:confirm="{{ __('Are you sure you want to delete this address?') }}"
                                                class="text-sm text-red-600 hover:text-red-700"
                                            >
                                                {{ __('Delete') }}
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="p-12 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No addresses saved') }}</h3>
                            <p class="text-gray-500 mb-6">{{ __('Add your shipping and billing addresses for faster checkout') }}</p>
                            <button 
                                wire:click="createAddress"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                            >
                                <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-2' : 'mr-2' }} -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                {{ __('Add Your First Address') }}
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>