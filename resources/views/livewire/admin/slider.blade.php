<?php

use Livewire\Volt\Component;
use App\Models\Slider;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $status = '';
    public bool $showModal = false;
    public bool $editMode = false;
    
    // Form fields
    public ?int $sliderId = null;
    public string $title = '';
    public string $titleAr = '';
    public string $subtitle = '';
    public string $subtitleAr = '';
    public string $buttonText = '';
    public string $buttonTextAr = '';
    public string $buttonLink = '';
    public $image = null;
    public string $existingImage = '';
    public int $sortOrder = 0;
    public bool $isActive = true;
    public string $position = 'home';
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

    public function createSlider()
    {
        $this->reset(['sliderId', 'title', 'titleAr', 'subtitle', 'subtitleAr', 
                     'buttonText', 'buttonTextAr', 'buttonLink', 'image', 
                     'existingImage', 'sortOrder', 'isActive', 'position',
                     'startsAt', 'endsAt']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function editSlider($id)
    {
        $slider = Slider::findOrFail($id);
        
        $this->sliderId = $slider->id;
        $this->title = $slider->getTranslation('title', 'en') ?? '';
        $this->titleAr = $slider->getTranslation('title', 'ar') ?? '';
        $this->subtitle = $slider->getTranslation('subtitle', 'en') ?? '';
        $this->subtitleAr = $slider->getTranslation('subtitle', 'ar') ?? '';
        $this->buttonText = $slider->getTranslation('button_text', 'en') ?? '';
        $this->buttonTextAr = $slider->getTranslation('button_text', 'ar') ?? '';
        $this->buttonLink = $slider->button_link ?? '';
        $this->existingImage = $slider->image ?? '';
        $this->sortOrder = $slider->sort_order;
        $this->isActive = $slider->is_active;
        $this->position = $slider->position;
        $this->startsAt = $slider->starts_at?->format('Y-m-d\TH:i');
        $this->endsAt = $slider->ends_at?->format('Y-m-d\TH:i');
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function saveSlider()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'titleAr' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'subtitleAr' => 'nullable|string|max:500',
            'buttonText' => 'nullable|string|max:50',
            'buttonTextAr' => 'nullable|string|max:50',
            'buttonLink' => 'nullable|string|max:255',
            'sortOrder' => 'required|integer|min:0',
            'position' => 'required|in:home,category,product',
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
            'button_text' => ['en' => $this->buttonText, 'ar' => $this->buttonTextAr],
            'button_link' => $this->buttonLink,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'position' => $this->position,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];

        if ($this->image) {
            // Delete old image if editing
            if ($this->editMode && $this->existingImage) {
                Storage::disk('public')->delete($this->existingImage);
            }
            
            $data['image'] = $this->image->store('sliders', 'public');
        }

        if ($this->editMode) {
            Slider::find($this->sliderId)->update($data);
            $message = 'Slider updated successfully';
        } else {
            Slider::create($data);
            $message = 'Slider created successfully';
        }

        $this->showModal = false;
        $this->reset(['image']);
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteSlider($id)
    {
        $slider = Slider::findOrFail($id);
        
        if ($slider->image) {
            Storage::disk('public')->delete($slider->image);
        }
        
        $slider->delete();
        
        $this->dispatch('toast', type: 'success', message: 'Slider deleted successfully');
    }

    public function toggleStatus($id)
    {
        $slider = Slider::findOrFail($id);
        $slider->update(['is_active' => !$slider->is_active]);
        
        $status = $slider->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', type: 'success', message: "Slider {$status} successfully");
    }

    public function updateOrder($items)
    {
        foreach ($items as $item) {
            Slider::where('id', $item['value'])->update(['sort_order' => $item['order']]);
        }
        
        $this->dispatch('toast', type: 'success', message: 'Slider order updated successfully');
    }

    public function with()
    {
        $query = Slider::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title->en', 'like', "%{$this->search}%")
                  ->orWhere('title->ar', 'like', "%{$this->search}%")
                  ->orWhere('subtitle->en', 'like', "%{$this->search}%")
                  ->orWhere('subtitle->ar', 'like', "%{$this->search}%");
            });
        }

        if ($this->status !== '') {
            $query->where('is_active', $this->status);
        }

        return [
            'sliders' => $query->orderBy('sort_order')->paginate(10)
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sliders</h1>
            <p class="text-sm text-gray-600 mt-1">Manage homepage and promotional sliders</p>
        </div>
        <button wire:click="createSlider" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Slider
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Search sliders..."
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

    <!-- Sliders List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6">
            <div class="space-y-4" x-data="{ 
                sortable: null,
                init() {
                    this.sortable = new Sortable(this.$refs.slidersList, {
                        animation: 150,
                        ghostClass: 'bg-gray-100',
                        onEnd: (evt) => {
                            let items = [];
                            this.$refs.slidersList.querySelectorAll('[data-id]').forEach((el, index) => {
                                items.push({ value: el.dataset.id, order: index });
                            });
                            @this.updateOrder(items);
                        }
                    });
                }
            }" x-ref="slidersList">
                @forelse($sliders as $slider)
                    <div data-id="{{ $slider->id }}" class="flex items-center gap-4 p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow cursor-move">
                        <!-- Drag Handle -->
                        <div class="text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>

                        <!-- Image -->
                        <div class="flex-shrink-0">
                            @if($slider->image)
                                <img src="{{ Storage::url($slider->image) }}" 
                                     alt="{{ $slider->title }}"
                                     class="w-32 h-20 object-cover rounded-lg">
                            @else
                                <div class="w-32 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif
                        </div>

                        <!-- Details -->
                        <div class="flex-1">
                            <h3 class="font-medium text-gray-900">{{ $slider->getTranslation('title', 'en') }}</h3>
                            @if($slider->subtitle)
                                <p class="text-sm text-gray-600 mt-1">{{ Str::limit($slider->getTranslation('subtitle', 'en'), 50) }}</p>
                            @endif
                            <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                                <span>Position: {{ ucfirst($slider->position) }}</span>
                                @if($slider->starts_at || $slider->ends_at)
                                    <span>Schedule: {{ $slider->starts_at?->format('M d') }} - {{ $slider->ends_at?->format('M d') }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="flex-shrink-0">
                            <button wire:click="toggleStatus({{ $slider->id }})" 
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $slider->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $slider->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </div>

                        <!-- Actions -->
                        <div class="flex-shrink-0 flex items-center gap-2">
                            <button wire:click="editSlider({{ $slider->id }})" 
                                    class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button wire:click="deleteSlider({{ $slider->id }})"
                                    wire:confirm="Are you sure you want to delete this slider?"
                                    class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-500 mt-2">No sliders found</p>
                    </div>
                @endforelse
            </div>
        </div>

        @if($sliders->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $sliders->links() }}
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
            
            <div class="relative bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editMode ? 'Edit' : 'Create' }} Slider
                </h3>
                
                <form wire:submit="saveSlider">
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

                        <!-- Button Text -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Button Text (English)</label>
                            <input type="text" 
                                   wire:model="buttonText" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('buttonText') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Button Text (Arabic)</label>
                            <input type="text" 
                                   wire:model="buttonTextAr" 
                                   dir="rtl"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('buttonTextAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Button Link -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Button Link</label>
                            <input type="text" 
                                   wire:model="buttonLink" 
                                   placeholder="/products, /categories/electronics, etc."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('buttonLink') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

                        <!-- Position -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                            <select wire:model="position" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="home">Homepage</option>
                                <option value="category">Category Pages</option>
                                <option value="product">Product Pages</option>
                            </select>
                            @error('position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                            {{ $editMode ? 'Update' : 'Create' }} Slider
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

<!-- Include Sortable.js -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>