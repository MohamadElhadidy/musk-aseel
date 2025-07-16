<?php

use Livewire\Volt\Component;
use App\Models\Banner;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $type = '';
    public string $status = '';
    public bool $showModal = false;
    public bool $editMode = false;
    
    // Form fields
    public ?int $bannerId = null;
    public string $title = '';
    public string $titleAr = '';
    public string $subtitle = '';
    public string $subtitleAr = '';
    public string $link = '';
    public $image = null;
    public string $existingImage = '';
    public string $bannerType = 'promotional';
    public string $position = 'sidebar';
    public string $backgroundColor = '#ffffff';
    public string $textColor = '#000000';
    public int $sortOrder = 0;
    public bool $isActive = true;
    public ?string $startsAt = null;
    public ?string $endsAt = null;

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

    public function createBanner()
    {
        $this->reset(['bannerId', 'title', 'titleAr', 'subtitle', 'subtitleAr', 
                     'link', 'image', 'existingImage', 'bannerType', 'position',
                     'backgroundColor', 'textColor', 'sortOrder', 'isActive',
                     'startsAt', 'endsAt']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function editBanner($id)
    {
        $banner = Banner::findOrFail($id);
        
        $this->bannerId = $banner->id;
        $this->title = $banner->getTranslation('title', 'en') ?? '';
        $this->titleAr = $banner->getTranslation('title', 'ar') ?? '';
        $this->subtitle = $banner->getTranslation('subtitle', 'en') ?? '';
        $this->subtitleAr = $banner->getTranslation('subtitle', 'ar') ?? '';
        $this->link = $banner->link ?? '';
        $this->existingImage = $banner->image ?? '';
        $this->bannerType = $banner->type;
        $this->position = $banner->position;
        $this->backgroundColor = $banner->background_color ?? '#ffffff';
        $this->textColor = $banner->text_color ?? '#000000';
        $this->sortOrder = $banner->sort_order;
        $this->isActive = $banner->is_active;
        $this->startsAt = $banner->starts_at?->format('Y-m-d\TH:i');
        $this->endsAt = $banner->ends_at?->format('Y-m-d\TH:i');
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function saveBanner()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'titleAr' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'subtitleAr' => 'nullable|string|max:500',
            'link' => 'nullable|string|max:255',
            'bannerType' => 'required|in:promotional,sale,announcement,brand',
            'position' => 'required|in:sidebar,header,footer,popup',
            'backgroundColor' => 'required|string|max:7',
            'textColor' => 'required|string|max:7',
            'sortOrder' => 'required|integer|min:0',
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after:startsAt',
        ];

        if (!$this->editMode || $this->image) {
            $rules['image'] = 'required|image|max:2048|mimes:jpg,jpeg,png,webp';
        }

        $this->validate($rules);

        $data = [
            'title' => ['en' => $this->title, 'ar' => $this->titleAr],
            'subtitle' => ['en' => $this->subtitle, 'ar' => $this->subtitleAr],
            'link' => $this->link,
            'type' => $this->bannerType,
            'position' => $this->position,
            'background_color' => $this->backgroundColor,
            'text_color' => $this->textColor,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];

        if ($this->image) {
            if ($this->editMode && $this->existingImage) {
                Storage::disk('public')->delete($this->existingImage);
            }
            
            $data['image'] = $this->image->store('banners', 'public');
        }

        if ($this->editMode) {
            Banner::find($this->bannerId)->update($data);
            $message = 'Banner updated successfully';
        } else {
            Banner::create($data);
            $message = 'Banner created successfully';
        }

        $this->showModal = false;
        $this->reset(['image']);
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteBanner($id)
    {
        $banner = Banner::findOrFail($id);
        
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        
        $banner->delete();
        
        $this->dispatch('toast', type: 'success', message: 'Banner deleted successfully');
    }

    public function toggleStatus($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->update(['is_active' => !$banner->is_active]);
        
        $status = $banner->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', type: 'success', message: "Banner {$status} successfully");
    }

    public function duplicateBanner($id)
    {
        $banner = Banner::findOrFail($id);
        
        $newBanner = $banner->replicate();
        $newBanner->title = ['en' => $banner->title . ' (Copy)', 'ar' => $banner->title . ' (نسخة)'];
        $newBanner->is_active = false;
        $newBanner->save();
        
        $this->dispatch('toast', type: 'success', message: 'Banner duplicated successfully');
    }

    public function with()
    {
        $query = Banner::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title->en', 'like', "%{$this->search}%")
                  ->orWhere('title->ar', 'like', "%{$this->search}%");
            });
        }

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->status !== '') {
            $query->where('is_active', $this->status);
        }

        return [
            'banners' => $query->orderBy('position')->orderBy('sort_order')->paginate(12),
            'bannerTypes' => [
                'promotional' => ['label' => 'Promotional', 'color' => 'blue'],
                'sale' => ['label' => 'Sale', 'color' => 'red'],
                'announcement' => ['label' => 'Announcement', 'color' => 'yellow'],
                'brand' => ['label' => 'Brand', 'color' => 'purple'],
            ],
            'positions' => [
                'sidebar' => 'Sidebar',
                'header' => 'Header',
                'footer' => 'Footer',
                'popup' => 'Popup',
            ]
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Banners</h1>
            <p class="text-sm text-gray-600 mt-1">Manage promotional banners across your site</p>
        </div>
        <button wire:click="createBanner" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Banner
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Search banners..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select wire:model.live="type" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Types</option>
                    @foreach($bannerTypes as $value => $type)
                        <option value="{{ $value }}">{{ $type['label'] }}</option>
                    @endforeach
                </select>
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

    <!-- Banners Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($banners as $banner)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <!-- Banner Preview -->
                <div class="relative aspect-[16/9] {{ $banner->position === 'sidebar' ? 'aspect-[3/4]' : '' }}">
                    @if($banner->image)
                        <img src="{{ Storage::url($banner->image) }}" 
                             alt="{{ $banner->title }}"
                             class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center" 
                             style="background-color: {{ $banner->background_color }}">
                            <svg class="w-12 h-12 opacity-20" style="color: {{ $banner->text_color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif
                    
                    <!-- Status Badge -->
                    <div class="absolute top-2 right-2 flex gap-2">
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $bannerTypes[$banner->type]['color'] }}-100 text-{{ $bannerTypes[$banner->type]['color'] }}-800">
                            {{ $bannerTypes[$banner->type]['label'] }}
                        </span>
                        @if(!$banner->is_active)
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                Inactive
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Banner Details -->
                <div class="p-4">
                    <h3 class="font-medium text-gray-900">{{ $banner->getTranslation('title', 'en') }}</h3>
                    @if($banner->subtitle)
                        <p class="text-sm text-gray-600 mt-1">{{ Str::limit($banner->getTranslation('subtitle', 'en'), 50) }}</p>
                    @endif
                    
                    <div class="mt-3 flex items-center justify-between text-sm text-gray-500">
                        <span>{{ $positions[$banner->position] }}</span>
                        @if($banner->starts_at || $banner->ends_at)
                            <span>{{ $banner->starts_at?->format('M d') }} - {{ $banner->ends_at?->format('M d') }}</span>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 flex items-center justify-between">
                        <button wire:click="toggleStatus({{ $banner->id }})" 
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $banner->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $banner->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                        
                        <div class="flex items-center gap-1">
                            <button wire:click="editBanner({{ $banner->id }})" 
                                    class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button wire:click="duplicateBanner({{ $banner->id }})" 
                                    class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                            <button wire:click="deleteBanner({{ $banner->id }})"
                                    wire:confirm="Are you sure you want to delete this banner?"
                                    class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 lg:col-span-3 text-center py-12">
                <svg class="w-12 h-12 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-gray-500 mt-2">No banners found</p>
            </div>
        @endforelse
    </div>

    @if($banners->hasPages())
        <div class="flex justify-center">
            {{ $banners->links() }}
        </div>
    @endif

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
            
            <div class="relative bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editMode ? 'Edit' : 'Create' }} Banner
                </h3>
                
                <form wire:submit="saveBanner">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title (English)</label>
                            <input type="text" 
                                   wire:model="title" 
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

                        <!-- Subtitle -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subtitle (English)</label>
                            <textarea wire:model="subtitle" 
                                      rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('subtitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subtitle (Arabic)</label>
                            <textarea wire:model="subtitleAr" 
                                      rows="2"
                                      dir="rtl"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('subtitleAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Link -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Link URL</label>
                            <input type="text" 
                                   wire:model="link" 
                                   placeholder="/products, /categories/electronics, etc."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('link') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Type & Position -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Banner Type</label>
                            <select wire:model="bannerType" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @foreach($bannerTypes as $value => $type)
                                    <option value="{{ $value }}">{{ $type['label'] }}</option>
                                @endforeach
                            </select>
                            @error('bannerType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                            <select wire:model="position" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                @foreach($positions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Colors -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Background Color</label>
                            <div class="flex gap-2">
                                <input type="color" 
                                       wire:model="backgroundColor" 
                                       class="h-10 w-20 border border-gray-300 rounded-lg">
                                <input type="text" 
                                       wire:model="backgroundColor" 
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            @error('backgroundColor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Text Color</label>
                            <div class="flex gap-2">
                                <input type="color" 
                                       wire:model="textColor" 
                                       class="h-10 w-20 border border-gray-300 rounded-lg">
                                <input type="text" 
                                       wire:model="textColor" 
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            @error('textColor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Image -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                            <input type="file" 
                                   wire:model="image" 
                                   accept="image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            
                            @if($image)
                                <div class="mt-2">
                                    <img src="{{ $image->temporaryUrl() }}" class="h-32 object-cover rounded-lg">
                                </div>
                            @elseif($editMode && $existingImage)
                                <div class="mt-2">
                                    <img src="{{ Storage::url($existingImage) }}" class="h-32 object-cover rounded-lg">
                                </div>
                            @endif
                        </div>

                        <!-- Sort Order -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" 
                                   wire:model="sortOrder" 
                                   min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('sortOrder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Schedule -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date (Optional)</label>
                            <input type="datetime-local" 
                                   wire:model="startsAt" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('startsAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date (Optional)</label>
                            <input type="datetime-local" 
                                   wire:model="endsAt" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('endsAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Status -->
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="isActive" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $editMode ? 'Update' : 'Create' }} Banner
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