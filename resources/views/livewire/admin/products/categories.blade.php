<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $editingCategoryId = null;
    public ?int $parent_id = null;
    public string $slug = '';
    public string $icon = '';
    public int $sort_order = 0;
    public bool $is_active = true;
    public bool $is_featured = false;
    
    // Translations
    public array $translations = [
        'en' => ['name' => '', 'description' => '', 'meta_title' => '', 'meta_description' => ''],
        'ar' => ['name' => '', 'description' => '', 'meta_title' => '', 'meta_description' => '']
    ];
    
    public bool $showForm = false;
    public $categories;

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        $this->loadCategories();
    }

    public function loadCategories()
    {
        $this->categories = Category::whereNull('parent_id')->get();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTranslationsEnName()
    {
        if (!$this->editingCategoryId && $this->translations['en']['name']) {
            $this->slug = Str::slug($this->translations['en']['name']);
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($categoryId)
    {
        $category = Category::with('translations')->find($categoryId);
        
        if (!$category) return;

        $this->editingCategoryId = $category->id;
        $this->parent_id = $category->parent_id;
        $this->slug = $category->slug;
        $this->icon = $category->icon ?? '';
        $this->sort_order = $category->sort_order;
        $this->is_active = $category->is_active;
        $this->is_featured = $category->is_featured;
        
        // Load translations
        foreach (['en', 'ar'] as $locale) {
            $translation = $category->translations->where('locale', $locale)->first();
            if ($translation) {
                $this->translations[$locale] = [
                    'name' => $translation->name,
                    'description' => $translation->description ?? '',
                    'meta_title' => $translation->meta_title ?? '',
                    'meta_description' => $translation->meta_description ?? ''
                ];
            }
        }
        
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'slug' => 'required|string|unique:categories,slug,' . $this->editingCategoryId,
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'required|integer|min:0',
            'translations.en.name' => 'required|string|max:255',
            'translations.ar.name' => 'required|string|max:255',
        ]);

        $data = [
            'parent_id' => $this->parent_id,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
        ];

        if ($this->editingCategoryId) {
            $category = Category::find($this->editingCategoryId);
            $category->update($data);
            $message = 'Category updated successfully';
        } else {
            $category = Category::create($data);
            $message = 'Category created successfully';
        }

        // Save translations
        foreach ($this->translations as $locale => $translation) {
            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                $translation
            );
        }

        $this->resetForm();
        $this->loadCategories();
        
        $this->dispatch('toast', 
            type: 'success',
            message: $message
        );
    }

    public function toggleStatus($categoryId)
    {
        $category = Category::find($categoryId);
        if ($category) {
            $category->update(['is_active' => !$category->is_active]);
            $this->dispatch('toast', 
                type: 'success',
                message: 'Category status updated'
            );
        }
    }

    public function toggleFeatured($categoryId)
    {
        $category = Category::find($categoryId);
        if ($category) {
            $category->update(['is_featured' => !$category->is_featured]);
            $this->dispatch('toast', 
                type: 'success',
                message: 'Category featured status updated'
            );
        }
    }

    public function delete($categoryId)
    {
        $category = Category::find($categoryId);
        
        if ($category) {
            // Check if category has children
            if ($category->children()->exists()) {
                $this->dispatch('toast', 
                    type: 'error',
                    message: 'Cannot delete category with subcategories'
                );
                return;
            }
            
            // Check if category has products
            if ($category->products()->exists()) {
                $this->dispatch('toast', 
                    type: 'error',
                    message: 'Cannot delete category with products'
                );
                return;
            }
            
            $category->delete();
            $this->loadCategories();
            
            $this->dispatch('toast', 
                type: 'success',
                message: 'Category deleted successfully'
            );
        }
    }

    public function resetForm()
    {
        $this->editingCategoryId = null;
        $this->parent_id = null;
        $this->slug = '';
        $this->icon = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->is_featured = false;
        $this->translations = [
            'en' => ['name' => '', 'description' => '', 'meta_title' => '', 'meta_description' => ''],
            'ar' => ['name' => '', 'description' => '', 'meta_title' => '', 'meta_description' => '']
        ];
        $this->showForm = false;
    }

    public function with()
    {
        $query = Category::withCount('products');

        if ($this->search) {
            $query->whereHas('translations', function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            });
        }

        return [
            'categoriesList' => $query->paginate(10),
            'layout' => 'components.layouts.admin',
        ];
    }
}; ?>

<div>
    <div class="p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Categories Management</h1>
            @if(!$showForm)
                <button wire:click="create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Add Category
                </button>
            @endif
        </div>

        @if($showForm)
            <!-- Category Form -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">
                    {{ $editingCategoryId ? 'Edit Category' : 'Create Category' }}
                </h2>

                <form wire:submit="save">
                    <!-- Nav Tabs -->
                    <div class="border-b border-gray-200 mb-4">
                        <nav class="-mb-px flex space-x-8">
                            <button type="button" class="py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                                General
                            </button>
                        </nav>
                    </div>

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

                        <!-- Parent Category -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parent Category</label>
                            <select wire:model="parent_id" class="w-full border-gray-300 rounded-lg">
                                <option value="">None (Top Level)</option>
                                @foreach($categories as $category)
                                    @if($category->id !== $editingCategoryId)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @foreach($category->children as $child)
                                            @if($child->id !== $editingCategoryId)
                                                <option value="{{ $child->id }}">-- {{ $child->name }}</option>
                                            @endif
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <!-- Icon -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Icon Class</label>
                            <input type="text" wire:model="icon" placeholder="e.g., laptop, shirt" class="w-full border-gray-300 rounded-lg">
                        </div>

                        <!-- Sort Order -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" wire:model="sort_order" class="w-full border-gray-300 rounded-lg" min="0">
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

                        <!-- Status Toggles -->
                        <div class="md:col-span-2 flex gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_featured" class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2 text-sm text-gray-700">Featured</span>
                            </label>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="resetForm" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            {{ $editingCategoryId ? 'Update' : 'Create' }} Category
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
                    placeholder="Search categories..."
                    class="w-full md:w-1/3 border-gray-300 rounded-lg"
                >
            </div>

            <!-- Categories Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Featured</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($categoriesList as $category)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($category->icon)
                                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-gray-100 rounded">
                                                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $category->name }}</div>
                                            @if($category->parent)
                                                <div class="text-sm text-gray-500">Under: {{ $category->parent->name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $category->slug }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $category->products_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button wire:click="toggleStatus({{ $category->id }})" class="inline-flex">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button wire:click="toggleFeatured({{ $category->id }})">
                                        @if($category->is_featured)
                                            <svg class="w-5 h-5 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                            </svg>
                                        @endif
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button wire:click="edit({{ $category->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button 
                                        wire:click="delete({{ $category->id }})"
                                        wire:confirm="Are you sure you want to delete this category?"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Show children -->
                            @foreach($category->children as $child)
                                <tr class="bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap pl-12">
                                        <div class="text-sm font-medium text-gray-900">— {{ $child->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $child->slug }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $child->products_count ?? 0 }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button wire:click="toggleStatus({{ $child->id }})" class="inline-flex">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $child->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $child->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button wire:click="toggleFeatured({{ $child->id }})">
                                            @if($child->is_featured)
                                                <svg class="w-5 h-5 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                                </svg>
                                            @endif
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button wire:click="edit({{ $child->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                        <button 
                                            wire:click="delete({{ $child->id }})"
                                            wire:confirm="Are you sure you want to delete this category?"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No categories found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t">
                    {{ $categoriesList->links() }}
                </div>
            </div>
        @endif
    </div>
</div>