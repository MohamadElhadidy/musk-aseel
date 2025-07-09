<?php

use Livewire\Volt\Component;
use App\Models\Contact;
use App\Models\Setting;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $subject = '';
    public string $message = '';
    public $settings = [];

    public function mount()
    {
        // Load contact settings
        $settingKeys = ['contact_email', 'contact_phone', 'contact_address'];
        $settings = Setting::whereIn('key', $settingKeys)->get();
        
        foreach ($settings as $setting) {
            $this->settings[$setting->key] = $setting->value;
        }

        // Pre-fill if user is logged in
        if (auth()->check()) {
            $this->name = auth()->user()->name;
            $this->email = auth()->user()->email;
            $this->phone = auth()->user()->phone ?? '';
        }
    }

    public function submit()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10|max:1000',
        ]);

        Contact::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
        ]);

        // Reset form
        $this->reset(['subject', 'message']);
        if (!auth()->check()) {
            $this->reset(['name', 'email', 'phone']);
        }

        $this->dispatch('toast', 
            type: 'success',
            message: __('Thank you for contacting us! We will get back to you soon.')
        );
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

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center mb-8">{{ __('Contact Us') }}</h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Contact Information -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-6">{{ __('Get in Touch') }}</h2>
                
                <div class="space-y-4">
                    @if($this->getSetting('contact_phone'))
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ __('Phone') }}</h3>
                                <p class="text-gray-600">{{ $this->getSetting('contact_phone') }}</p>
                            </div>
                        </div>
                    @endif

                    @if($this->getSetting('contact_email'))
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ __('Email') }}</h3>
                                <p class="text-gray-600">{{ $this->getSetting('contact_email') }}</p>
                            </div>
                        </div>
                    @endif

                    @if($this->getSetting('contact_address'))
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ __('Address') }}</h3>
                                <p class="text-gray-600">{{ $this->getSetting('contact_address') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Business Hours -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">{{ __('Business Hours') }}</h2>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Saturday - Thursday') }}</span>
                        <span class="font-medium">9:00 AM - 6:00 PM</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Friday') }}</span>
                        <span class="font-medium">{{ __('Closed') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-6">{{ __('Send us a Message') }}</h2>
                
                <form wire:submit="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="name" class="block text-gray-700 mb-2">{{ __('Name') }} <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                id="name"
                                wire:model="name"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-gray-700 mb-2">{{ __('Email') }} <span class="text-red-500">*</span></label>
                            <input 
                                type="email" 
                                id="email"
                                wire:model="email"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="block text-gray-700 mb-2">{{ __('Phone') }}</label>
                        <input 
                            type="tel" 
                            id="phone"
                            wire:model="phone"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                        >
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="subject" class="block text-gray-700 mb-2">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                        <input 
                            type="text" 
                            id="subject"
                            wire:model="subject"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                            required
                        >
                        @error('subject')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="message" class="block text-gray-700 mb-2">{{ __('Message') }} <span class="text-red-500">*</span></label>
                        <textarea 
                            id="message"
                            wire:model="message"
                            rows="5"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                            required
                        ></textarea>
                        @error('message')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full md:w-auto px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove>{{ __('Send Message') }}</span>
                        <span wire:loading>{{ __('Sending...') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Map Section (Optional) -->
    <div class="mt-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">{{ __('Find Us') }}</h2>
            <div class="aspect-video bg-gray-200 rounded-lg flex items-center justify-center">
                <!-- You can integrate Google Maps or any other map service here -->
                <p class="text-gray-500">{{ __('Map integration placeholder') }}</p>
            </div>
        </div>
    </div>
</div>