<?php

use Livewire\Volt\Component;
use App\Models\Page;

new class extends Component
{
    public ?Page $page = null;
    public string $slug;

    public function mount($slug)
    {
        $this->slug = $slug;
        $this->page = Page::where('slug', $slug)
            ->active()
            ->firstOrFail();
    }

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold mb-8">{{ $page->title }}</h1>

        <!-- Page Content -->
        <div class="bg-white rounded-lg shadow p-6 md:p-8">
            <div class="prose prose-lg max-w-none {{ app()->getLocale() === 'ar' ? 'text-right' : '' }}">
                {!! $page->content !!}
            </div>
        </div>

        <!-- Back Button -->
        <div class="mt-8 text-center">
            <a 
                href="/" 
                wire:navigate
                class="inline-flex items-center text-blue-600 hover:text-blue-700"
            >
                <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-2 rotate-180' : 'mr-2' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                {{ __('Back to Home') }}
            </a>
        </div>
    </div>
</div>