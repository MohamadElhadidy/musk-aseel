<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    // Profile fields
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public ?string $date_of_birth = null;
    public string $gender = '';
    public string $preferred_locale = '';
    
    // Password fields
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';
    
    public string $activeTab = 'profile';

    public function mount()
    {
        if (!auth()->check()) {
            $this->redirect('/login', navigate: true);
            return;
        }

        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d');
        $this->gender = $user->gender ?? '';
        $this->preferred_locale = $user->preferred_locale;
    }

    public function updateProfile()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'preferred_locale' => 'required|in:en,ar',
        ]);

        auth()->user()->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'preferred_locale' => $this->preferred_locale,
        ]);

        // Update session locale if changed
        if ($this->preferred_locale !== session('locale')) {
            session(['locale' => $this->preferred_locale]);
        }

        $this->dispatch('toast', 
            type: 'success',
            message: __('Profile updated successfully')
        );
    }

    public function updatePassword()
    {
        $this->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($this->current_password, auth()->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The provided password does not match your current password.'),
            ]);
        }

        auth()->user()->update([
            'password' => Hash::make($this->new_password),
        ]);

        // Reset password fields
        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        $this->dispatch('toast', 
            type: 'success',
            message: __('Password updated successfully')
        );
    }

    public function deleteAccount()
    {
        // This is a dangerous operation, you might want to add additional confirmation
        $user = auth()->user();
        
        // Log out the user
        auth()->logout();
        
        // Soft delete or hard delete based on your requirements
        $user->delete();
        
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6">
                <!-- User Info -->
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-semibold text-blue-600">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <h3 class="font-semibold text-gray-900">{{ auth()->user()->name }}</h3>
                    <p class="text-sm text-gray-600">{{ auth()->user()->email }}</p>
                </div>

                <!-- Navigation -->
                <nav class="space-y-1">
                    <a href="/account" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        {{ __('Dashboard') }}
                    </a>

                    <a href="/account/orders" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        {{ __('Orders') }}
                    </a>

                    <a href="/account/addresses" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        {{ __('Addresses') }}
                    </a>

                    <a href="/wishlist" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        {{ __('Wishlist') }}
                    </a>

                    <a href="/account/profile" wire:navigate class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg">
                        <svg class="w-5 h-5 {{ app()->getLocale() === 'ar' ? 'ml-3' : 'mr-3' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        {{ __('Profile Settings') }}
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow">
                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button 
                            wire:click="$set('activeTab', 'profile')"
                            class="py-4 px-6 text-sm font-medium {{ $activeTab === 'profile' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                        >
                            {{ __('Profile Information') }}
                        </button>
                        <button 
                            wire:click="$set('activeTab', 'password')"
                            class="py-4 px-6 text-sm font-medium {{ $activeTab === 'password' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                        >
                            {{ __('Change Password') }}
                        </button>
                        <button 
                            wire:click="$set('activeTab', 'delete')"
                            class="py-4 px-6 text-sm font-medium {{ $activeTab === 'delete' ? 'border-b-2 border-red-500 text-red-600' : 'text-gray-500 hover:text-gray-700' }}"
                        >
                            {{ __('Delete Account') }}
                        </button>
                    </nav>
                </div>

                <!-- Profile Tab -->
                @if($activeTab === 'profile')
                    <form wire:submit="updateProfile" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Full Name') }}
                                </label>
                                <input 
                                    type="text" 
                                    id="name"
                                    wire:model="name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Email Address') }}
                                </label>
                                <input 
                                    type="email" 
                                    id="email"
                                    wire:model="email"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Phone Number') }}
                                </label>
                                <input 
                                    type="tel" 
                                    id="phone"
                                    wire:model="phone"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Date of Birth -->
                            <div>
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Date of Birth') }}
                                </label>
                                <input 
                                    type="date" 
                                    id="date_of_birth"
                                    wire:model="date_of_birth"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                                @error('date_of_birth')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Gender -->
                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Gender') }}
                                </label>
                                <select 
                                    id="gender"
                                    wire:model="gender"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">{{ __('Select Gender') }}</option>
                                    <option value="male">{{ __('Male') }}</option>
                                    <option value="female">{{ __('Female') }}</option>
                                    <option value="other">{{ __('Other') }}</option>
                                </select>
                                @error('gender')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Preferred Language -->
                            <div>
                                <label for="preferred_locale" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Preferred Language') }}
                                </label>
                                <select 
                                    id="preferred_locale"
                                    wire:model="preferred_locale"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="en">English</option>
                                    <option value="ar">العربية</option>
                                </select>
                                @error('preferred_locale')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-6">
                            <button 
                                type="submit"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                            >
                                {{ __('Save Changes') }}
                            </button>
                        </div>
                    </form>
                @endif

                <!-- Password Tab -->
                @if($activeTab === 'password')
                    <form wire:submit="updatePassword" class="p-6">
                        <div class="max-w-lg">
                            <!-- Current Password -->
                            <div class="mb-4">
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Current Password') }}
                                </label>
                                <input 
                                    type="password" 
                                    id="current_password"
                                    wire:model="current_password"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('current_password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- New Password -->
                            <div class="mb-4">
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('New Password') }}
                                </label>
                                <input 
                                    type="password" 
                                    id="new_password"
                                    wire:model="new_password"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('new_password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirm New Password -->
                            <div class="mb-6">
                                <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Confirm New Password') }}
                                </label>
                                <input 
                                    type="password" 
                                    id="new_password_confirmation"
                                    wire:model="new_password_confirmation"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                            </div>

                            <button 
                                type="submit"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                            >
                                {{ __('Update Password') }}
                            </button>
                        </div>
                    </form>
                @endif

                <!-- Delete Account Tab -->
                @if($activeTab === 'delete')
                    <div class="p-6">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }}">
                                    <h3 class="text-sm font-medium text-red-800">
                                        {{ __('Warning: This action is permanent') }}
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>{{ __('Once you delete your account, there is no going back. Please be certain.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="prose max-w-none text-gray-600 mb-6">
                            <p>{{ __('Deleting your account will:') }}</p>
                            <ul>
                                <li>{{ __('Permanently delete your profile information') }}</li>
                                <li>{{ __('Cancel any pending orders') }}</li>
                                <li>{{ __('Remove your addresses and payment methods') }}</li>
                                <li>{{ __('Delete your order history') }}</li>
                                <li>{{ __('Remove you from our mailing list') }}</li>
                            </ul>
                        </div>

                        <button 
                            wire:click="deleteAccount"
                            wire:confirm="{{ __('Are you absolutely sure you want to delete your account? This action cannot be undone.') }}"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                        >
                            {{ __('Delete My Account') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>