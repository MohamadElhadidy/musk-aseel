<?php

use Livewire\Volt\Component;
use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

new class extends Component
{
    public string $email = '';
    public $categories;
    public $pages;
    public $settings = [];

    public function mount()
    {
        $this->categories = Category::active()
            ->root()
            ->take(5)
            ->get();
            
        $this->pages = Page::active()->get();
        
        // Load settings
        $settingKeys = ['site_name', 'site_tagline', 'contact_email', 'contact_phone', 'contact_address', 'facebook_url', 'twitter_url', 'instagram_url'];
        $settings = Setting::whereIn('key', $settingKeys)->get();
        
        foreach ($settings as $setting) {
            $this->settings[$setting->key] = $setting->value;
        }
    }

    public function subscribe()
    {
        // $this->validate([
        //     'email' => 'required|email|unique:newsletter_subscribers,email',
        // ]);

        // NewsletterSubscriber::create([
        //     'email' => $this->email,
        //     'token' => Str::random(32),
        // ]);

        // $this->email = '';
        // $this->dispatch('toast', 
        //     type: 'success',
        //     message: __('Thank you for subscribing to our newsletter!')
        // );
    }

    public function getSetting($key, $locale = null)
    {
        $value = $this->settings[$key] ?? null;
        
        if (is_array($value)) {
            $locale = $locale ?? app()->getLocale();
            return $value[$locale] ?? $value['en'] ?? '';
        }
        
        return $value;
    }
}; ?>

<footer class="bg-gray-900 text-white">
    <!-- Main Footer -->
    <div class="container mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Company Info -->
            <div>
                <h3 class="text-xl font-bold mb-4">{{ $this->getSetting('site_name') }}</h3>
                <p class="text-gray-400 mb-4">{{ $this->getSetting('site_tagline') }}</p>
                <div class="flex gap-4">
                    @if($this->getSetting('facebook_url'))
                        <a href="{{ $this->getSetting('facebook_url') }}" target="_blank" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>
                            </svg>
                        </a>
                    @endif
                    @if($this->getSetting('twitter_url'))
                        <a href="{{ $this->getSetting('twitter_url') }}" target="_blank" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/>
                            </svg>
                        </a>
                    @endif
                    @if($this->getSetting('instagram_url'))
                        <a href="{{ $this->getSetting('instagram_url') }}" target="_blank" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-lg font-semibold mb-4">{{ __('Quick Links') }}</h4>
                <ul class="space-y-2">
                    @foreach($pages as $page)
                        <li>
                            <a href="/pages/{{ $page->slug }}" wire:navigate class="text-gray-400 hover:text-white">
                                {{ $page->title }}
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="/contact" wire:navigate class="text-gray-400 hover:text-white">
                            {{ __('Contact Us') }}
                        </a>
                    </li>
                    <li>
                        <a href="/faq" wire:navigate class="text-gray-400 hover:text-white">
                            {{ __('FAQ') }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Categories -->
            <div>
                <h4 class="text-lg font-semibold mb-4">{{ __('Categories') }}</h4>
                <ul class="space-y-2">
                    @foreach($categories as $category)
                        <li>
                            <a href="/categories/{{ $category->slug }}" wire:navigate class="text-gray-400 hover:text-white">
                                {{ $category->name }}
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="/categories" wire:navigate class="text-gray-400 hover:text-white">
                            {{ __('All Categories') }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Newsletter -->
            <div>
                <h4 class="text-lg font-semibold mb-4">{{ __('Newsletter') }}</h4>
                <p class="text-gray-400 mb-4">{{ __('Subscribe to get special offers and updates') }}</p>
                <form wire:submit="subscribe">
                    <div class="flex">
                        <input 
                            type="email" 
                            wire:model="email"
                            placeholder="{{ __('Your email') }}"
                            class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-{{ app()->getLocale() === 'ar' ? 'r' : 'l' }}-lg focus:outline-none focus:bg-gray-700"
                            required
                        >
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }}-lg transition-colors"
                        >
                            {{ __('Subscribe') }}
                        </button>
                    </div>
                    @error('email')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </form>
            </div>
        </div>
    </div>

    <!-- Contact Info -->
    <div class="bg-gray-800 py-6">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center md:text-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">
                @if($this->getSetting('contact_phone'))
                    <div class="flex items-center justify-center md:justify-start gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <span class="text-gray-400">{{ $this->getSetting('contact_phone') }}</span>
                    </div>
                @endif

                @if($this->getSetting('contact_email'))
                    <div class="flex items-center justify-center md:justify-start gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-gray-400">{{ $this->getSetting('contact_email') }}</span>
                    </div>
                @endif

                @if($this->getSetting('contact_address'))
                    <div class="flex items-center justify-center md:justify-start gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="text-gray-400">{{ $this->getSetting('contact_address') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="bg-black py-4">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-400">
                <p>&copy; {{ date('Y') }} {{ $this->getSetting('site_name') }}. {{ __('All rights reserved.') }}</p>
                <div class="flex gap-4 mt-2 md:mt-0">
                    <a href="/pages/privacy-policy" wire:navigate class="hover:text-white">{{ __('Privacy Policy') }}</a>
                    <a href="/pages/terms-conditions" wire:navigate class="hover:text-white">{{ __('Terms & Conditions') }}</a>
                </div>
            </div>
        </div>
    </div>
</footer>