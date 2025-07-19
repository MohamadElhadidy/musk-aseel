<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Coupon;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    
    // Coupon form
    public ?int $editingCouponId = null;
    public string $code = '';
    public array $description = ['en' => '', 'ar' => ''];
    public string $type = 'percentage';
    public float $value = 0;
    public ?float $minimum_amount = null;
    public ?int $usage_limit = null;
    public ?int $usage_limit_per_user = null;
    public bool $is_active = true;
    public ?string $valid_from = null;
    public ?string $valid_until = null;
    
    public bool $showForm = false;
    
    #[Layout('components.layouts.admin')]
    public function mount()
    {
        //
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($couponId)
    {
        $coupon = Coupon::find($couponId);
        
        if (!$coupon) return;

        $this->editingCouponId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description ?? ['en' => '', 'ar' => ''];
        $this->type = $coupon->type;
        $this->value = $coupon->value;
        $this->minimum_amount = $coupon->minimum_amount;
        $this->usage_limit = $coupon->usage_limit;
        $this->usage_limit_per_user = $coupon->usage_limit_per_user;
        $this->is_active = $coupon->is_active;
        $this->valid_from = $coupon->valid_from?->format('Y-m-d\TH:i');
        $this->valid_until = $coupon->valid_until?->format('Y-m-d\TH:i');
        
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'code' => 'required|string|unique:coupons,code,' . $this->editingCouponId,
            'description.en' => 'required|string|max:255',
            'description.ar' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0' . ($this->type === 'percentage' ? '|max:100' : ''),
            'minimum_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
        ]);

        $data = [
            'code' => strtoupper($this->code),
            'description' => $this->description,
            'type' => $this->type,
            'value' => $this->value,
            'minimum_amount' => $this->minimum_amount,
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_user' => $this->usage_limit_per_user,
            'is_active' => $this->is_active,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
        ];

        if ($this->editingCouponId) {
            $coupon = Coupon::find($this->editingCouponId);
            $coupon->update($data);
            $message = 'Coupon updated successfully';
        } else {
            $data['used_count'] = 0;
            Coupon::create($data);
            $message = 'Coupon created successfully';
        }

        $this->resetForm();
        
        $this->dispatch('toast', 
            type: 'success',
            message: $message
        );
    }

    public function toggleStatus($couponId)
    {
        $coupon = Coupon::find($couponId);
        if ($coupon) {
            $coupon->update(['is_active' => !$coupon->is_active]);
            $this->dispatch('toast', 
                type: 'success',
                message: 'Coupon status updated'
            );
        }
    }

    public function delete($couponId)
    {
        $coupon = Coupon::find($couponId);
        
        if ($coupon) {
            // Check if coupon has been used
            if ($coupon->used_count > 0) {
                $this->dispatch('toast', 
                    type: 'error',
                    message: 'Cannot delete coupon that has been used'
                );
                return;
            }
            
            $coupon->delete();
            
            $this->dispatch('toast', 
                type: 'success',
                message: 'Coupon deleted successfully'
            );
        }
    }

    public function resetForm()
    {
        $this->editingCouponId = null;
        $this->code = '';
        $this->description = ['en' => '', 'ar' => ''];
        $this->type = 'percentage';
        $this->value = 0;
        $this->minimum_amount = null;
        $this->usage_limit = null;
        $this->usage_limit_per_user = null;
        $this->is_active = true;
        $this->valid_from = null;
        $this->valid_until = null;
        $this->showForm = false;
    }

    public function with()
    {
        $query = Coupon::withCount('users');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhereJsonContains('description->en', $this->search)
                  ->orWhereJsonContains('description->ar', $this->search);
            });
        }

        if ($this->status === 'active') {
            $query->where('is_active', true)
                  ->where(function ($q) {
                      $q->whereNull('valid_until')
                        ->orWhere('valid_until', '>', now());
                  });
        } elseif ($this->status === 'expired') {
            $query->where('valid_until', '<', now());
        } elseif ($this->status === 'inactive') {
            $query->where('is_active', false);
        }

        return [
            'coupons' => $query->latest()->paginate(10),
            'layout' => 'components.layouts.admin',
        ];
    }
}; ?>

