<?php

use Livewire\Volt\Component;
use App\Models\Setting;
use App\Models\Language;
use App\Models\Currency;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;

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
    public string $whatsappNumber = '';
    public array $workingHours = ['en' => '', 'ar' => ''];
    
    // Shop Settings
    public float $taxRate = 0;
    public string $currencyPosition = 'before';
    public ?int $defaultCurrency = null;
    public ?int $defaultLanguage = null;
    public int $productsPerPage = 12;
    public bool $enableReviews = true;
    public bool $enableWishlist = true;
    public bool $enableCompare = true;
    public int $lowStockThreshold = 10;
    
    // Social Settings
    public string $facebookUrl = '';
    public string $twitterUrl = '';
    public string $instagramUrl = '';
    public string $youtubeUrl = '';
    public string $linkedinUrl = '';
    public string $tiktokUrl = '';
    
    // Email Settings
    public string $mailDriver = 'smtp';
    public string $mailHost = '';
    public string $mailPort = '587';
    public string $mailUsername = '';
    public string $mailPassword = '';
    public string $mailEncryption = 'tls';
    public string $mailFromAddress = '';
    public string $mailFromName = '';
    
    // SEO Settings
    public array $metaTitle = ['en' => '', 'ar' => ''];
    public array $metaDescription = ['en' => '', 'ar' => ''];
    public array $metaKeywords = ['en' => '', 'ar' => ''];
    public string $googleAnalyticsId = '';
    public string $facebookPixelId = '';
    
    // Maintenance
    public bool $maintenanceMode = false;
    public array $maintenanceMessage = ['en' => '', 'ar' => ''];

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }
        
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        
        // General
        $this->siteName = $settings['site_name'] ?? ['en' => config('app.name'), 'ar' => config('app.name')];
        $this->siteTagline = $settings['site_tagline'] ?? ['en' => '', 'ar' => ''];
        $this->currentLogo = $settings['site_logo'] ?? '';
        $this->currentFavicon = $settings['site_favicon'] ?? '';
        
        // Contact
        $this->contactEmail = $settings['contact_email'] ?? '';
        $this->contactPhone = $settings['contact_phone'] ?? '';
        $this->contactAddress = $settings['contact_address'] ?? ['en' => '', 'ar' => ''];
        $this->whatsappNumber = $settings['whatsapp_number'] ?? '';
        $this->workingHours = $settings['working_hours'] ?? ['en' => '', 'ar' => ''];
        
        // Shop
        $this->taxRate = $settings['tax_rate'] ?? 0;
        $this->currencyPosition = $settings['currency_position'] ?? 'before';
        $this->defaultCurrency = $settings['default_currency'] ?? Currency::where('is_default', true)->first()?->id;
        $this->defaultLanguage = $settings['default_language'] ?? Language::where('is_default', true)->first()?->id;
        $this->productsPerPage = $settings['products_per_page'] ?? 12;
        $this->enableReviews = $settings['enable_reviews'] ?? true;
        $this->enableWishlist = $settings['enable_wishlist'] ?? true;
        $this->enableCompare = $settings['enable_compare'] ?? true;
        $this->lowStockThreshold = $settings['low_stock_threshold'] ?? 10;
        
        // Social
        $this->facebookUrl = $settings['facebook_url'] ?? '';
        $this->twitterUrl = $settings['twitter_url'] ?? '';
        $this->instagramUrl = $settings['instagram_url'] ?? '';
        $this->youtubeUrl = $settings['youtube_url'] ?? '';
        $this->linkedinUrl = $settings['linkedin_url'] ?? '';
        $this->tiktokUrl = $settings['tiktok_url'] ?? '';
        
        // Email
        $this->mailDriver = $settings['mail_driver'] ?? 'smtp';
        $this->mailHost = $settings['mail_host'] ?? '';
        $this->mailPort = $settings['mail_port'] ?? '587';
        $this->mailUsername = $settings['mail_username'] ?? '';
        $this->mailPassword = $settings['mail_password'] ?? '';
        $this->mailEncryption = $settings['mail_encryption'] ?? 'tls';
        $this->mailFromAddress = $settings['mail_from_address'] ?? '';
        $this->mailFromName = $settings['mail_from_name'] ?? '';
        
        // SEO
        $this->metaTitle = $settings['meta_title'] ?? ['en' => '', 'ar' => ''];
        $this->metaDescription = $settings['meta_description'] ?? ['en' => '', 'ar' => ''];
        $this->metaKeywords = $settings['meta_keywords'] ?? ['en' => '', 'ar' => ''];
        $this->googleAnalyticsId = $settings['google_analytics_id'] ?? '';
        $this->facebookPixelId = $settings['facebook_pixel_id'] ?? '';
        
        // Maintenance
        $this->maintenanceMode = $settings['maintenance_mode'] ?? false;
        $this->maintenanceMessage = $settings['maintenance_message'] ?? ['en' => 'Site under maintenance', 'ar' => 'الموقع تحت الصيانة'];
    }

    public function saveGeneralSettings()
    {
        $this->validate([
            'siteName.en' => 'required|string|max:255',
            'siteName.ar' => 'required|string|max:255',
            'siteTagline.en' => 'nullable|string|max:255',
            'siteTagline.ar' => 'nullable|string|max:255',
            'siteLogo' => 'nullable|image|max:2048',
            'siteFavicon' => 'nullable|image|max:512|mimes:ico,png,jpg',
        ]);

        Setting::set('site_name', $this->siteName);
        Setting::set('site_tagline', $this->siteTagline);
        
        if ($this->siteLogo) {
            // Delete old logo if exists
            if ($this->currentLogo && \Storage::disk('public')->exists($this->currentLogo)) {
                \Storage::disk('public')->delete($this->currentLogo);
            }
            
            $logoPath = $this->siteLogo->store('settings', 'public');
            Setting::set('site_logo', $logoPath);
            $this->currentLogo = $logoPath;
        }
        
        if ($this->siteFavicon) {
            // Delete old favicon if exists
            if ($this->currentFavicon && \Storage::disk('public')->exists($this->currentFavicon)) {
                \Storage::disk('public')->delete($this->currentFavicon);
            }
            
            $faviconPath = $this->siteFavicon->store('settings', 'public');
            Setting::set('site_favicon', $faviconPath);
            $this->currentFavicon = $faviconPath;
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
            'whatsappNumber' => 'nullable|string|max:20',
            'workingHours.en' => 'nullable|string',
            'workingHours.ar' => 'nullable|string',
        ]);

        Setting::set('contact_email', $this->contactEmail);
        Setting::set('contact_phone', $this->contactPhone);
        Setting::set('contact_address', $this->contactAddress);
        Setting::set('whatsapp_number', $this->whatsappNumber);
        Setting::set('working_hours', $this->workingHours);

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
            'productsPerPage' => 'required|integer|min:6|max:48',
            'lowStockThreshold' => 'required|integer|min:1',
        ]);

        Setting::set('tax_rate', $this->taxRate);
        Setting::set('currency_position', $this->currencyPosition);
        Setting::set('products_per_page', $this->productsPerPage);
        Setting::set('enable_reviews', $this->enableReviews);
        Setting::set('enable_wishlist', $this->enableWishlist);
        Setting::set('enable_compare', $this->enableCompare);
        Setting::set('low_stock_threshold', $this->lowStockThreshold);
        
        // Update default currency
        Currency::query()->update(['is_default' => false]);
        Currency::find($this->defaultCurrency)->update(['is_default' => true]);
        Setting::set('default_currency', $this->defaultCurrency);
        
        // Update default language
        Language::query()->update(['is_default' => false]);
        Language::find($this->defaultLanguage)->update(['is_default' => true]);
        Setting::set('default_language', $this->defaultLanguage);

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
            'linkedinUrl' => 'nullable|url',
            'tiktokUrl' => 'nullable|url',
        ]);

        Setting::set('facebook_url', $this->facebookUrl);
        Setting::set('twitter_url', $this->twitterUrl);
        Setting::set('instagram_url', $this->instagramUrl);
        Setting::set('youtube_url', $this->youtubeUrl);
        Setting::set('linkedin_url', $this->linkedinUrl);
        Setting::set('tiktok_url', $this->tiktokUrl);

        $this->dispatch('toast', 
            type: 'success',
            message: __('Social media settings updated successfully')
        );
    }

    public function saveEmailSettings()
    {
        $this->validate([
            'mailDriver' => 'required|in:smtp,sendmail,mail',
            'mailHost' => 'required_if:mailDriver,smtp',
            'mailPort' => 'required_if:mailDriver,smtp|numeric',
            'mailUsername' => 'required_if:mailDriver,smtp',
            'mailPassword' => 'required_if:mailDriver,smtp',
            'mailEncryption' => 'required_if:mailDriver,smtp|in:tls,ssl,null',
            'mailFromAddress' => 'required|email',
            'mailFromName' => 'required|string|max:255',
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
            'MAIL_ENCRYPTION' => $this->mailEncryption === 'null' ? null : $this->mailEncryption,
            'MAIL_FROM_ADDRESS' => $this->mailFromAddress,
            'MAIL_FROM_NAME' => '"' . $this->mailFromName . '"',
        ]);

        $this->dispatch('toast', 
            type: 'success',
            message: __('Email settings updated successfully')
        );
    }

    public function saveSeoSettings()
    {
        $this->validate([
            'metaTitle.en' => 'nullable|string|max:60',
            'metaTitle.ar' => 'nullable|string|max:60',
            'metaDescription.en' => 'nullable|string|max:160',
            'metaDescription.ar' => 'nullable|string|max:160',
            'metaKeywords.en' => 'nullable|string|max:255',
            'metaKeywords.ar' => 'nullable|string|max:255',
            'googleAnalyticsId' => 'nullable|string|max:20',
            'facebookPixelId' => 'nullable|string|max:20',
        ]);

        Setting::set('meta_title', $this->metaTitle);
        Setting::set('meta_description', $this->metaDescription);
        Setting::set('meta_keywords', $this->metaKeywords);
        Setting::set('google_analytics_id', $this->googleAnalyticsId);
        Setting::set('facebook_pixel_id', $this->facebookPixelId);

        $this->dispatch('toast', 
            type: 'success',
            message: __('SEO settings updated successfully')
        );
    }

    public function saveMaintenanceSettings()
    {
        $this->validate([
            'maintenanceMessage.en' => 'required_if:maintenanceMode,true|string',
            'maintenanceMessage.ar' => 'required_if:maintenanceMode,true|string',
        ]);

        Setting::set('maintenance_mode', $this->maintenanceMode);
        Setting::set('maintenance_message', $this->maintenanceMessage);

        // Toggle maintenance mode
        if ($this->maintenanceMode) {
            \Artisan::call('down', [
                '--render' => 'errors::503',
                '--secret' => 'maintenance-secret-' . \Str::random(32)
            ]);
        } else {
            \Artisan::call('up');
        }

        $this->dispatch('toast', 
            type: 'success',
            message: __('Maintenance settings updated successfully')
        );
    }

    public function testEmailConnection()
    {
        try {
            Mail::raw('This is a test email from ' . config('app.name'), function ($message) {
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

    protected function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            if (preg_match("/^{$key}=/m", $envContent)) {
                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$value}",
                    $envContent
                );
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);
    }

    public function with()
    {
        return [
            'currencies' => Currency::all(),
            'languages' => Language::all(),
        ];
    }
}; ?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Settings') }}</h1>
        <p class="text-gray-600">{{ __('Manage your store settings and configurations') }}</p>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b">
            <nav class="flex flex-wrap -mb-px">
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
                <button 
                    wire:click="$set('activeTab', 'seo')"
                    class="py-4 px-6 text-sm font-medium {{ $activeTab === 'seo' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('SEO') }}
                </button>
                <button 
                    wire:click="$set('activeTab', 'maintenance')"
                    class="py-4 px-6 text-sm font-medium {{ $activeTab === 'maintenance' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Maintenance') }}
                </button>
            </nav>
        </div>

        <div class="p-6">
            <!-- General Tab -->
            @if($activeTab === 'general')
                <form wire:submit.prevent="saveGeneralSettings" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Site Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Name') }} (EN)</label>
                            <input type="text" wire:model="siteName.en" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('siteName.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Name') }} (AR)</label>
                            <input type="text" wire:model="siteName.ar" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl">
                            @error('siteName.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Site Tagline -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Tagline') }} (EN)</label>
                            <input type="text" wire:model="siteTagline.en" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('siteTagline.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Tagline') }} (AR)</label>
                            <input type="text" wire:model="siteTagline.ar" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl">
                            @error('siteTagline.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Logo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Logo') }}</label>
                            <input type="file" wire:model="siteLogo" class="w-full" accept="image/*">
                            @if($currentLogo)
                                <img src="{{ asset('storage/' . $currentLogo) }}" alt="Logo" class="mt-2 h-20">
                            @endif
                            @error('siteLogo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Favicon -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Site Favicon') }}</label>
                            <input type="file" wire:model="siteFavicon" class="w-full" accept="image/x-icon,image/png,image/jpg">
                            @if($currentFavicon)
                                <img src="{{ asset('storage/' . $currentFavicon) }}" alt="Favicon" class="mt-2 h-10">
                            @endif
                            @error('siteFavicon') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ __('Save General Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Contact Tab -->
            @if($activeTab === 'contact')
                <form wire:submit.prevent="saveContactSettings" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Contact Email') }}</label>
                            <input type="email" wire:model="contactEmail" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('contactEmail') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Contact Phone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Contact Phone') }}</label>
                            <input type="text" wire:model="contactPhone" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('contactPhone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- WhatsApp Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('WhatsApp Number') }}</label>
                            <input type="text" wire:model="whatsappNumber" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('whatsappNumber') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Contact Address -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Contact Address') }} (EN)</label>
                            <textarea wire:model="contactAddress.en" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm"></textarea>
                            @error('contactAddress.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Contact Address') }} (AR)</label>
                            <textarea wire:model="contactAddress.ar" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl"></textarea>
                            @error('contactAddress.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Working Hours -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Working Hours') }} (EN)</label>
                            <input type="text" wire:model="workingHours.en" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="Mon-Fri: 9AM-6PM, Sat: 10AM-4PM">
                            @error('workingHours.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Working Hours') }} (AR)</label>
                            <input type="text" wire:model="workingHours.ar" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl" placeholder="الإثنين-الجمعة: 9 صباحًا - 6 مساءً">
                            @error('workingHours.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ __('Save Contact Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Shop Tab -->
            @if($activeTab === 'shop')
                <form wire:submit.prevent="saveShopSettings" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tax Rate -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Tax Rate') }} (%)</label>
                            <input type="number" wire:model="taxRate" step="0.01" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('taxRate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Currency Position -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Currency Position') }}</label>
                            <select wire:model="currencyPosition" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="before">{{ __('Before') }} ($99)</option>
                                <option value="after">{{ __('After') }} (99$)</option>
                            </select>
                            @error('currencyPosition') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Default Currency -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Currency') }}</label>
                            <select wire:model="defaultCurrency" class="w-full border-gray-300 rounded-lg shadow-sm">
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}">{{ $currency->name }} ({{ $currency->code }})</option>
                                @endforeach
                            </select>
                            @error('defaultCurrency') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Default Language -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Language') }}</label>
                            <select wire:model="defaultLanguage" class="w-full border-gray-300 rounded-lg shadow-sm">
                                @foreach($languages as $language)
                                    <option value="{{ $language->id }}">{{ $language->name }}</option>
                                @endforeach
                            </select>
                            @error('defaultLanguage') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Products Per Page -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Products Per Page') }}</label>
                            <input type="number" wire:model="productsPerPage" min="6" max="48" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('productsPerPage') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Low Stock Threshold -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Low Stock Threshold') }}</label>
                            <input type="number" wire:model="lowStockThreshold" min="1" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('lowStockThreshold') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Feature Toggles -->
                        <div class="md:col-span-2 space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="enableReviews" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">{{ __('Enable Product Reviews') }}</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="enableWishlist" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">{{ __('Enable Wishlist') }}</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="enableCompare" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">{{ __('Enable Product Compare') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ __('Save Shop Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Social Media Tab -->
            @if($activeTab === 'social')
                <form wire:submit.prevent="saveSocialSettings" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Facebook -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Facebook URL') }}</label>
                            <input type="url" wire:model="facebookUrl" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="https://facebook.com/yourpage">
                            @error('facebookUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Twitter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Twitter URL') }}</label>
                            <input type="url" wire:model="twitterUrl" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="https://twitter.com/yourhandle">
                            @error('twitterUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Instagram -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Instagram URL') }}</label>
                            <input type="url" wire:model="instagramUrl" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="https://instagram.com/yourhandle">
                            @error('instagramUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- YouTube -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('YouTube URL') }}</label>
                            <input type="url" wire:model="youtubeUrl" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="https://youtube.com/yourchannel">
                            @error('youtubeUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- LinkedIn -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('LinkedIn URL') }}</label>
                            <input type="url" wire:model="linkedinUrl" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="https://linkedin.com/company/yourcompany">
                            @error('linkedinUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- TikTok -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('TikTok URL') }}</label>
                            <input type="url" wire:model="tiktokUrl" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="https://tiktok.com/@yourhandle">
                            @error('tiktokUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ __('Save Social Media Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Email Tab -->
            @if($activeTab === 'email')
                <form wire:submit.prevent="saveEmailSettings" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Mail Driver -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Driver') }}</label>
                            <select wire:model="mailDriver" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="smtp">SMTP</option>
                                <option value="sendmail">Sendmail</option>
                                <option value="mail">Mail</option>
                            </select>
                            @error('mailDriver') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Mail Host -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Host') }}</label>
                            <input type="text" wire:model="mailHost" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="smtp.gmail.com">
                            @error('mailHost') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Mail Port -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Port') }}</label>
                            <input type="text" wire:model="mailPort" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="587">
                            @error('mailPort') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Mail Encryption -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Encryption') }}</label>
                            <select wire:model="mailEncryption" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="null">{{ __('None') }}</option>
                            </select>
                            @error('mailEncryption') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Mail Username -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Username') }}</label>
                            <input type="text" wire:model="mailUsername" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('mailUsername') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Mail Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mail Password') }}</label>
                            <input type="password" wire:model="mailPassword" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('mailPassword') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- From Address -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('From Address') }}</label>
                            <input type="email" wire:model="mailFromAddress" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('mailFromAddress') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- From Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('From Name') }}</label>
                            <input type="text" wire:model="mailFromName" class="w-full border-gray-300 rounded-lg shadow-sm">
                            @error('mailFromName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" wire:click="testEmailConnection" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                            {{ __('Send Test Email') }}
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ __('Save Email Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- SEO Tab -->
            @if($activeTab === 'seo')
                <form wire:submit.prevent="saveSeoSettings" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Meta Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Meta Title') }} (EN)</label>
                            <input type="text" wire:model="metaTitle.en" maxlength="60" class="w-full border-gray-300 rounded-lg shadow-sm">
                            <p class="text-xs text-gray-500 mt-1">{{ strlen($metaTitle['en']) }}/60 {{ __('characters') }}</p>
                            @error('metaTitle.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Meta Title') }} (AR)</label>
                            <input type="text" wire:model="metaTitle.ar" maxlength="60" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl">
                            <p class="text-xs text-gray-500 mt-1">{{ strlen($metaTitle['ar']) }}/60 {{ __('characters') }}</p>
                            @error('metaTitle.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Meta Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Meta Description') }} (EN)</label>
                            <textarea wire:model="metaDescription.en" rows="3" maxlength="160" class="w-full border-gray-300 rounded-lg shadow-sm"></textarea>
                            <p class="text-xs text-gray-500 mt-1">{{ strlen($metaDescription['en']) }}/160 {{ __('characters') }}</p>
                            @error('metaDescription.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Default Meta Description') }} (AR)</label>
                            <textarea wire:model="metaDescription.ar" rows="3" maxlength="160" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl"></textarea>
                            <p class="text-xs text-gray-500 mt-1">{{ strlen($metaDescription['ar']) }}/160 {{ __('characters') }}</p>
                            @error('metaDescription.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Meta Keywords -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Meta Keywords') }} (EN)</label>
                            <input type="text" wire:model="metaKeywords.en" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="keyword1, keyword2, keyword3">
                            @error('metaKeywords.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Meta Keywords') }} (AR)</label>
                            <input type="text" wire:model="metaKeywords.ar" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl" placeholder="كلمة1، كلمة2، كلمة3">
                            @error('metaKeywords.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Analytics -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Google Analytics ID') }}</label>
                                <input type="text" wire:model="googleAnalyticsId" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="G-XXXXXXXXXX">
                                @error('googleAnalyticsId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Facebook Pixel ID') }}</label>
                                <input type="text" wire:model="facebookPixelId" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="XXXXXXXXXXXXXXXX">
                                @error('facebookPixelId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ __('Save SEO Settings') }}
                        </button>
                    </div>
                </form>
            @endif

            <!-- Maintenance Tab -->
            @if($activeTab === 'maintenance')
                <form wire:submit.prevent="saveMaintenanceSettings" class="space-y-6">
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    {{ __('Warning: Enabling maintenance mode will make your site inaccessible to visitors.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Maintenance Mode Toggle -->
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="maintenanceMode" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm font-medium text-gray-700">{{ __('Enable Maintenance Mode') }}</span>
                        </label>

                        <!-- Maintenance Message -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Maintenance Message') }} (EN)</label>
                            <textarea wire:model="maintenanceMessage.en" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm"></textarea>
                            @error('maintenanceMessage.en') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Maintenance Message') }} (AR)</label>
                            <textarea wire:model="maintenanceMessage.ar" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm" dir="rtl"></textarea>
                            @error('maintenanceMessage.ar') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ __('Save Maintenance Settings') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>