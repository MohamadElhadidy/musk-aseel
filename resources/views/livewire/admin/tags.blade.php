<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Tag;
use Illuminate\Support\Str;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public $editingTag = null;
    
    // Form fields
    public array $name = ['en' => '', 'ar' => ''];
    public string $slug = '';
    
    public bool $showForm = false;

    public function mount()
    {
        if (!auth()->user()?->is_admin) {
            $this->redirect('/', navigate: true);
        }
    }

    public function create()
    {
        $this->reset(['editingTag', 'name', 'slug']);
        $this->showForm = true;
    }

    public function edit($tagId)
    {
        $tag = Tag::findOrFail($tagId);
        $this->editingTag = $tag;
        $this->name = json_decode($tag->getAttributes()['name'], true) ?? ['en' => '', 'ar' => ''];
        $this->slug = $tag->slug;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tags,slug' . ($this->editingTag ? ',' . $this->editingTag->id : ''),
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
        ];

        if ($this->editingTag) {
            $this->editingTag->update($data);
            $message = __('Tag updated successfully');
        } else {
            Tag::create($data);
            $message = __('Tag created successfully');
        }

        $this->reset(['editingTag', 'name', 'slug', 'showForm']);
        
        $this->dispatch('toast', 
            type: 'success',
            message: $message
        );
    }

    public function generateSlug()
    {
        $this->slug = Str::slug($this->name['en']);
    }

    public function delete($tagId)
    {
        $tag = Tag::findOrFail($tagId);
        
        // Check if tag is used by products
        if ($tag->products()->exists()) {
            $this->dispatch('toast', 
                type: 'error',
                message: __('Cannot delete tag that is assigned to products')
            );
            return;
        }
        
        $tag->delete();
        
        $this->dispatch('toast', 
            type: 'success',
            message: __('Tag deleted successfully')
        );
    }

    public function with()
    {
        return [
            'tags' => Tag::withCount('products')
                ->when($this->search, function ($query) {
                    $query->where('name->en', 'like', "%{$this->search}%")
                          ->orWhere('name->ar', 'like', "%{$this->search}%")
                          ->orWhere('slug', 'like', "%{$this->search}%");
                })
                ->latest()
                ->paginate(10),
            'layout' => 'components.layouts.admin',
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Tags Management') }}</h1>
            @if(!$showForm)
                <button 
                    wire:click="create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    {{ __('Add New Tag') }}
                </button>
            @endif
        </div>
    </div>

    @if($showForm)
        <!-- Tag Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">
                {{ $editingTag ? __('Edit Tag') : __('Create New Tag') }}
            </h2>

            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- English Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Name (English)') }} <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="name.en"
                            wire:keyup="generateSlug"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        >
                        @error('name.en')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Arabic Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Name (Arabic)') }} <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="name.ar"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            dir="rtl"
                            required
                        >
                        @error('name.ar')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Slug -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Slug') }} <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="slug"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        >
                        @error('slug')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button 
                        type="button"
                        wire:click="$set('showForm', false)"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        {{ $editingTag ? __('Update Tag') : __('Create Tag') }}
                    </button>
                </div>
            </form>
        </div>
    @else
        <!-- Search -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search tags...') }}"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>

        <!-- Tags Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Tag') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Slug') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Products') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($tags as $tag)
                        <tr>
                            <td class="px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $tag->getTranslatedName('en') }}
                                    </p>
                                    <p class="text-sm text-gray-500" dir="rtl">
                                        {{ $tag->getTranslatedName('ar') }}
                                    </p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $tag->slug }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $tag->products_count }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <button 
                                    wire:click="edit({{ $tag->id }})"
                                    class="text-blue-600 hover:text-blue-900 mr-3"
                                >
                                    {{ __('Edit') }}
                                </button>
                                <button 
                                    wire:click="delete({{ $tag->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this tag?') }}"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                {{ __('No tags found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($tags->hasPages())
                <div class="px-6 py-4 border-t">
                    {{ $tags->links() }}
                </div>
            @endif
        </div>
    @endif
</div>