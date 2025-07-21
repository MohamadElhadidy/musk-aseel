<?php 
namespace App\Livewire\Admin\Settings;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Currencies extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $editMode = false;
    public bool $showRateUpdateModal = false;
    
    // Form fields
    public ?int $currencyId = null;
    public string $code = '';
    public string $name = '';
    public string $symbol = '';
    public float $exchange_rate = 1.0000;
    public bool $is_active = true;
    public bool $is_default = false;
    
    // Rate update
    public string $rateUpdateMethod = 'manual';
    public string $apiProvider = 'exchangerate-api';
    public string $apiKey = '';
    
    // Filters
    public string $search = '';
    public string $filter = '';

    protected $rules = [
        'code' => 'required|string|size:3|unique:currencies,code',
        'name' => 'required|string|max:255',
        'symbol' => 'required|string|max:10',
        'exchange_rate' => 'required|numeric|min:0.0001',
    ];

    public function createCurrency()
    {
        $this->reset(['currencyId', 'code', 'name', 'symbol', 'exchange_rate', 'is_active', 'is_default']);
        $this->exchange_rate = 1.0000;
        $this->editMode = false;
        $this->showModal = true;
    }

    public function editCurrency($id)
    {
        $currency = Currency::findOrFail($id);
        
        $this->currencyId = $currency->id;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->symbol = $currency->symbol;
        $this->exchange_rate = $currency->exchange_rate;
        $this->is_active = $currency->is_active;
        $this->is_default = $currency->is_default;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        if ($this->editMode) {
            $this->rules['code'] = 'required|string|size:3|unique:currencies,code,' . $this->currencyId;
        }
        
        $this->validate();

        $data = [
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'symbol' => $this->symbol,
            'exchange_rate' => $this->exchange_rate,
            'is_active' => $this->is_active,
        ];

        if ($this->is_default) {
            Currency::where('is_default', true)->update(['is_default' => false]);
            $data['is_default'] = true;
            $data['exchange_rate'] = 1.0000; // Default currency always has rate 1
        }

        if ($this->editMode) {
            Currency::find($this->currencyId)->update($data);
            $message = 'Currency updated successfully';
        } else {
            Currency::create($data);
            $message = 'Currency created successfully';
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function updateRates()
    {
        $this->showRateUpdateModal = true;
        $this->apiKey = config('services.exchange_rates.api_key', '');
    }

    public function performRateUpdate()
    {
        $this->validate([
            'apiKey' => 'required_if:rateUpdateMethod,api|string',
        ]);

        if ($this->rateUpdateMethod === 'api') {
            $this->updateRatesFromApi();
        }
        
        $this->showRateUpdateModal = false;
    }

    protected function updateRatesFromApi()
    {
        $defaultCurrency = Currency::where('is_default', true)->first();
        
        if (!$defaultCurrency) {
            $this->dispatch('toast', type: 'error', message: 'No default currency set');
            return;
        }

        try {
            $rates = $this->fetchExchangeRates($defaultCurrency->code);
            
            foreach (Currency::where('code', '!=', $defaultCurrency->code)->get() as $currency) {
                if (isset($rates[$currency->code])) {
                    $currency->update(['exchange_rate' => $rates[$currency->code]]);
                }
            }
            
            Cache::put('last_rate_update', now(), 86400);
            $this->dispatch('toast', type: 'success', message: 'Exchange rates updated successfully');
            
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to update rates: ' . $e->getMessage());
        }
    }

    protected function fetchExchangeRates($baseCurrency)
    {
        switch ($this->apiProvider) {
            case 'exchangerate-api':
                $response = Http::get("https://v6.exchangerate-api.com/v6/{$this->apiKey}/latest/{$baseCurrency}");
                if ($response->successful()) {
                    return $response->json()['conversion_rates'];
                }
                break;
                
            case 'fixer':
                $response = Http::get("https://api.fixer.io/latest", [
                    'access_key' => $this->apiKey,
                    'base' => $baseCurrency
                ]);
                if ($response->successful()) {
                    return $response->json()['rates'];
                }
                break;
                
            case 'currencylayer':
                $response = Http::get("https://api.currencylayer.com/live", [
                    'access_key' => $this->apiKey,
                    'source' => $baseCurrency
                ]);
                if ($response->successful()) {
                    $quotes = $response->json()['quotes'];
                    $rates = [];
                    foreach ($quotes as $key => $value) {
                        $code = substr($key, 3);
                        $rates[$code] = $value;
                    }
                    return $rates;
                }
                break;
        }
        
        throw new \Exception('Failed to fetch exchange rates');
    }

    public function toggleStatus($id)
    {
        $currency = Currency::findOrFail($id);
        
        if ($currency->is_default && $currency->is_active) {
            $this->dispatch('toast', type: 'error', message: 'Cannot deactivate the default currency');
            return;
        }
        
        $currency->update(['is_active' => !$currency->is_active]);
        $this->dispatch('toast', type: 'success', message: 'Currency status updated');
    }

    public function setDefault($id)
    {
        $currency = Currency::findOrFail($id);
        
        if (!$currency->is_active) {
            $this->dispatch('toast', type: 'error', message: 'Please activate the currency first');
            return;
        }
        
        Currency::where('is_default', true)->update(['is_default' => false]);
        $currency->update([
            'is_default' => true,
            'exchange_rate' => 1.0000
        ]);
        
        // Recalculate all rates based on new default
        $this->recalculateRates($currency);
        
        $this->dispatch('toast', type: 'success', message: 'Default currency updated');
    }

    protected function recalculateRates(Currency $newDefault)
    {
        // This would need to fetch new rates with the new base currency
        // For now, we'll just notify that rates need to be updated
        Cache::forget('last_rate_update');
    }

    public function deleteCurrency($id)
    {
        $currency = Currency::findOrFail($id);
        
        if ($currency->is_default) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete the default currency');
            return;
        }
        
        // Check if currency is used in orders
        if (Order::where('currency_code', $currency->code)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete currency used in orders');
            return;
        }
        
        $currency->delete();
        $this->dispatch('toast', type: 'success', message: 'Currency deleted successfully');
    }

    public function render()
    {
        $query = Currency::query();
        
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%")
                  ->orWhere('symbol', 'like', "%{$this->search}%");
            });
        }
        
        if ($this->filter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->filter === 'inactive') {
            $query->where('is_active', false);
        }
        
        return view('livewire.admin.settings.currencies', [
            'currencies' => $query->paginate(10),
            'lastUpdate' => Cache::get('last_rate_update')
        ])->layout('layouts.admin');
    }
}

