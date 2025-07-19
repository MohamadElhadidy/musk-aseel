<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination;

    // List properties
    public string $search = '';
    public string $status = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public int $perPage = 10;

    // Form properties
    public bool $showForm = false;
    public ?int $editingUserId = null;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $is_active = true;
    public array $permissions = [];
    
    // Details modal
    public bool $showDetails = false;
    public ?User $selectedUser = null;
    public array $userActivity = [];

    // Available permissions
    public array $availablePermissions = [
        'manage_products' => 'Manage Products',
        'manage_orders' => 'Manage Orders',
        'manage_categories' => 'Manage Categories',
        'manage_brands' => 'Manage Brands',
        'manage_users' => 'Manage Users',
        'manage_customers' => 'Manage Customers',
        'manage_coupons' => 'Manage Coupons',
        'manage_reviews' => 'Manage Reviews',
        'manage_settings' => 'Manage Settings',
        'manage_pages' => 'Manage Pages',
        'view_reports' => 'View Reports',
        'manage_shipping' => 'Manage Shipping',
        'manage_payments' => 'Manage Payments',
    ];

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }

        // Check if the current user can manage other admins
        if (!$this->canManageAdmins()) {
            $this->redirect('/admin', navigate: true);
            return;
        }
    }

    public function canManageAdmins(): bool
    {
        // You can implement your permission logic here
        // For now, we'll assume all admins can manage other admins
        return auth()->user()->is_admin;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'desc';
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($userId)
    {
        $user = User::where('is_admin', true)->find($userId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'Admin user not found');
            return;
        }

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->is_active = $user->is_active;
        $this->permissions = $user->permissions ?? [];
        $this->password = '';
        $this->password_confirmation = '';

        $this->showForm = true;
        $this->showDetails = false;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingUserId,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];

        if (!$this->editingUserId || $this->password) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        $validated = $this->validate($rules);

        try {
            if ($this->editingUserId) {
                $user = User::find($this->editingUserId);
                
                if (!$user) {
                    throw new \Exception('User not found');
                }

                // Prevent self-deactivation
                if ($user->id === auth()->id() && !$this->is_active) {
                    $this->dispatch('toast', type: 'error', message: 'You cannot deactivate your own account');
                    return;
                }

                $user->name = $this->name;
                $user->email = $this->email;
                $user->phone = $this->phone ?: null;
                $user->is_active = $this->is_active;
                $user->permissions = $this->permissions;

                if ($this->password) {
                    $user->password = Hash::make($this->password);
                }

                $user->save();

                $this->dispatch('toast', type: 'success', message: 'Admin user updated successfully');
            } else {
                $user = User::create([
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone ?: null,
                    'password' => Hash::make($this->password),
                    'is_admin' => true,
                    'is_active' => $this->is_active,
                    'permissions' => $this->permissions,
                    'email_verified_at' => now(),
                ]);

                $this->dispatch('toast', type: 'success', message: 'Admin user created successfully');
            }

            $this->resetForm();
            $this->showForm = false;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error saving admin user: ' . $e->getMessage());
        }
    }

    public function viewDetails($userId)
    {
        $this->selectedUser = User::where('is_admin', true)
            ->withCount(['orders', 'reviews'])
            ->find($userId);

        if (!$this->selectedUser) {
            $this->dispatch('toast', type: 'error', message: 'Admin user not found');
            return;
        }

        // Get user's recent activity (you can customize this based on your logging system)
        $this->userActivity = [
            'last_login' => $this->selectedUser->last_login_at ?? 'Never',
            'total_logins' => $this->selectedUser->login_count ?? 0,
            'created_users' => User::where('created_by', $userId)->count(),
            'modified_products' => 0, // Implement based on your audit log
            'processed_orders' => 0, // Implement based on your audit log
        ];

        $this->showDetails = true;
        $this->showForm = false;
    }

    public function toggleStatus($userId)
    {
        $user = User::where('is_admin', true)->find($userId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'Admin user not found');
            return;
        }

        // Prevent self-deactivation
        if ($user->id === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'You cannot change your own status');
            return;
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $this->dispatch('toast', 
            type: 'success', 
            message: 'Admin user ' . ($user->is_active ? 'activated' : 'deactivated') . ' successfully'
        );
    }

    public function delete($userId)
    {
        if (!$this->canDelete($userId)) {
            return;
        }

        $user = User::where('is_admin', true)->find($userId);

        if ($user) {
            $user->delete();
            $this->dispatch('toast', type: 'success', message: 'Admin user deleted successfully');
        }
    }

    public function canDelete($userId): bool
    {
        // Cannot delete yourself
        if ($userId === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'You cannot delete your own account');
            return false;
        }

        // Cannot delete the last active admin
        $activeAdmins = User::where('is_admin', true)
            ->where('is_active', true)
            ->count();

        if ($activeAdmins <= 1) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete the last active admin');
            return false;
        }

        return true;
    }

    public function resetForm()
    {
        $this->editingUserId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->is_active = true;
        $this->permissions = [];
        $this->resetValidation();
    }

    public function adminUsers()
    {
        $query = User::where('is_admin', true);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        if ($this->status !== '') {
            $query->where('is_active', $this->status);
        }

        return $query->orderBy($this->sortBy, $this->sortDir)->paginate($this->perPage);
    }
}; ?>

