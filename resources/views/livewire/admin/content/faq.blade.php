public bool $categoryIsActive = true;

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

    public function createFaq()
    {
        $this->reset(['faqId', 'question', 'questionAr', 'answer', 'answerAr',
                     'categoryId', 'sortOrder', 'isActive', 'isFeatured']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function editFaq($id)
    {
        $faq = Faq::findOrFail($id);
        
        $this->faqId = $faq->id;
        $this->question = $faq->getTranslation('question', 'en') ?? '';
        $this->questionAr = $faq->getTranslation('question', 'ar') ?? '';
        $this->answer = $faq->getTranslation('answer', 'en') ?? '';
        $this->answerAr = $faq->getTranslation('answer', 'ar') ?? '';
        $this->categoryId = $faq->category_id;
        $this->sortOrder = $faq->sort_order;
        $this->isActive = $faq->is_active;
        $this->isFeatured = $faq->is_featured;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function saveFaq()
    {
        $this->validate([
            'question' => 'required|string|max:500',
            'questionAr' => 'required|string|max:500',
            'answer' => 'required|string',
            'answerAr' => 'required|string',
            'categoryId' => 'nullable|exists:faq_categories,id',
            'sortOrder' => 'required|integer|min:0',
        ]);

        $data = [
            'question' => ['en' => $this->question, 'ar' => $this->questionAr],
            'answer' => ['en' => $this->answer, 'ar' => $this->answerAr],
            'category_id' => $this->categoryId,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
        ];

        if ($this->editMode) {
            Faq::find($this->faqId)->update($data);
            $message = 'FAQ updated successfully';
        } else {
            Faq::create($data);
            $message = 'FAQ created successfully';
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteFaq($id)
    {
        Faq::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'FAQ deleted successfully');
    }

    public function toggleStatus($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->update(['is_active' => !$faq->is_active]);
        
        $status = $faq->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', type: 'success', message: "FAQ {$status} successfully");
    }

    public function toggleFeatured($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->update(['is_featured' => !$faq->is_featured]);
        
        $status = $faq->is_featured ? 'featured' : 'unfeatured';
        $this->dispatch('toast', type: 'success', message: "FAQ {$status} successfully");
    }

    public function createCategory()
    {
        $this->reset(['editingCategoryId', 'categoryName', 'categoryNameAr',
                     'categoryDescription', 'categoryDescriptionAr', 
                     'categorySortOrder', 'categoryIsActive']);
        $this->showCategoryModal = true;
    }

    public function editCategory($id)
    {
        $category = FaqCategory::findOrFail($id);
        
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->getTranslation('name', 'en') ?? '';
        $this->categoryNameAr = $category->getTranslation('name', 'ar') ?? '';
        $this->categoryDescription = $category->getTranslation('description', 'en') ?? '';
        $this->categoryDescriptionAr = $category->getTranslation('description', 'ar') ?? '';
        $this->categorySortOrder = $category->sort_order;
        $this->categoryIsActive = $category->is_active;
        
        $this->showCategoryModal = true;
    }

    public function saveCategory()
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
            'categoryNameAr' => 'required|string|max:255',
            'categoryDescription' => 'nullable|string|max:500',
            'categoryDescriptionAr' => 'nullable|string|max:500',
            'categorySortOrder' => 'required|integer|min:0',
        ]);

        $data = [
            'name' => ['en' => $this->categoryName, 'ar' => $this->categoryNameAr],
            'description' => ['en' => $this->categoryDescription, 'ar' => $this->categoryDescriptionAr],
            'sort_order' => $this->categorySortOrder,
            'is_active' => $this->categoryIsActive,
        ];

        if ($this->editingCategoryId) {
            FaqCategory::find($this->editingCategoryId)->update($data);
            $message = 'Category updated successfully';
        } else {
            FaqCategory::create($data);
            $message = 'Category created successfully';
        }

        $this->showCategoryModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteCategory($id)
    {
        $category = FaqCategory::findOrFail($id);
        
        // Move FAQs to uncategorized
        Faq::where('category_id', $id)->update(['category_id' => null]);
        
        $category->delete();
        
        $this->dispatch('toast', type: 'success', message: 'Category deleted successfully');
    }

    public function with()
    {
        $query = Faq::with('category');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('question->en', 'like', "%{$this->search}%")
                  ->orWhere('question->ar', 'like', "%{$this->search}%")
                  ->orWhere('answer->en', 'like', "%{$this->search}%")
                  ->orWhere('answer->ar', 'like', "%{$this->search}%");
            });
        }

        if ($this->categoryFilter !== '') {
            if ($this->categoryFilter === 'uncategorized') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $this->categoryFilter);
            }
        }

        if ($this->status !== '') {
            $query->where('is_active', $this->status);
        }

        return [
            'faqs' => $query->orderBy('sort_order')->paginate(20),
            'categories' => FaqCategory::orderBy('sort_order')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">FAQs</h1>
            <p class="text-sm text-gray-600 mt-1">Manage frequently asked questions</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="createCategory" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                Manage Categories
            </button>
            <button wire:click="createFaq" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add FAQ
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Search FAQs..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select wire:model.live="categoryFilter" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Categories</option>
                    <option value="uncategorized">Uncategorized</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->getTranslation('name', 'en') }}</option>
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

    <!-- FAQs List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6">
            <div class="space-y-4">
                @forelse($faqs as $faq)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    @if($faq->is_featured)
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                            Featured
                                        </span>
                                    @endif
                                    @if($faq->category)
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            {{ $faq->category->getTranslation('name', 'en') }}
                                        </span>
                                    @endif
                                </div>
                                
                                <h3 class="font-medium text-gray-900">{{ $faq->getTranslation('question', 'en') }}</h3>
                                <p class="text-sm text-gray-600 mt-1">{{ Str::limit($faq->getTranslation('answer', 'en'), 150) }}</p>
                                
                                <div class="mt-3 flex items-center gap-4 text-sm text-gray-500">
                                    <span>Sort Order: {{ $faq->sort_order }}</span>
                                    <span>Updated: {{ $faq->updated_at->format('M d, Y') }}</span>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3 ml-4">
                                <button wire:click="toggleStatus({{ $faq->id }})" 
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $faq->is_active ? 'bg-blue-600' : 'bg-gray-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $faq->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                                
                                <button wire:click="toggleFeatured({{ $faq->id }})" 
                                        class="p-2 {{ $faq->is_featured ? 'text-yellow-600 bg-yellow-50' : 'text-gray-600 hover:bg-gray-50' }} rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                    </svg>
                                </button>
                                
                                <button wire:click="editFaq({{ $faq->id }})" 
                                        class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                
                                <button wire:click="deleteFaq({{ $faq->id }})"
                                        wire:confirm="Are you sure you want to delete this FAQ?"
                                        class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-gray-500 mt-2">No FAQs found</p>
                    </div>
                @endforelse
            </div>
        </div>

        @if($faqs->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $faqs->links() }}
            </div>
        @endif
    </div>

    <!-- FAQ Modal -->
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
                    {{ $editMode ? 'Edit' : 'Create' }} FAQ
                </h3>
                
                <form wire:submit="saveFaq">
                    <div class="space-y-4">
                        <!-- Category -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select wire:model="categoryId" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Uncategorized</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->getTranslation('name', 'en') }}</option>
                                @endforeach
                            </select>
                            @error('categoryId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Question -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Question (English)</label>
                            <textarea wire:model="question" 
                                      rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('question') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Question (Arabic)</label>
                            <textarea wire:model="questionAr" 
                                      rows="2"
                                      dir="rtl"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('questionAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Answer -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Answer (English)</label>
                            <textarea wire:model="answer" 
                                      rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('answer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Answer (Arabic)</label>
                            <textarea wire:model="answerAr" 
                                      rows="4"
                                      dir="rtl"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('answerAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

                        <!-- Options -->
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="isActive" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="isFeatured" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Featured</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $editMode ? 'Update' : 'Create' }} FAQ
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

    <!-- Category Modal -->
    <div x-show="$wire.showCategoryModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showCategoryModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editingCategoryId ? 'Edit' : 'Create' }} Category
                </h3>
                
                <form wire:submit="saveCategory">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name (English)</label>
                            <input type="text" 
                                   wire:model="categoryName" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('categoryName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name (Arabic)</label>
                            <input type="text" 
                                   wire:model="categoryNameAr" 
                                   dir="rtl"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('categoryNameAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                            <textarea wire:model="categoryDescription" 
                                      rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('categoryDescription') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (Arabic)</label>
                            <textarea wire:model="categoryDescriptionAr" 
                                      rows="2"
                                      dir="rtl"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('categoryDescriptionAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" 
                                   wire:model="categorySortOrder" 
                                   min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('categorySortOrder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="categoryIsActive" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $editingCategoryId ? 'Update' : 'Create' }} Category
                        </button>
                        <button type="button" 
                                wire:click="$set('showCategoryModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Categories List -->
                @if($categories->count() > 0)
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Existing Categories</h4>
                        <div class="space-y-2">
                            @foreach($categories as $category)
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">{{ $category->getTranslation('name', 'en') }}</span>
                                    <div class="flex items-center gap-2">
                                        <button wire:click="editCategory({{ $category->id }})" 
                                                class="text-sm text-blue-600 hover:text-blue-800">
                                            Edit
                                        </button>
                                        <button wire:click="deleteCategory({{ $category->id }})"
                                                wire:confirm="Are you sure? FAQs will be moved to uncategorized."
                                                class="text-sm text-red-600 hover:text-red-800">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>