<div>
    <div class="p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Coupons Management</h1>
            @if(!$showForm)
                <button wire:click="create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Add Coupon
                </button>
            @endif
        </div>

        @if($showForm)
            <!-- Coupon Form -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">
                    {{ $editingCouponId ? 'Edit Coupon' : 'Create Coupon' }}
                </h2>

                <form wire:submit="save">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Code -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Coupon Code <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="code" class="w-full border-gray-300 rounded-lg uppercase" required>
                            @error('code')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Discount Type <span class="text-red-500">*</span>
                            </label>
                            <select wire:model.live="type" class="w-full border-gray-300 rounded-lg" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>

                        <!-- Value -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Discount Value <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" wire:model="value" step="0.01" min="0" {{ $type === 'percentage' ? 'max=100' : '' }} class="w-full border-gray-300 rounded-lg pr-12" required>
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                                    {{ $type === 'percentage' ? '%' : '$' }}
                                </span>
                            </div>
                            @error('value')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Minimum Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Order Amount</label>
                            <input type="number" wire:model="minimum_amount" step="0.01" min="0" class="w-full border-gray-300 rounded-lg">
                            @error('minimum_amount')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- English Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Description (English) <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="description.en" class="w-full border-gray-300 rounded-lg" required>
                            @error('description.en')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Arabic Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Description (Arabic) <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="description.ar" class="w-full border-gray-300 rounded-lg" required>
                            @error('description.ar')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Usage Limit -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Usage Limit</label>
                            <input type="number" wire:model="usage_limit" min="1" class="w-full border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                        </div>

                        <!-- Usage Limit Per User -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Usage Limit Per Customer</label>
                            <input type="number" wire:model="usage_limit_per_user" min="1" class="w-full border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                        </div>

                        <!-- Valid From -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
                            <input type="datetime-local" wire:model="valid_from" class="w-full border-gray-300 rounded-lg">
                            @error('valid_from')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Valid Until -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
                            <input type="datetime-local" wire:model="valid_until" class="w-full border-gray-300 rounded-lg">
                            @error('valid_until')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="resetForm" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ $editingCouponId ? 'Update' : 'Create' }} Coupon
                        </button>
                    </div>
                </form>
            </div>
        @else
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input 
                        type="text" 
                        wire:model.live="search"
                        placeholder="Search coupons..."
                        class="border-gray-300 rounded-lg"
                    >
                    
                    <select wire:model.live="status" class="border-gray-300 rounded-lg">
                        <option value="">All Coupons</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Coupons Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid Until</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($coupons as $coupon)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $coupon->code }}</div>
                                    @if($coupon->minimum_amount)
                                        <div class="text-xs text-gray-500">Min: ${{ number_format($coupon->minimum_amount, 2) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $coupon->description }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $coupon->type === 'percentage' ? $coupon->value . '%' : '$' . number_format($coupon->value, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>Used: {{ $coupon->used_count }}</div>
                                    @if($coupon->usage_limit)
                                        <div class="text-xs">Limit: {{ $coupon->usage_limit }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $coupon->valid_until ? $coupon->valid_until->format('M d, Y') : 'No expiry' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button wire:click="toggleStatus({{ $coupon->id }})" class="inline-flex">
                                        @if(!$coupon->is_active)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Inactive
                                            </span>
                                        @elseif($coupon->valid_until && $coupon->valid_until < now())
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Expired
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        @endif
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button wire:click="edit({{ $coupon->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    @if($coupon->used_count == 0)
                                        <button 
                                            wire:click="delete({{ $coupon->id }})"
                                            wire:confirm="Are you sure you want to delete this coupon?"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            Delete
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    No coupons found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t">
                    {{ $coupons->links() }}
                </div>
            </div>
        @endif
    </div>
</div>