<?php

use Livewire\Volt\Component;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

new class extends Component
{
    public string $email = '';

    public function subscribe()
    {
        $this->validate([
            'email' => 'required|email|unique:newsletter_subscribers,email',
        ]);

        NewsletterSubscriber::create([
            'email' => $this->email,
            'token' => Str::random(32),
        ]);

        $this->email = '';
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Thank you for subscribing! Check your email for confirmation.')
        );
    }
}; ?>

<form wire:submit="subscribe" class="max-w-md mx-auto">
    <div class="flex">
        <input 
            type="email" 
            wire:model="email"
            placeholder="{{ __('Enter your email') }}"
            class="flex-1 px-4 py-3 bg-white text-gray-900 rounded-{{ app()->getLocale() === 'ar' ? 'r' : 'l' }}-lg focus:outline-none"
            required
        >
        <button 
            type="submit"
            class="px-6 py-3 bg-gray-900 hover:bg-gray-800 text-white rounded-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }}-lg transition-colors"
        >
            {{ __('Subscribe') }}
        </button>
    </div>
    @error('email')
        <p class="text-white text-sm mt-2">{{ $message }}</p>
    @enderror
</form>