<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Brand;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public ?int $editingBrandId = null;
    public string $slug = '';
    public $logo = null;
    public $currentLogo = null;
    public bool $is_active = true;
    
      
    #[Layout('components.layouts.admin')]
    public function mount()
    {
    }

    // Translations
    public array $translations = [
        'en' => ['name' => '', 'description' => ''],
        'ar' => ['name' => '', 'description' => '']
    ];
    
    public bool $showForm = false;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTranslationsEnName()
    {
        if (!$this->editingBrandId && $this->translations['en']['name']) {
            $this->slug = Str::slug($this->translations['en']['name']);
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($brandId)
    {
        $brand = Brand::with('translations')->find($brandId);
        
        if (!$brand) return;

        $this->editingBrandId = $brand->id;
        $this->slug = $brand->slug;
        $this->currentLogo = $brand->logo;
        $this->is_active = $brand->is_active;
        
        // Load translations
        foreach (['en', 'ar'] as $locale) {
            $translation = $brand->translations->where('locale', $locale)->first();
            if ($translation) {
                $this->translations[$locale] = [
                    'name' => $translation->name,
                    'description' => $translation->description ?? ''
                ];
            }
        }
        
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'slug' => 'required|string|unique:brands,slug,' . $this->editingBrandId,
            'logo' => 'nullable|image|max:2048',
            'translations.en.name' => 'required|string|max:255',
            'translations.ar.name' => 'required|string|max:255',
        ]);

        $data = [
            'slug' => $this->slug,
            'is_active' => $this->is_active,
        ];

        // Handle logo upload
        if ($this->logo) {
            $data['logo'] = $this->logo->store('brands', 'public');
        }

        if ($this->editingBrandId) {
            $brand = Brand::find($this->editingBrandId);
            $brand->update($data);
            $message = 'Brand updated successfully';
        } else {
            $brand = Brand::create($data);
            $message = 'Brand created successfully';
        }

        // Save translations
        foreach ($this->translations as $locale => $translation) {
            $brand->translations()->updateOrCreate(
                ['locale' => $locale],
                $translation
            );
        }

        $this->resetForm();
        
        $this->dispatch('toast', 
            type: 'success',
            message: $message
        );
    }

    public function toggleStatus($brandId)
    {
        $brand = Brand::find($brandId);
        if ($brand) {
            $brand->update(['is_active' => !$brand->is_active]);
            $this->dispatch('toast', 
                type: 'success',
                message: 'Brand deleted successfully'
            );
        }
    }

    public function resetForm()
    {
        $this->editingBrandId = null;
        $this->slug = '';
        $this->logo = null;
        $this->currentLogo = null;
        $this->is_active = true;
        $this->translations = [
            'en' => ['name' => '', 'description' => ''],
            'ar' => ['name' => '', 'description' => '']
        ];
        $this->showForm = false;
    }

    public function with()
    {
        $query = Brand::withCount('products');

        if ($this->search) {
            $query->whereHas('translations', function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            });
        }

        return [
            'brands' => $query->paginate(10),
            'layout' => 'components.layouts.admin',
        ];
    }
}; ?>

<div>
    <div class="p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Brands Management</h1>
            @if(!$showForm)
                <button wire:click="create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Add Brand
                </button>
            @endif
        </div>

        @if($showForm)
            <!-- Brand Form -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">
                    {{ $editingBrandId ? 'Edit Brand' : 'Create Brand' }}
                </h2>

                <form wire:submit="save">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- English Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Name (English) <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model.live="translations.en.name" class="w-full border-gray-300 rounded-lg" required>
                            @error('translations.en.name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Arabic Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Name (Arabic) <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="translations.ar.name" class="w-full border-gray-300 rounded-lg" required>
                            @error('translations.ar.name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Slug -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Slug <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="slug" class="w-full border-gray-300 rounded-lg" required>
                            @error('slug')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Logo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                            <input type="file" wire:model="logo" class="w-full" accept="image/*">
                            @error('logo')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                            
                            @if($logo)
                                <div class="mt-2">
                                    <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="h-20 object-contain">
                                </div>
                            @elseif($currentLogo)
                                <div class="mt-2">
                                    <img src="{{ asset('storage/' . $currentLogo) }}" alt="Current logo" class="h-20 object-contain">
                                </div>
                            @endif
                        </div>

                        <!-- English Description -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                            <textarea wire:model="translations.en.description" rows="3" class="w-full border-gray-300 rounded-lg"></textarea>
                        </div>

                        <!-- Arabic Description -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (Arabic)</label>
                            <textarea wire:model="translations.ar.description" rows="3" class="w-full border-gray-300 rounded-lg"></textarea>
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
                            {{ $editingBrandId ? 'Update' : 'Create' }} Brand
                        </button>
                    </div>
                </form>
            </div>
        @else
            <!-- Search -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <input 
                    type="text" 
                    wire:model.live="search"
                    placeholder="Search brands..."
                    class="w-full md:w-1/3 border-gray-300 rounded-lg"
                >
            </div>

            <!-- Brands Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($brands as $brand)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($brand->logo)
                                            <img src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}" class="h-10 w-10 object-contain mr-3">
                                        @else
                                            <div class="h-10 w-10 bg-gray-200 rounded flex items-center justify-center mr-3">
                                                <span class="text-gray-500 text-xs">{{ substr($brand->name, 0, 2) }}</span>
                                            </div>
                                        @endif
                                        <div class="text-sm font-medium text-gray-900">{{ $brand->name }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $brand->slug }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $brand->products_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button wire:click="toggleStatus({{ $brand->id }})" class="inline-flex">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $brand->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $brand->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button wire:click="edit({{ $brand->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button 
                                        wire:click="delete({{ $brand->id }})"
                                        wire:confirm="Are you sure you want to delete this brand?"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No brands found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t">
                    {{ $brands->links() }}
                </div>
            </div>
        @endif
    </div>
</div>