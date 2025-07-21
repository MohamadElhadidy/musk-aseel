<?php
// resources/views/livewire/currency-switcher.blade.php
use Livewire\Volt\Component;
use App\Models\Currency;

new class extends Component
{
    public $currencies;
    public $currentCurrency;
    public bool $showDropdown = false;

    public function mount()
    {
        $this->currencies = Currency::active()->get();
        $this->currentCurrency = $this->currencies->where('code', session('currency', 'USD'))->first() 
                                ?? $this->currencies->where('is_default', true)->first();
    }

    public function switchCurrency($code)
    {
        $currency = $this->currencies->where('code', $code)->first();
        
        if ($currency) {
            session(['currency' => $code, 'currency_id' => $currency->id]);
            
            // Refresh the page to update prices
            $this->redirect(request()->header('Referer'), navigate: true);
        }
    }
}; ?>

<div class="relative" x-data="{ open: @entangle('showDropdown') }">
    <!-- Currency Button -->
    <button 
        @click="open = !open"
        @click.away="open = false"
        class="flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition"
    >
        <span class="text-sm font-medium">{{ $currentCurrency->code }} ({{ $currentCurrency->symbol }})</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- Currency Dropdown -->
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
    >
        <div class="py-1">
            @foreach($currencies as $currency)
                <button
                    wire:click="switchCurrency('{{ $currency->code }}')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 {{ $currency->code === $currentCurrency->code ? 'bg-gray-50 font-semibold' : '' }}"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ $currency->code }}</span>
                            <span class="text-gray-500 ml-1">{{ $currency->symbol }}</span>
                        </div>
                        <span class="text-gray-600">{{ $currency->name }}</span>
                        @if($currency->code === $currentCurrency->code)
                            <svg class="w-4 h-4 text-green-600 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </div>
</div>