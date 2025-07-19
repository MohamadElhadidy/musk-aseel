<?php

use Livewire\Volt\Component;
use App\Models\Setting;
use App\Models\Language;
use App\Models\Currency;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $activeTab = 'general';
    
    // General Settings
    public array $siteName = ['en' => '', 'ar' => ''];
    public array $siteTagline = ['en' => '', 'ar' => ''];
    public $siteLogo = null;
    public $siteFavicon = null;
    public string $currentLogo = '';
    public string $currentFavicon = '';
    
    // Contact Settings
    public string $contactEmail = '';
    public string $contactPhone = '';
    public array $contactAddress = ['en' => '', 'ar' => ''];
    
    // Shop Settings
    public float $taxRate = 0;
    public string $currencyPosition = 'before';
    public ?int $defaultCurrency = null;
    public ?int $defaultLanguage = null;
    
    // Social Settings
    public string $facebookUrl = '';
    public string $twitterUrl = '';
    public string $instagramUrl = '';
    public string $youtubeUrl = '';
    
    // Email Settings
    public string $mailDriver = 'smtp';
    public string $mailHost = '';
    public string $mailPort = '587';
    public string $mailUsername = '';
    public string $mailPassword = '';
    public string $mailEncryption = 'tls';
    public string $mailFromAddress = '';
    public string $mailFromName = '';

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $settings = Setting::all()->pluck('value', 'key');
        
        // General
        $this->siteName = $settings['site_name'] ?? ['en' => '', 'ar' => ''];
        $this->siteTagline = $settings['site_tagline'] ?? ['en' => '', 'ar' => ''];
        $this->currentLogo = $settings['site_logo'] ?? '';
        $this->currentFavicon = $settings['site_favicon'] ?? '';
        
        // Contact
        $this->contactEmail = $settings['contact_email'] ?? '';
        $this->contactPhone = $settings['contact_phone'] ?? '';
        $this->contactAddress = $settings['contact_address'] ?? ['en' => '', 'ar' => ''];
        
        // Shop
        $this->taxRate = $settings['tax_rate'] ?? 0;
        $this->currencyPosition = $settings['currency_position'] ?? 'before';
        $this->defaultCurrency = Currency::where('is_default', true)->first()?->id;
        $this->defaultLanguage = Language::where('is_default', true)->first()?->id;
        
        // Social
        $this->facebookUrl = $settings['facebook_url'] ?? '';
        $this->twitterUrl = $settings['twitter_url'] ?? '';
        $this->instagramUrl = $settings['instagram_url'] ?? '';
        $this->youtubeUrl = $settings['youtube_url'] ?? '';
        
        // Email
        $this->mailDriver = $settings['mail_driver'] ?? 'smtp';
        $this->mailHost = $settings['mail_host'] ?? '';
        $this->mailPort = $settings['mail_port'] ?? '587';
        $this->mailUsername = $settings['mail_username'] ?? '';
        $this->mailPassword = $settings['mail_password'] ?? '';
        $this->mailEncryption = $settings['mail_encryption'] ?? 'tls';
        $this->mailFromAddress = $settings['mail_from_address'] ?? '';
        $this->mailFromName = $settings['mail_from_name'] ?? '';
    }

    public function saveGeneralSettings()
    {
        $this->validate([
            'siteName.en' => 'required|string|max:255',
            'siteName.ar' => 'required|string|max:255',
            'siteTagline.en' => 'nullable|string|max:255',
            'siteTagline.ar' => 'nullable|string|max:255',
            'siteLogo' => 'nullable|image|max:2048',
            'siteFavicon' => 'nullable|image|max:512',
        ]);

        Setting::set('site_name', $this->siteName);
        Setting::set('site_tagline', $this->siteTagline);
        
        if ($this->siteLogo) {
            $logoPath = $this->siteLogo->store('settings', 'public');
            Setting::set('site_logo', $logoPath);
        }
        
        if ($this->siteFavicon) {
            $faviconPath = $this->siteFavicon->store('settings', 'public');
            Setting::set('site_favicon', $faviconPath);
        }

        $this->dispatch('toast', 
            type: 'success',
            message: __('General settings updated successfully')
        );
    }

    public function saveContactSettings()
    {
        $this->validate([
            'contactEmail' => 'required|email',
            'contactPhone' => 'required|string|max:20',
            'contactAddress.en' => 'required|string',
            'contactAddress.ar' => 'required|string',
        ]);

        Setting::set('contact_email', $this->contactEmail);
        Setting::set('contact_phone', $this->contactPhone);
        Setting::set('contact_address', $this->contactAddress);

        $this->dispatch('toast', 
            type: 'success',
            message: __('Contact settings updated successfully')
        );
    }

    public function saveShopSettings()
    {
        $this->validate([
            'taxRate' => 'required|numeric|min:0|max:100',
            'currencyPosition' => 'required|in:before,after',
            'defaultCurrency' => 'required|exists:currencies,id',
            'defaultLanguage' => 'required|exists:languages,id',
        ]);

        Setting::set('tax_rate', $this->taxRate);
        Setting::set('currency_position', $this->currencyPosition);
        
        // Update default currency
        Currency::query()->update(['is_default' => false]);
        Currency::find($this->defaultCurrency)->update(['is_default' => true]);
        
        // Update default language
        Language::query()->update(['is_default' => false]);
        Language::find($this->defaultLanguage)->update(['is_default' => true]);

        $this->dispatch('toast', 
            type: 'success',
            message: __('Shop settings updated successfully')
        );
    }

    public function saveSocialSettings()
    {
        $this->validate([
            'facebookUrl' => 'nullable|url',
            'twitterUrl' => 'nullable|url',
            'instagramUrl' => 'nullable|url',
            'youtubeUrl' => 'nullable|url',
        ]);

        Setting::set('facebook_url', $this->facebookUrl);
        Setting::set('twitter_url', $this->twitterUrl);
        Setting::set('instagram_url', $this->instagramUrl);
        Setting::set('youtube_url', $this->youtubeUrl);

        $this->dispatch('toast', 
            type: 'success',
            message: __('Social settings updated successfully')
        );
    }

    public function saveEmailSettings()
    {
        $this->validate([
            'mailDriver' => 'required|in:smtp,sendmail,mailgun,ses',
            'mailHost' => 'required_if:mailDriver,smtp',
            'mailPort' => 'required_if:mailDriver,smtp|numeric',
            'mailUsername' => 'required_if:mailDriver,smtp',
            'mailPassword' => 'required_if:mailDriver,smtp',
            'mailEncryption' => 'required_if:mailDriver,smtp|in:tls,ssl,null',
            'mailFromAddress' => 'required|email',
            'mailFromName' => 'required|string',
        ]);

        Setting::set('mail_driver', $this->mailDriver);
        Setting::set('mail_host', $this->mailHost);
        Setting::set('mail_port', $this->mailPort);
        Setting::set('mail_username', $this->mailUsername);
        Setting::set('mail_password', $this->mailPassword);
        Setting::set('mail_encryption', $this->mailEncryption);
        Setting::set('mail_from_address', $this->mailFromAddress);
        Setting::set('mail_from_name', $this->mailFromName);

        // Update .env file
        $this->updateEnvFile([
            'MAIL_MAILER' => $this->mailDriver,
            'MAIL_HOST' => $this->mailHost,
            'MAIL_PORT' => $this->mailPort,
            'MAIL_USERNAME' => $this->mailUsername,
            'MAIL_PASSWORD' => $this->mailPassword,
            'MAIL_ENCRYPTION' => $this->mailEncryption,
            'MAIL_FROM_ADDRESS' => $this->mailFromAddress,
            'MAIL_FROM_NAME' => $this->mailFromName,
        ]);

        $this->dispatch('toast', 
            type: 'success',
            message: __('Email settings updated successfully')
        );
    }

    protected function updateEnvFile(array $data)
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}=\"{$value}\"";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envFile, $envContent);
    }

    public function testEmailConnection()
    {
        try {
            \Mail::raw('Test email from ' . config('app.name'), function ($message) {
                $message->to($this->mailFromAddress)
                    ->subject('Test Email Connection');
            });

            $this->dispatch('toast', 
                type: 'success',
                message: __('Test email sent successfully! Check your inbox.')
            );
        } catch (\Exception $e) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Failed to send test email: ') . $e->getMessage()
            );
        }
    }

    public function with()
    {
        return [
            'currencies' => Currency::all(),
            'languages' => Language::all(),
            'layout' => 'admin.layout',
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Settings') }}</h1>
        <p class="text-gray-600">{{ __('Manage your store settings and configurations') }}</p>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b">
            <nav class="flex -mb-px">
                <button 
                    wire:click="$set('activeTab', 'general')"
                    class="py-4 px-6 text-sm font-medium {{ $activeTab === 'general' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('General') }}
                </button>
                <button 
                    wire:click="$set('activeTab', 'contact')"
                    class="py-4 px-6 text-sm font-medium {{ $activeTab === 'contact' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Contact') }}
                </button>
                <button 
                    wire:click="$set('activeTab', 'shop')"
                    class="py-4 px-6 text-sm font-medium {{ $activeTab === 'shop' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Shop') }}
                </button>
                <button 
                    wire:click="$set('activeTab', 'social')"
                    class="py-4 px-6 text-sm font-medium {{ $activeTab === 'social' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Social Media') }}
                </button>
                <button 
                    wire:click="$set('activeTab', 'email')"
                    class="py-4 px-6 text-sm font-medium {{ $activeTab === 'email' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Email') }}
                </button>
            </nav>
        </div>

        <div class="p-6">
            <!-- General Settings -->
            @if($activeTab === 'general')
                <form wire:submit="saveGeneralSettings">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Site Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Name') }}</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <input 
                                        type="text"
                                        wire:model="siteName.en"
                                        placeholder="English"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                        required
                                    >
                                    @error('siteName.en')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <input 
                                        type="text"
                                        wire:model="siteName.ar"
                                        placeholder="العربية"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                        required
                                    >
                                    @error('siteName.ar')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Site Tagline -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Tagline') }}</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <input 
                                        type="text"
                                        wire:model="siteTagline.en"
                                        placeholder="English"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    >
                                </div>
                                <div>
                                    <input 
                                        type="text"
                                        wire:model="siteTagline.ar"
                                        placeholder="العربية"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Logo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Logo') }}</label>
                            @if($currentLogo)
                                <div class="mb-4">
                                    <img src="{{ asset('storage/' . $currentLogo) }}" alt="Logo" class="h-20">
                                </div>
                            @endif
                            <input 
                                type="file"
                                wire:model="siteLogo"
                                accept="image/*"
                                class="w-full"
                            >
                            @error('siteLogo')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Favicon -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Favicon') }}</label>
                            @if($currentFavicon)
                                <div class="mb-4">
                                    <img src="{{ asset('storage/' . $currentFavicon) }}" alt="Favicon" class="h-8">
                                </div>
                            @endif
                            <input 
                                type="file"
                                wire:model="siteFavicon"
                                accept="image/*"
                                class="w-full"
                            >
                            @error('siteFavicon')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ __('Save General Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Contact Settings -->
            @if($activeTab === 'contact')
                <form wire:submit="saveContactSettings">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Contact Email') }}</label>
                            <input 
                                type="email"
                                wire:model="contactEmail"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('contactEmail')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Contact Phone') }}</label>
                            <input 
                                type="text"
                                wire:model="contactPhone"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('contactPhone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Address -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Contact Address') }}</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <textarea 
                                        wire:model="contactAddress.en"
                                        placeholder="English"
                                        rows="3"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                        required
                                    ></textarea>
                                    @error('contactAddress.en')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <textarea 
                                        wire:model="contactAddress.ar"
                                        placeholder="العربية"
                                        rows="3"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                        required
                                    ></textarea>
                                    @error('contactAddress.ar')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ __('Save Contact Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Shop Settings -->
            @if($activeTab === 'shop')
                <form wire:submit="saveShopSettings">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tax Rate -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Tax Rate (%)') }}</label>
                            <input 
                                type="number"
                                wire:model="taxRate"
                                step="0.01"
                                min="0"
                                max="100"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('taxRate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Currency Position -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Currency Position') }}</label>
                            <select 
                                wire:model="currencyPosition"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                                <option value="before">{{ __('Before Amount') }} ($100)</option>
                                <option value="after">{{ __('After Amount') }} (100$)</option>
                            </select>
                        </div>

                        <!-- Default Currency -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Currency') }}</label>
                            <select 
                                wire:model="defaultCurrency"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                                <option value="">{{ __('Select Currency') }}</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}">{{ $currency->name }} ({{ $currency->code }})</option>
                                @endforeach
                            </select>
                            @error('defaultCurrency')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Default Language -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Language') }}</label>
                            <select 
                                wire:model="defaultLanguage"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                                <option value="">{{ __('Select Language') }}</option>
                                @foreach($languages as $language)
                                    <option value="{{ $language->id }}">{{ $language->name }}</option>
                                @endforeach
                            </select>
                            @error('defaultLanguage')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ __('Save Shop Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Social Settings -->
            @if($activeTab === 'social')
                <form wire:submit="saveSocialSettings">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Facebook -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Facebook URL') }}</label>
                            <input 
                                type="url"
                                wire:model="facebookUrl"
                                placeholder="https://facebook.com/yourpage"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                            @error('facebookUrl')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Twitter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Twitter URL') }}</label>
                            <input 
                                type="url"
                                wire:model="twitterUrl"
                                placeholder="https://twitter.com/yourhandle"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                            @error('twitterUrl')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Instagram -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Instagram URL') }}</label>
                            <input 
                                type="url"
                                wire:model="instagramUrl"
                                placeholder="https://instagram.com/yourhandle"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                            @error('instagramUrl')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- YouTube -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('YouTube URL') }}</label>
                            <input 
                                type="url"
                                wire:model="youtubeUrl"
                                placeholder="https://youtube.com/yourchannel"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                            @error('youtubeUrl')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ __('Save Social Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Email Settings -->
            @if($activeTab === 'email')
                <form wire:submit="saveEmailSettings">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Mail Driver -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Driver') }}</label>
                            <select 
                                wire:model.live="mailDriver"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            >
                                <option value="smtp">SMTP</option>
                                <option value="sendmail">Sendmail</option>
                                <option value="mailgun">Mailgun</option>
                                <option value="ses">Amazon SES</option>
                            </select>
                        </div>

                        @if($mailDriver === 'smtp')
                            <!-- Host -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Host') }}</label>
                                <input 
                                    type="text"
                                    wire:model="mailHost"
                                    placeholder="smtp.gmail.com"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                @error('mailHost')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Port -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Port') }}</label>
                                <input 
                                    type="text"
                                    wire:model="mailPort"
                                    placeholder="587"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                @error('mailPort')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Username -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Username') }}</label>
                                <input 
                                    type="text"
                                    wire:model="mailUsername"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                @error('mailUsername')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Password') }}</label>
                                <input 
                                    type="password"
                                    wire:model="mailPassword"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    required
                                >
                                @error('mailPassword')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Encryption -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Encryption') }}</label>
                                <select 
                                    wire:model="mailEncryption"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                >
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="null">None</option>
                                </select>
                            </div>
                        @endif

                        <!-- From Address -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('From Email Address') }}</label>
                            <input 
                                type="email"
                                wire:model="mailFromAddress"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('mailFromAddress')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- From Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('From Name') }}</label>
                            <input 
                                type="text"
                                wire:model="mailFromName"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                required
                            >
                            @error('mailFromName')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ __('Save Email Settings') }}
                        </button>
                        <button 
                            type="button"
                            wire:click="testEmailConnection"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                        >
                            {{ __('Send Test Email') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>