<div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Admin Users') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ __('Manage administrative users and their permissions') }}</p>
        </div>
        @if(!$showForm && !$showDetails)
            <button wire:click="create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('Add Admin User') }}
            </button>
        @endif
    </div>

    <!-- Admin User Form -->
    @if($showForm)
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-semibold">{{ $editingUserId ? __('Edit Admin User') : __('Create Admin User') }}</h2>
                <button wire:click="resetForm" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="save" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Name') }}</label>
                        <input type="text" wire:model="name" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Email') }}</label>
                        <input type="email" wire:model="email" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Phone') }} ({{ __('Optional') }})</label>
                        <input type="text" wire:model="phone" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Status') }}</label>
                        <select wire:model="is_active" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="1">{{ __('Active') }}</option>
                            <option value="0">{{ __('Inactive') }}</option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('Password') }} 
                            @if($editingUserId)
                                <span class="text-gray-500 font-normal">({{ __('Leave blank to keep current') }})</span>
                            @endif
                        </label>
                        <input type="password" wire:model="password" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Confirm Password') }}</label>
                        <input type="password" wire:model="password_confirmation" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Permissions -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-4">{{ __('Permissions') }}</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($availablePermissions as $key => $label)
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" wire:model="permissions" value="{{ $key }}" 
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="text-sm text-gray-700">{{ __($label) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-3 pt-6 border-t">
                    <button type="button" wire:click="resetForm" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        {{ $editingUserId ? __('Update Admin User') : __('Create Admin User') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Admin User Details -->
    @if($showDetails && $selectedUser)
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('Admin User Details') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $selectedUser->name }}</p>
                </div>
                <button wire:click="$set('showDetails', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="space-y-4">
                    <h3 class="font-medium text-gray-900">{{ __('Basic Information') }}</h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Email') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $selectedUser->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Phone') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $selectedUser->phone ?: __('Not provided') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Status') }}</dt>
                            <dd>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $selectedUser->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $selectedUser->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Member Since') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $selectedUser->created_at->format('M d, Y') }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Activity Summary -->
                <div class="space-y-4">
                    <h3 class="font-medium text-gray-900">{{ __('Activity Summary') }}</h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Last Login') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $userActivity['last_login'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Total Logins') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $userActivity['total_logins'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Created Users') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $userActivity['created_users'] }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Permissions -->
                <div class="col-span-full space-y-4">
                    <h3 class="font-medium text-gray-900">{{ __('Permissions') }}</h3>
                    @if(!empty($selectedUser->permissions))
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedUser->permissions as $permission)
                                @if(isset($availablePermissions[$permission]))
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                        {{ __($availablePermissions[$permission]) }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">{{ __('No specific permissions assigned') }}</p>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
                <button wire:click="edit({{ $selectedUser->id }})" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    {{ __('Edit User') }}
                </button>
            </div>
        </div>
    @endif

    <!-- Filters and Search -->
    @if(!$showForm && !$showDetails)
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <input type="text" wire:model.live.debounce.300ms="search" 
                        placeholder="{{ __('Search by name, email, or phone...') }}"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <!-- Status Filter -->
                <div>
                    <select wire:model.live="status" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __('All Status') }}</option>
                        <option value="1">{{ __('Active') }}</option>
                        <option value="0">{{ __('Inactive') }}</option>
                    </select>
                </div>

                <!-- Per Page -->
                <div>
                    <select wire:model.live="perPage" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="10">10 {{ __('per page') }}</option>
                        <option value="25">25 {{ __('per page') }}</option>
                        <option value="50">50 {{ __('per page') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Admin Users Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('name')" class="flex items-center gap-1">
                                {{ __('Name') }}
                                @if($sortBy === 'name')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="{{ $sortDir === 'asc' ? 'M7 11l5-5m0 0l5 5m-5-5v12' : 'M17 13l-5 5m0 0l-5-5m5 5V6' }}">
                                        </path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('email')" class="flex items-center gap-1">
                                {{ __('Email') }}
                                @if($sortBy === 'email')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="{{ $sortDir === 'asc' ? 'M7 11l5-5m0 0l5 5m-5-5v12' : 'M17 13l-5 5m0 0l-5-5m5 5V6' }}">
                                        </path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Phone') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Permissions') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('created_at')" class="flex items-center gap-1">
                                {{ __('Joined') }}
                                @if($sortBy === 'created_at')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="{{ $sortDir === 'asc' ? 'M7 11l5-5m0 0l5 5m-5-5v12' : 'M17 13l-5 5m0 0l-5-5m5 5V6' }}">
                                        </path>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->adminUsers() as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        @if($user->id === auth()->id())
                                            <span class="text-xs text-blue-600">{{ __('(You)') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->email }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->phone ?: __('N/A') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($user->permissions && count($user->permissions) > 0)
                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                        {{ count($user->permissions) }} {{ __('permissions') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">{{ __('All permissions') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleStatus({{ $user->id }})" 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} hover:opacity-80 transition-opacity"
                                    {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                    {{ $user->is_active ? __('Active') : __('Inactive') }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <button wire:click="viewDetails({{ $user->id }})" 
                                        class="text-blue-600 hover:text-blue-800 transition-colors">
                                        {{ __('View') }}
                                    </button>
                                    <button wire:click="edit({{ $user->id }})" 
                                        class="text-indigo-600 hover:text-indigo-800 transition-colors">
                                        {{ __('Edit') }}
                                    </button>
                                    @if($user->id !== auth()->id())
                                        <button wire:click="delete({{ $user->id }})" 
                                            wire:confirm="{{ __('Are you sure you want to delete this admin user?') }}"
                                            class="text-red-600 hover:text-red-800 transition-colors">
                                            {{ __('Delete') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                    <p class="text-gray-500 text-sm">{{ __('No admin users found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            <!-- Pagination -->
            @if($this->adminUsers()->hasPages())
                <div class="px-6 py-4 border-t">
                    {{ $this->adminUsers()->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Quick Stats -->
    @if(!$showForm && !$showDetails)
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ __('Total Admins') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ \App\Models\User::where('is_admin', true)->count() }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ __('Active Admins') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ \App\Models\User::where('is_admin', true)->where('is_active', true)->count() }}</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ __('Inactive Admins') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ \App\Models\User::where('is_admin', true)->where('is_active', false)->count() }}</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ __('Super Admins') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ \App\Models\User::where('is_admin', true)->whereJsonLength('permissions', 0)->orWhereNull('permissions')->count() }}</p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Listen for toast events
    window.addEventListener('toast', event => {
        const { type, message } = event.detail;
        
        // You can implement your toast notification here
        // For now, we'll use a simple alert
        if (type === 'error') {
            alert('Error: ' + message);
        } else if (type === 'success') {
            alert('Success: ' + message);
        }
    });
</script>
@endpush