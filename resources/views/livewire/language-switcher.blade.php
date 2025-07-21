<?php
// resources/views/livewire/language-switcher.blade.php
use Livewire\Volt\Component;
use App\Models\Language;

new class extends Component
{
    public $languages;
    public $currentLanguage;
    public bool $showDropdown = false;

    public function mount()
    {
        $this->languages = Language::active()->get();
        $this->currentLanguage = $this->languages->where('code', app()->getLocale())->first();
    }

    public function switchLanguage($code)
    {
        $language = $this->languages->where('code', $code)->first();
        
        if ($language) {
            session(['locale' => $code]);
            
            if (auth()->check()) {
                auth()->user()->update(['preferred_locale' => $code]);
            }
            
            // Refresh the page to apply new language
            $this->redirect(request()->header('Referer'), navigate: true);
        }
    }
}; ?>

<div class="relative" x-data="{ open: @entangle('showDropdown') }">
    <!-- Language Button -->
    <button 
        @click="open = !open"
        @click.away="open = false"
        class="flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
        </svg>
        <span class="text-sm">{{ $currentLanguage->native_name ?? $currentLanguage->name }}</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- Language Dropdown -->
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-48 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
    >
        <div class="py-1">
            @foreach($languages as $language)
                <button
                    wire:click="switchLanguage('{{ $language->code }}')"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 {{ $language->code === app()->getLocale() ? 'bg-gray-50 font-semibold' : '' }}"
                >
                    <div class="flex items-center justify-between">
                        <span>{{ $language->native_name }}</span>
                        @if($language->code === app()->getLocale())
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </div>
</div>