?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Currencies</h1>
            <p class="text-sm text-gray-600 mt-1">Manage available currencies and exchange rates</p>
        </div>
        <div class="flex space-x-3">
            <button wire:click="updateRates" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Update Rates
            </button>
            <button wire:click="createCurrency" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Add Currency
            </button>
        </div>
    </div>

    @if($lastUpdate)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-800">
                Exchange rates last updated: {{ $lastUpdate->diffForHumans() }}
            </p>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input 
                    type="text" 
                    wire:model.live="search"
                    placeholder="Search currencies..."
                    class="w-full px-3 py-2 border rounded-lg"
                >
            </div>
            <div>
                <select wire:model.live="filter" class="w-full px-3 py-2 border rounded-lg">
                    <option value="">All Currencies</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Currencies Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Currency</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Symbol</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exchange Rate</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($currencies as $currency)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs bg-gray-100 rounded font-mono">{{ $currency->code }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $currency->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold">{{ $currency->symbol }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ number_format($currency->exchange_rate, 4) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button wire:click="toggleStatus({{ $currency->id }})" class="relative inline-flex h-6 w-11 items-center rounded-full {{ $currency->is_active ? 'bg-green-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $currency->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($currency->is_default)
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Default</span>
                            @else
                                <button wire:click="setDefault({{ $currency->id }})" class="text-blue-600 hover:text-blue-800 text-sm">
                                    Set Default
                                </button>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button wire:click="editCurrency({{ $currency->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                Edit
                            </button>
                            @unless($currency->is_default)
                                <button wire:click="deleteCurrency({{ $currency->id }})" onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" class="text-red-600 hover:text-red-900">
                                    Delete
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            No currencies found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="px-6 py-4 border-t">
            {{ $currencies->links() }}
        </div>
    </div>

    <!-- Currency Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">
                    {{ $editMode ? 'Edit Currency' : 'Add New Currency' }}
                </h3>
                
                <form wire:submit="save">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency Code</label>
                            <input 
                                type="text" 
                                wire:model="code" 
                                placeholder="USD, EUR, EGP"
                                maxlength="3"
                                class="w-full px-3 py-2 border rounded-lg uppercase @error('code') border-red-500 @enderror"
                                {{ $editMode ? 'disabled' : '' }}
                            >
                            @error('code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency Name</label>
                            <input 
                                type="text" 
                                wire:model="name"
                                placeholder="US Dollar"
                                class="w-full px-3 py-2 border rounded-lg @error('name') border-red-500 @enderror"
                            >
                            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
                            <input 
                                type="text" 
                                wire:model="symbol"
                                placeholder="$"
                                class="w-full px-3 py-2 border rounded-lg @error('symbol') border-red-500 @enderror"
                            >
                            @error('symbol') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exchange Rate</label>
                            <input 
                                type="number" 
                                step="0.0001"
                                wire:model="exchange_rate"
                                class="w-full px-3 py-2 border rounded-lg @error('exchange_rate') border-red-500 @enderror"
                                {{ $is_default ? 'disabled' : '' }}
                            >
                            @error('exchange_rate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            <p class="text-xs text-gray-500 mt-1">Rate relative to default currency</p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_active" class="rounded">
                                <span class="ml-2 text-sm">Active</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_default" class="rounded">
                                <span class="ml-2 text-sm">Set as Default</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ $editMode ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Rate Update Modal -->
    @if($showRateUpdateModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-lg w-full">
                <h3 class="text-lg font-medium mb-4">Update Exchange Rates</h3>
                
                <form wire:submit="performRateUpdate">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Update Method</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" wire:model="rateUpdateMethod" value="manual" class="mr-2">
                                    <span>Manual Update</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" wire:model="rateUpdateMethod" value="api" class="mr-2">
                                    <span>API Update</span>
                                </label>
                            </div>
                        </div>
                        
                        @if($rateUpdateMethod === 'api')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">API Provider</label>
                                <select wire:model="apiProvider" class="w-full px-3 py-2 border rounded-lg">
                                    <option value="exchangerate-api">ExchangeRate-API</option>
                                    <option value="fixer">Fixer.io</option>
                                    <option value="currencylayer">CurrencyLayer</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                                <input 
                                    type="text" 
                                    wire:model="apiKey"
                                    placeholder="Enter your API key"
                                    class="w-full px-3 py-2 border rounded-lg @error('apiKey') border-red-500 @enderror"
                                >
                                @error('apiKey') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showRateUpdateModal', false)" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Update Rates
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>