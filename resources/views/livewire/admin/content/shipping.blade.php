<?php

use Livewire\Volt\Component;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Country;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination;

    public string $activeTab = 'methods';
    public bool $showMethodModal = false;
    public bool $showZoneModal = false;
    public bool $editMode = false;
    
    // Shipping Method fields
    public ?int $methodId = null;
    public string $methodName = '';
    public string $methodNameAr = '';
    public string $methodDescription = '';
    public string $methodDescriptionAr = '';
    public string $methodType = 'flat_rate';
    public float $baseRate = 0;
    public float $perKgRate = 0;
    public float $perItemRate = 0;
    public float $minOrderAmount = 0;
    public float $maxOrderAmount = 0;
    public int $estimatedDays = 3;
    public bool $methodIsActive = true;
    
    // Shipping Zone fields
    public ?int $zoneId = null;
    public string $zoneName = '';
    public string $zoneNameAr = '';
    public array $selectedCountries = [];
    public float $zoneRate = 0;
    public int $zoneEstimatedDays = 5;
    public bool $zoneIsActive = true;

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }
    }

    // Shipping Methods
    public function createMethod()
    {
        $this->reset(['methodId', 'methodName', 'methodNameAr', 'methodDescription', 
                     'methodDescriptionAr', 'methodType', 'baseRate', 'perKgRate', 
                     'perItemRate', 'minOrderAmount', 'maxOrderAmount', 
                     'estimatedDays', 'methodIsActive']);
        $this->editMode = false;
        $this->showMethodModal = true;
    }

    public function editMethod($id)
    {
        $method = ShippingMethod::findOrFail($id);
        
        $this->methodId = $method->id;
        $this->methodName = $method->getTranslation('name', 'en') ?? '';
        $this->methodNameAr = $method->getTranslation('name', 'ar') ?? '';
        $this->methodDescription = $method->getTranslation('description', 'en') ?? '';
        $this->methodDescriptionAr = $method->getTranslation('description', 'ar') ?? '';
        $this->methodType = $method->type;
        $this->baseRate = $method->base_rate;
        $this->perKgRate = $method->per_kg_rate;
        $this->perItemRate = $method->per_item_rate;
        $this->minOrderAmount = $method->min_order_amount;
        $this->maxOrderAmount = $method->max_order_amount;
        $this->estimatedDays = $method->estimated_days;
        $this->methodIsActive = $method->is_active;
        
        $this->editMode = true;
        $this->showMethodModal = true;
    }

    public function saveMethod()
    {
        $this->validate([
            'methodName' => 'required|string|max:255',
            'methodNameAr' => 'required|string|max:255',
            'methodDescription' => 'nullable|string|max:500',
            'methodDescriptionAr' => 'nullable|string|max:500',
            'methodType' => 'required|in:flat_rate,per_weight,per_item,free',
            'baseRate' => 'required|numeric|min:0',
            'perKgRate' => 'required_if:methodType,per_weight|numeric|min:0',
            'perItemRate' => 'required_if:methodType,per_item|numeric|min:0',
            'minOrderAmount' => 'required|numeric|min:0',
            'maxOrderAmount' => 'required|numeric|min:0|gte:minOrderAmount',
            'estimatedDays' => 'required|integer|min:0',
        ]);

        $data = [
            'name' => ['en' => $this->methodName, 'ar' => $this->methodNameAr],
            'description' => ['en' => $this->methodDescription, 'ar' => $this->methodDescriptionAr],
            'type' => $this->methodType,
            'base_rate' => $this->baseRate,
            'per_kg_rate' => $this->perKgRate,
            'per_item_rate' => $this->perItemRate,
            'min_order_amount' => $this->minOrderAmount,
            'max_order_amount' => $this->maxOrderAmount,
            'estimated_days' => $this->estimatedDays,
            'is_active' => $this->methodIsActive,
        ];

        if ($this->editMode) {
            ShippingMethod::find($this->methodId)->update($data);
            $message = 'Shipping method updated successfully';
        } else {
            ShippingMethod::create($data);
            $message = 'Shipping method created successfully';
        }

        $this->showMethodModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteMethod($id)
    {
        ShippingMethod::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Shipping method deleted successfully');
    }

    public function toggleMethodStatus($id)
    {
        $method = ShippingMethod::findOrFail($id);
        $method->update(['is_active' => !$method->is_active]);
        
        $status = $method->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', type: 'success', message: "Shipping method {$status} successfully");
    }

    // Shipping Zones
    public function createZone()
    {
        $this->reset(['zoneId', 'zoneName', 'zoneNameAr', 'selectedCountries',
                     'zoneRate', 'zoneEstimatedDays', 'zoneIsActive']);
        $this->editMode = false;
        $this->showZoneModal = true;
    }

    public function editZone($id)
    {
        $zone = ShippingZone::findOrFail($id);
        
        $this->zoneId = $zone->id;
        $this->zoneName = $zone->getTranslation('name', 'en') ?? '';
        $this->zoneNameAr = $zone->getTranslation('name', 'ar') ?? '';
        $this->selectedCountries = $zone->countries->pluck('id')->toArray();
        $this->zoneRate = $zone->rate;
        $this->zoneEstimatedDays = $zone->estimated_days;
        $this->zoneIsActive = $zone->is_active;
        
        $this->editMode = true;
        $this->showZoneModal = true;
    }

    public function saveZone()
    {
        $this->validate([
            'zoneName' => 'required|string|max:255',
            'zoneNameAr' => 'required|string|max:255',
            'selectedCountries' => 'required|array|min:1',
            'selectedCountries.*' => 'exists:countries,id',
            'zoneRate' => 'required|numeric|min:0',
            'zoneEstimatedDays' => 'required|integer|min:0',
        ]);

        $data = [
            'name' => ['en' => $this->zoneName, 'ar' => $this->zoneNameAr],
            'rate' => $this->zoneRate,
            'estimated_days' => $this->zoneEstimatedDays,
            'is_active' => $this->zoneIsActive,
        ];

        if ($this->editMode) {
            $zone = ShippingZone::find($this->zoneId);
            $zone->update($data);
            $message = 'Shipping zone updated successfully';
        } else {
            $zone = ShippingZone::create($data);
            $message = 'Shipping zone created successfully';
        }

        // Sync countries
        $zone->countries()->sync($this->selectedCountries);

        $this->showZoneModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteZone($id)
    {
        ShippingZone::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Shipping zone deleted successfully');
    }

    public function toggleZoneStatus($id)
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->update(['is_active' => !$zone->is_active]);
        
        $status = $zone->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', type: 'success', message: "Shipping zone {$status} successfully");
    }

    public function with()
    {
        return [
            'methods' => $this->activeTab === 'methods' 
                ? ShippingMethod::paginate(10) 
                : collect(),
            'zones' => $this->activeTab === 'zones' 
                ? ShippingZone::with('countries')->paginate(10) 
                : collect(),
            'countries' => Country::orderBy('name')->get(),
            'methodTypes' => [
                'flat_rate' => 'Flat Rate',
                'per_weight' => 'Per Weight (kg)',
                'per_item' => 'Per Item',
                'free' => 'Free Shipping',
            ]
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Shipping Settings</h1>
        <p class="text-sm text-gray-600 mt-1">Configure shipping methods and zones</p>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button wire:click="$set('activeTab', 'methods')"
                        class="px-6 py-3 text-sm font-medium {{ $activeTab === 'methods' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Shipping Methods
                </button>
                <button wire:click="$set('activeTab', 'zones')"
                        class="px-6 py-3 text-sm font-medium {{ $activeTab === 'zones' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Shipping Zones
                </button>
            </nav>
        </div>

        <div class="p-6">
            @if($activeTab === 'methods')
                <!-- Shipping Methods -->
                <div class="mb-4 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Shipping Methods</h2>
                    <button wire:click="createMethod" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Method
                    </button>
                </div>

                <div class="space-y-4">
                    @forelse($methods as $method)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900">{{ $method->getTranslation('name', 'en') }}</h3>
                                    @if($method->description)
                                        <p class="text-sm text-gray-600 mt-1">{{ $method->getTranslation('description', 'en') }}</p>
                                    @endif
                                    
                                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-500">Type:</span>
                                            <span class="ml-1 font-medium">{{ $methodTypes[$method->type] }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Base Rate:</span>
                                            <span class="ml-1 font-medium">${{ number_format($method->base_rate, 2) }}</span>
                                        </div>
                                        @if($method->type === 'per_weight')
                                            <div>
                                                <span class="text-gray-500">Per kg:</span>
                                                <span class="ml-1 font-medium">${{ number_format($method->per_kg_rate, 2) }}</span>
                                            </div>
                                        @elseif($method->type === 'per_item')
                                            <div>
                                                <span class="text-gray-500">Per Item:</span>
                                                <span class="ml-1 font-medium">${{ number_format($method->per_item_rate, 2) }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <span class="text-gray-500">Est. Days:</span>
                                            <span class="ml-1 font-medium">{{ $method->estimated_days }}</span>
                                        </div>
                                    </div>
                                    
                                    @if($method->min_order_amount > 0 || $method->max_order_amount > 0)
                                        <div class="mt-2 text-sm text-gray-500">
                                            Order Amount: 
                                            @if($method->min_order_amount > 0)
                                                Min ${{ number_format($method->min_order_amount, 2) }}
                                            @endif
                                            @if($method->max_order_amount > 0)
                                                - Max ${{ number_format($method->max_order_amount, 2) }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex items-center gap-3 ml-4">
                                    <button wire:click="toggleMethodStatus({{ $method->id }})" 
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $method->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $method->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                    
                                    <button wire:click="editMethod({{ $method->id }})" 
                                            class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    
                                    <button wire:click="deleteMethod({{ $method->id }})"
                                            wire:confirm="Are you sure you want to delete this shipping method?"
                                            class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            <p class="text-gray-500 mt-2">No shipping methods found</p>
                        </div>
                    @endforelse
                </div>

                @if($methods->hasPages())
                    <div class="mt-4">
                        {{ $methods->links() }}
                    </div>
                @endif
            @else
                <!-- Shipping Zones -->
                <div class="mb-4 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Shipping Zones</h2>
                    <button wire:click="createZone" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Zone
                    </button>
                </div>

                <div class="space-y-4">
                    @forelse($zones as $zone)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900">{{ $zone->getTranslation('name', 'en') }}</h3>
                                    
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($zone->countries as $country)
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                                {{ $country->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                    
                                    <div class="mt-3 flex items-center gap-4 text-sm text-gray-500">
                                        <span>Rate: ${{ number_format($zone->rate, 2) }}</span>
                                        <span>Est. Days: {{ $zone->estimated_days }}</span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3 ml-4">
                                    <button wire:click="toggleZoneStatus({{ $zone->id }})" 
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $zone->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $zone->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                    
                                    <button wire:click="editZone({{ $zone->id }})" 
                                            class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    
                                    <button wire:click="deleteZone({{ $zone->id }})"
                                            wire:confirm="Are you sure you want to delete this shipping zone?"
                                            class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-gray-500 mt-2">No shipping zones found</p>
                        </div>
                    @endforelse
                </div>

                @if($zones->hasPages())
                    <div class="mt-4">
                        {{ $zones->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    <!-- Shipping Method Modal -->
    <div x-show="$wire.showMethodModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showMethodModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editMode ? 'Edit' : 'Create' }} Shipping Method
                </h3>
                
                <form wire:submit="saveMethod">
                    <div class="space-y-4">
                        <!-- Name -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name (English)</label>
                                <input type="text" 
                                       wire:model="methodName" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @error('methodName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name (Arabic)</label>
                                <input type="text" 
                                       wire:model="methodNameAr" 
                                       dir="rtl"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @error('methodNameAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                                <textarea wire:model="methodDescription" 
                                          rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                                @error('methodDescription') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description (Arabic)</label>
                                <textarea wire:model="methodDescriptionAr" 
                                          rows="2"
                                          dir="rtl"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                                @error('methodDescriptionAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Calculation Type</label>
                            <select wire:model="methodType" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @foreach($methodTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('methodType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Rates -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Base Rate</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" 
                                           wire:model="baseRate" 
                                           step="0.01"
                                           min="0"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                @error('baseRate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            @if($methodType === 'per_weight')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Per kg Rate</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" 
                                               wire:model="perKgRate" 
                                               step="0.01"
                                               min="0"
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    @error('perKgRate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @elseif($methodType === 'per_item')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Per Item Rate</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" 
                                               wire:model="perItemRate" 
                                               step="0.01"
                                               min="0"
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    @error('perItemRate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Days</label>
                                <input type="number" 
                                       wire:model="estimatedDays" 
                                       min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @error('estimatedDays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Order Amount Limits -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Order Amount</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" 
                                           wire:model="minOrderAmount" 
                                           step="0.01"
                                           min="0"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                @error('minOrderAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Order Amount</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" 
                                           wire:model="maxOrderAmount" 
                                           step="0.01"
                                           min="0"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                @error('maxOrderAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="methodIsActive" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $editMode ? 'Update' : 'Create' }} Method
                        </button>
                        <button type="button" 
                                wire:click="$set('showMethodModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Shipping Zone Modal -->
    <div x-show="$wire.showZoneModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showZoneModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editMode ? 'Edit' : 'Create' }} Shipping Zone
                </h3>
                
                <form wire:submit="saveZone">
                    <div class="space-y-4">
                        <!-- Name -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Zone Name (English)</label>
                                <input type="text" 
                                       wire:model="zoneName" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @error('zoneName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Zone Name (Arabic)</label>
                                <input type="text" 
                                       wire:model="zoneNameAr" 
                                       dir="rtl"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @error('zoneNameAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Countries -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Countries</label>
                            <div class="border border-gray-300 rounded-lg p-3 max-h-60 overflow-y-auto">
                                @foreach($countries as $country)
                                    <label class="flex items-center p-2 hover:bg-gray-50 rounded">
                                        <input type="checkbox" 
                                               wire:model="selectedCountries" 
                                               value="{{ $country->id }}"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">{{ $country->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedCountries') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Rate and Days -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Shipping Rate</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" 
                                           wire:model="zoneRate" 
                                           step="0.01"
                                           min="0"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                @error('zoneRate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Delivery Days</label>
                                <input type="number" 
                                       wire:model="zoneEstimatedDays" 
                                       min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @error('zoneEstimatedDays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="zoneIsActive" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $editMode ? 'Update' : 'Create' }} Zone
                        </button>
                        <button type="button" 
                                wire:click="$set('showZoneModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>