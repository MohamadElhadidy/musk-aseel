<?php

use Livewire\Volt\Component;
use App\Models\Faq;

new class extends Component
{
    public $faqs;
    public $openItems = [];
    public string $searchQuery = '';

    public function mount()
    {
        $this->loadFaqs();
    }

    public function loadFaqs()
    {
        $query = Faq::active()->with('translations');
        
        if ($this->searchQuery) {
            $query->whereHas('translations', function ($q) {
                $q->where('locale', app()->getLocale())
                  ->where(function ($sq) {
                      $sq->where('question', 'like', "%{$this->searchQuery}%")
                         ->orWhere('answer', 'like', "%{$this->searchQuery}%");
                  });
            });
        }
        
        $this->faqs = $query->get();
    }

    public function toggleItem($id)
    {
        if (in_array($id, $this->openItems)) {
            $this->openItems = array_diff($this->openItems, [$id]);
        } else {
            $this->openItems[] = $id;
        }
    }

    public function updatedSearchQuery()
    {
        $this->loadFaqs();
        $this->openItems = [];
    }

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center mb-8">{{ __('Frequently Asked Questions') }}</h1>

    <!-- Search Bar -->
    <div class="max-w-2xl mx-auto mb-8">
        <div class="relative">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="searchQuery"
                placeholder="{{ __('Search FAQs...') }}"
                class="w-full px-4 py-3 {{ app()->getLocale() === 'ar' ? 'pr-12' : 'pl-12' }} border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
            >
            <svg class="absolute {{ app()->getLocale() === 'ar' ? 'right-4' : 'left-4' }} top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>

    <!-- FAQ Categories (Optional) -->
    <div class="flex flex-wrap justify-center gap-2 mb-8">
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg">{{ __('All') }}</button>
        <button class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">{{ __('Orders') }}</button>
        <button class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">{{ __('Shipping') }}</button>
        <button class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">{{ __('Returns') }}</button>
        <button class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">{{ __('Payment') }}</button>
    </div>

    <!-- FAQ Items -->
    @if($faqs->count() > 0)
        <div class="max-w-3xl mx-auto">
            <div class="space-y-4">
                @foreach($faqs as $faq)
                    <div class="bg-white rounded-lg shadow" wire:key="faq-{{ $faq->id }}">
                        <button 
                            wire:click="toggleItem({{ $faq->id }})"
                            class="w-full px-6 py-4 text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }} flex items-center justify-between hover:bg-gray-50 transition"
                        >
                            <h3 class="font-semibold text-gray-900 {{ app()->getLocale() === 'ar' ? 'ml-4' : 'mr-4' }}">
                                {{ $faq->question }}
                            </h3>
                            <svg 
                                class="w-5 h-5 text-gray-500 flex-shrink-0 transition-transform {{ in_array($faq->id, $openItems) ? 'rotate-180' : '' }}"
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        @if(in_array($faq->id, $openItems))
                            <div class="px-6 pb-4">
                                <div class="text-gray-600 prose max-w-none">
                                    {!! nl2br(e($faq->answer)) !!}
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="text-center py-12">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            @if($searchQuery)
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('No FAQs found') }}</h3>
                <p class="text-gray-600">{{ __('Try searching with different keywords') }}</p>
            @else
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('No FAQs available') }}</h3>
                <p class="text-gray-600">{{ __('Please check back later') }}</p>
            @endif
        </div>
    @endif

    <!-- Contact Section -->
    <div class="mt-12 text-center">
        <div class="bg-blue-50 rounded-lg p-8">
            <h2 class="text-xl font-semibold mb-4">{{ __('Still have questions?') }}</h2>
            <p class="text-gray-600 mb-6">{{ __('We\'re here to help! Contact our support team for assistance.') }}</p>
            <a 
                href="/contact" 
                wire:navigate
                class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
            >
                {{ __('Contact Us') }}
            </a>
        </div>
    </div>
</div>