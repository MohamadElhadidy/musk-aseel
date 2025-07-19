<?php

use Livewire\Volt\Component;
use App\Models\Page;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public bool $showModal = false;
    public bool $editMode = false;
    
    // Form fields
    public ?int $pageId = null;
    public string $title = '';
    public string $titleAr = '';
    public string $slug = '';
    public string $content = '';
    public string $contentAr = '';
    public string $metaTitle = '';
    public string $metaTitleAr = '';
    public string $metaDescription = '';
    public string $metaDescriptionAr = '';
    public string $metaKeywords = '';
    public string $metaKeywordsAr = '';
    public bool $isActive = true;
    public bool $showInHeader = false;
    public bool $showInFooter = true;
    public int $sortOrder = 0;

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTitle()
    {
        if (!$this->editMode) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function createPage()
    {
        $this->reset(['pageId', 'title', 'titleAr', 'slug', 'content', 'contentAr',
                     'metaTitle', 'metaTitleAr', 'metaDescription', 'metaDescriptionAr',
                     'metaKeywords', 'metaKeywordsAr', 'isActive', 'showInHeader', 
                     'showInFooter', 'sortOrder']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function editPage($id)
    {
        $page = Page::findOrFail($id);
        
        $this->pageId = $page->id;
        $this->title = $page->getTranslation('title', 'en') ?? '';
        $this->titleAr = $page->getTranslation('title', 'ar') ?? '';
        $this->slug = $page->slug;
        $this->content = $page->getTranslation('content', 'en') ?? '';
        $this->contentAr = $page->getTranslation('content', 'ar') ?? '';
        $this->metaTitle = $page->getTranslation('meta_title', 'en') ?? '';
        $this->metaTitleAr = $page->getTranslation('meta_title', 'ar') ?? '';
        $this->metaDescription = $page->getTranslation('meta_description', 'en') ?? '';
        $this->metaDescriptionAr = $page->getTranslation('meta_description', 'ar') ?? '';
        $this->metaKeywords = $page->getTranslation('meta_keywords', 'en') ?? '';
        $this->metaKeywordsAr = $page->getTranslation('meta_keywords', 'ar') ?? '';
        $this->isActive = $page->is_active;
        $this->showInHeader = $page->show_in_header;
        $this->showInFooter = $page->show_in_footer;
        $this->sortOrder = $page->sort_order;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function savePage()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'titleAr' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug' . ($this->editMode ? ',' . $this->pageId : ''),
            'content' => 'required|string',
            'contentAr' => 'required|string',
            'metaTitle' => 'nullable|string|max:255',
            'metaTitleAr' => 'nullable|string|max:255',
            'metaDescription' => 'nullable|string|max:500',
            'metaDescriptionAr' => 'nullable|string|max:500',
            'metaKeywords' => 'nullable|string|max:255',
            'metaKeywordsAr' => 'nullable|string|max:255',
            'sortOrder' => 'required|integer|min:0',
        ];

        $this->validate($rules);

        $data = [
            'title' => ['en' => $this->title, 'ar' => $this->titleAr],
            'slug' => $this->slug,
            'content' => ['en' => $this->content, 'ar' => $this->contentAr],
            'meta_title' => ['en' => $this->metaTitle, 'ar' => $this->metaTitleAr],
            'meta_description' => ['en' => $this->metaDescription, 'ar' => $this->metaDescriptionAr],
            'meta_keywords' => ['en' => $this->metaKeywords, 'ar' => $this->metaKeywordsAr],
            'is_active' => $this->isActive,
            'show_in_header' => $this->showInHeader,
            'show_in_footer' => $this->showInFooter,
            'sort_order' => $this->sortOrder,
        ];

        if ($this->editMode) {
            Page::find($this->pageId)->update($data);
            $message = 'Page updated successfully';
        } else {
            Page::create($data);
            $message = 'Page created successfully';
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deletePage($id)
    {
        // Prevent deletion of system pages
        $page = Page::findOrFail($id);
        $systemPages = ['terms-of-service', 'privacy-policy', 'about-us', 'contact-us'];
        
        if (in_array($page->slug, $systemPages)) {
            $this->dispatch('toast', type: 'error', message: 'System pages cannot be deleted');
            return;
        }
        
        $page->delete();
        
        $this->dispatch('toast', type: 'success', message: 'Page deleted successfully');
    }

    public function toggleStatus($id)
    {
        $page = Page::findOrFail($id);
        $page->update(['is_active' => !$page->is_active]);
        
        $status = $page->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', type: 'success', message: "Page {$status} successfully");
    }

    public function duplicatePage($id)
    {
        $page = Page::findOrFail($id);
        
        $newPage = $page->replicate();
        $newPage->title = ['en' => $page->title . ' (Copy)', 'ar' => $page->title . ' (نسخة)'];
        $newPage->slug = $page->slug . '-copy-' . time();
        $newPage->is_active = false;
        $newPage->save();
        
        $this->dispatch('toast', type: 'success', message: 'Page duplicated successfully');
    }

    public function with()
    {
        $query = Page::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title->en', 'like', "%{$this->search}%")
                  ->orWhere('title->ar', 'like', "%{$this->search}%")
                  ->orWhere('slug', 'like', "%{$this->search}%");
            });
        }

        if ($this->status !== '') {
            $query->where('is_active', $this->status);
        }

        return [
            'pages' => $query->orderBy('sort_order')->paginate(15)
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pages</h1>
            <p class="text-sm text-gray-600 mt-1">Manage static pages and content</p>
        </div>
        <button wire:click="createPage" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Page
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Search pages..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select wire:model.live="status" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Pages List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Display</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($pages as $page)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $page->getTranslation('title', 'en') }}</div>
                                <div class="text-sm text-gray-500">{{ $page->getTranslation('title', 'ar') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="/pages/{{ $page->slug }}" 
                                   target="_blank"
                                   class="text-sm text-blue-600 hover:text-blue-800">
                                    /pages/{{ $page->slug }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex gap-2">
                                    @if($page->show_in_header)
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            Header
                                        </span>
                                    @endif
                                    @if($page->show_in_footer)
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                            Footer
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleStatus({{ $page->id }})" 
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $page->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $page->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $page->updated_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <button wire:click="editPage({{ $page->id }})" 
                                            class="text-blue-600 hover:text-blue-800">
                                        Edit
                                    </button>
                                    <button wire:click="duplicatePage({{ $page->id }})" 
                                            class="text-green-600 hover:text-green-800">
                                        Duplicate
                                    </button>
                                    @if(!in_array($page->slug, ['terms-of-service', 'privacy-policy', 'about-us', 'contact-us']))
                                        <button wire:click="deletePage({{ $page->id }})"
                                                wire:confirm="Are you sure you want to delete this page?"
                                                class="text-red-600 hover:text-red-800">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No pages found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pages->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $pages->links() }}
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="$wire.showModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editMode ? 'Edit' : 'Create' }} Page
                </h3>
                
                <form wire:submit="savePage">
                    <div class="space-y-6">
                        <!-- Basic Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Basic Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title (English)</label>
                                    <input type="text" 
                                           wire:model.live="title" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title (Arabic)</label>
                                    <input type="text" 
                                           wire:model="titleAr" 
                                           dir="rtl"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    @error('titleAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">URL Slug</label>
                                    <div class="flex items-center">
                                        <span class="text-gray-500 mr-2">/pages/</span>
                                        <input type="text" 
                                               wire:model="slug" 
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Content</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Content (English)</label>
                                    <textarea wire:model="content" 
                                              rows="8"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                                    @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Content (Arabic)</label>
                                    <textarea wire:model="contentAr" 
                                              rows="8"
                                              dir="rtl"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                                    @error('contentAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SEO Settings -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">SEO Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Title (English)</label>
                                    <input type="text" 
                                           wire:model="metaTitle" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    @error('metaTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Title (Arabic)</label>
                                    <input type="text" 
                                           wire:model="metaTitleAr" 
                                           dir="rtl"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    @error('metaTitleAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description (English)</label>
                                    <textarea wire:model="metaDescription" 
                                              rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                                    @error('metaDescription') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description (Arabic)</label>
                                    <textarea wire:model="metaDescriptionAr" 
                                              rows="3"
                                              dir="rtl"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                                    @error('metaDescriptionAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Keywords (English)</label>
                                    <input type="text" 
                                           wire:model="metaKeywords" 
                                           placeholder="keyword1, keyword2, keyword3"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    @error('metaKeywords') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Keywords (Arabic)</label>
                                    <input type="text" 
                                           wire:model="metaKeywordsAr" 
                                           dir="rtl"
                                           placeholder="كلمة1، كلمة2، كلمة3"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    @error('metaKeywordsAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Display Settings -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Display Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                    <input type="number" 
                                           wire:model="sortOrder" 
                                           min="0"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    @error('sortOrder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               wire:model="isActive" 
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Active</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               wire:model="showInHeader" 
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Show in Header Menu</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               wire:model="showInFooter" 
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Show in Footer Menu</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $editMode ? 'Update' : 'Create' }} Page
                        </button>
                        <button type="button" 
                                wire:click="$set('showModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>