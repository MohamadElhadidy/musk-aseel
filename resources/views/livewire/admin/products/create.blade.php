<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Tag;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;

new class extends Component
{
    use WithFileUploads;

    // Step management
    public $currentStep = 1;
    public $totalSteps = 5;
    public $completedSteps = [];

    // Basic Information
    #[Validate('required|min:3|max:255')]
    public $productName = '';
    
    #[Validate('required|unique:products,slug')]
    public $slug = '';
    
    #[Validate('required|unique:products,sku')]
    public $sku = '';
    
    #[Validate('required')]
    public $brandId = '';
    
    #[Validate('required|array|min:1')]
    public $categoryIds = [];
    
    public $tagIds = [];
    
    #[Validate('required')]
    public $description = '';
    
    public $shortDescription = '';

    // Pricing & Inventory
    #[Validate('required|numeric|min:0')]
    public $price = '';
    
    #[Validate('nullable|numeric|gt:price')]
    public $comparePrice = '';
    
    #[Validate('nullable|numeric|min:0')]
    public $cost = '';
    
    #[Validate('required|integer|min:0')]
    public $quantity = 0;
    
    public $trackQuantity = true;
    public $isActive = true;
    public $isFeatured = false;

    // Physical Properties
    #[Validate('nullable|numeric|min:0')]
    public $weight = '';
    
    public $length = '';
    public $width = '';
    public $height = '';

    // Images
    #[Validate(['images.*' => 'image|max:2048'])]
    public $images = [];
    public $uploadedImages = [];
    public $primaryImageIndex = 0;

    // Attributes & Variants
    public $selectedAttributes = [];
    public $attributeValues = [];
    public $variants = [];
    public $autoGenerateVariants = true;

    // SEO
    public $metaTitle = '';
    public $metaDescription = '';

    // Component data
    public $brands = [];
    public $categories = [];
    public $tags = [];
    public $attributes = [];

    // UI State
    public $showPreview = false;
    public $saving = false;
    public $saved = false;

    public function mount()
    {
        $this->brands = Brand::active()->get();
        $this->categories = Category::active()->with('translations')->get();
        $this->tags = Tag::all();
        $this->attributes = Attribute::where('is_variant', true)->with('values.translations')->get();
        
        $this->generateSku();
    }

    public function generateSku()
    {
        $prefix = $this->brandId ? strtoupper(substr(Brand::find($this->brandId)?->slug ?? 'PRD', 0, 3)) : 'PRD';
        $this->sku = $prefix . '-' . strtoupper(Str::random(6));
    }

    public function updatedProductName($value)
    {
        $this->slug = Str::slug($value);
        $this->metaTitle = $value;
    }

    public function uploadImages()
    {
        $this->validate(['images.*' => 'image|max:2048']);

        foreach ($this->images as $index => $image) {
            $path = $image->store('products', 'public');
            $this->uploadedImages[] = [
                'id' => 'temp_' . uniqid(),
                'path' => $path,
                'url' => asset('storage/' . $path),
                'is_primary' => count($this->uploadedImages) === 0,
                'sort_order' => count($this->uploadedImages),
            ];
        }

        $this->images = [];
    }

    public function removeImage($index)
    {
        unset($this->uploadedImages[$index]);
        $this->uploadedImages = array_values($this->uploadedImages);
        
        if (count($this->uploadedImages) > 0 && $this->primaryImageIndex >= count($this->uploadedImages)) {
            $this->primaryImageIndex = 0;
            $this->uploadedImages[0]['is_primary'] = true;
        }
    }

    public function setPrimaryImage($index)
    {
        foreach ($this->uploadedImages as $i => &$image) {
            $image['is_primary'] = $i === $index;
        }
        $this->primaryImageIndex = $index;
    }

    public function addAttributeValue($attributeId)
    {
        if (!isset($this->attributeValues[$attributeId])) {
            $this->attributeValues[$attributeId] = [];
        }
    }

    public function removeAttributeValue($attributeId)
    {
        unset($this->selectedAttributes[$attributeId]);
        unset($this->attributeValues[$attributeId]);
        $this->selectedAttributes = array_filter($this->selectedAttributes);
        
        if ($this->autoGenerateVariants) {
            $this->generateVariants();
        }
    }

    public function generateVariants()
    {
        $this->variants = [];
        
        if (empty($this->selectedAttributes)) {
            return;
        }

        $attributes = [];
        foreach ($this->selectedAttributes as $attrId => $enabled) {
            if ($enabled && !empty($this->attributeValues[$attrId])) {
                $attribute = Attribute::find($attrId);
                $attributes[] = [
                    'id' => $attrId,
                    'code' => $attribute->code,
                    'values' => $this->attributeValues[$attrId]
                ];
            }
        }

        if (empty($attributes)) {
            return;
        }

        $combinations = $this->generateCombinations($attributes);
        
        foreach ($combinations as $index => $combination) {
            $variantSku = $this->sku;
            $attributes = [];
            
            foreach ($combination as $attr) {
                $variantSku .= '-' . strtoupper(substr($attr['value'], 0, 2));
                $attributes[$attr['code']] = $attr['value'];
            }
            
            $this->variants[] = [
                'id' => 'new_' . $index,
                'sku' => $variantSku,
                'price' => $this->price,
                'quantity' => 10,
                'attributes' => $attributes,
                'attribute_ids' => collect($combination)->pluck('value_id', 'attribute_id')->toArray(),
                'is_active' => true,
            ];
        }
    }

    private function generateCombinations($attributes, $i = 0, $current = [])
    {
        if ($i == count($attributes)) {
            return [$current];
        }
        
        $results = [];
        $attribute = $attributes[$i];
        
        foreach ($attribute['values'] as $valueId) {
            $value = AttributeValue::find($valueId);
            if ($value) {
                $newCurrent = $current;
                $newCurrent[] = [
                    'attribute_id' => $attribute['id'],
                    'code' => $attribute['code'],
                    'value_id' => $valueId,
                    'value' => $value->value,
                ];
                $results = array_merge($results, $this->generateCombinations($attributes, $i + 1, $newCurrent));
            }
        }
        
        return $results;
    }

    public function updateVariant($index, $field, $value)
    {
        $this->variants[$index][$field] = $value;
    }

    public function removeVariant($index)
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function nextStep()
    {
        $this->validateStep($this->currentStep);
        
        $this->completedSteps[] = $this->currentStep;
        $this->completedSteps = array_unique($this->completedSteps);
        
        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep($step)
    {
        if ($step <= max($this->completedSteps) + 1 || $step <= $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    private function validateStep($step)
    {
        switch ($step) {
            case 1: // Basic Information
                $this->validate([
                    'productName' => 'required|min:3|max:255',
                    'slug' => 'required|unique:products,slug',
                    'sku' => 'required|unique:products,sku',
                    'brandId' => 'required',
                    'categoryIds' => 'required|array|min:1',
                    'description' => 'required',
                ]);
                break;
                
            case 2: // Pricing & Inventory
                $this->validate([
                    'price' => 'required|numeric|min:0',
                    'comparePrice' => 'nullable|numeric|gt:price',
                    'cost' => 'nullable|numeric|min:0',
                    'quantity' => 'required_if:trackQuantity,true|integer|min:0',
                ]);
                break;
                
            case 3: // Images
                $this->validate([
                    'uploadedImages' => 'required|array|min:1',
                ]);
                break;
        }
    }

    public function togglePreview()
    {
        $this->showPreview = !$this->showPreview;
    }

    public function save()
    {
        $this->saving = true;
        
        try {
            // Validate all steps
            for ($i = 1; $i <= $this->totalSteps; $i++) {
                $this->validateStep($i);
            }

            $product = Product::create([
                'brand_id' => $this->brandId,
                'sku' => $this->sku,
                'slug' => $this->slug,
                'price' => $this->price,
                'compare_price' => $this->comparePrice ?: null,
                'cost' => $this->cost ?: null,
                'quantity' => $this->trackQuantity ? $this->quantity : 0,
                'track_quantity' => $this->trackQuantity,
                'is_active' => $this->isActive,
                'is_featured' => $this->isFeatured,
                'weight' => $this->weight ?: null,
                'dimensions' => $this->length || $this->width || $this->height ? [
                    'length' => $this->length,
                    'width' => $this->width,
                    'height' => $this->height,
                ] : null,
            ]);

            // Add translations
            $product->translations()->create([
                'locale' => app()->getLocale(),
                'name' => $this->productName,
                'description' => $this->description,
                'short_description' => $this->shortDescription,
                'meta_title' => $this->metaTitle ?: $this->productName,
                'meta_description' => $this->metaDescription,
            ]);

            // Attach categories and tags
            $product->categories()->attach($this->categoryIds);
            if (!empty($this->tagIds)) {
                $product->tags()->attach($this->tagIds);
            }

            // Save images
            foreach ($this->uploadedImages as $index => $image) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $image['path'],
                    'is_primary' => $image['is_primary'],
                    'sort_order' => $index,
                ]);
            }

            // Create variants
            if (!empty($this->variants)) {
                foreach ($this->variants as $variant) {
                    $productVariant = $product->variants()->create([
                        'sku' => $variant['sku'],
                        'price' => $variant['price'],
                        'quantity' => $variant['quantity'],
                        'attributes' => $variant['attributes'],
                        'is_active' => $variant['is_active'],
                    ]);

                    // If using attribute tables
                    if (!empty($variant['attribute_ids'])) {
                        foreach ($variant['attribute_ids'] as $attrId => $valueId) {
                            $productVariant->variantAttributes()->create([
                                'attribute_id' => $attrId,
                                'attribute_value_id' => $valueId,
                            ]);
                        }
                    }
                }
            }

            $this->saved = true;
            $this->saving = false;

            session()->flash('success', 'Product created successfully!');
            $this->redirect(route('admin.products'), navigate: true);
            
        } catch (\Exception $e) {
            $this->saving = false;
            session()->flash('error', 'Error creating product: ' . $e->getMessage());
        }
    }

    public function with(): array
    {
        return [
            'brands' => $this->brands,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'attributes' => $this->attributes,
        ];
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50">
    <!-- Beautiful Header -->
    <div class="bg-white border-b border-gray-100 sticky top-0 z-40 backdrop-blur-lg bg-white/90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <button wire:click="$dispatch('close')" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </button>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                        Create New Product
                    </h1>
                </div>
                
                <!-- Step Indicator -->
                <div class="hidden md:flex items-center space-x-2">
                    @for ($i = 1; $i <= $totalSteps; $i++)
                        <button 
                            wire:click="goToStep({{ $i }})"
                            class="flex items-center space-x-2 px-3 py-1.5 rounded-full text-sm font-medium transition-all
                                {{ $currentStep === $i ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg scale-105' : '' }}
                                {{ in_array($i, $completedSteps) ? 'bg-green-100 text-green-700 hover:bg-green-200' : '' }}
                                {{ !in_array($i, $completedSteps) && $currentStep !== $i ? 'bg-gray-100 text-gray-400' : '' }}"
                            @if($i > max(array_merge($completedSteps, [$currentStep]))) disabled @endif
                        >
                            <span class="flex items-center justify-center w-6 h-6 rounded-full
                                {{ $currentStep === $i ? 'bg-white/20' : '' }}
                                {{ in_array($i, $completedSteps) ? 'bg-green-600 text-white' : '' }}">
                                @if(in_array($i, $completedSteps))
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    {{ $i }}
                                @endif
                            </span>
                            <span class="hidden lg:inline">
                                @switch($i)
                                    @case(1) Basic Info @break
                                    @case(2) Pricing @break
                                    @case(3) Images @break
                                    @case(4) Variants @break
                                    @case(5) SEO & Review @break
                                @endswitch
                            </span>
                        </button>
                    @endfor
                </div>

                <!-- Preview Toggle -->
                <button 
                    wire:click="togglePreview"
                    class="flex items-center space-x-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span>{{ $showPreview ? 'Hide' : 'Show' }} Preview</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Section -->
            <div class="lg:col-span-2">
                <form wire:submit="save" class="space-y-8">
                    <!-- Step 1: Basic Information -->
                    @if($currentStep === 1)
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 animate-fade-in">
                            <h2 class="text-2xl font-bold mb-6 flex items-center">
                                <span class="flex items-center justify-center w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-full mr-3">1</span>
                                Basic Information
                            </h2>
                            
                            <div class="space-y-6">
                                <!-- Product Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Product Name <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model.live="productName"
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                        placeholder="Enter product name"
                                    >
                                    @error('productName') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                </div>

                                <!-- Slug and SKU -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            URL Slug <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">/products/</span>
                                            <input 
                                                type="text" 
                                                wire:model="slug"
                                                class="w-full pl-24 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                            >
                                        </div>
                                        @error('slug') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            SKU <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex space-x-2">
                                            <input 
                                                type="text" 
                                                wire:model="sku"
                                                class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                readonly
                                            >
                                            <button 
                                                type="button"
                                                wire:click="generateSku"
                                                class="px-4 py-3 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                            </button>
                                        </div>
                                        @error('sku') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Brand -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Brand <span class="text-red-500">*</span>
                                    </label>
                                    <select 
                                        wire:model="brandId"
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                    >
                                        <option value="">Select a brand</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('brandId') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                </div>

                                <!-- Categories -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Categories <span class="text-red-500">*</span>
                                    </label>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                        @foreach($categories as $category)
                                            <label class="relative flex items-center p-3 border-2 rounded-xl cursor-pointer transition-all
                                                {{ in_array($category->id, $categoryIds) ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-gray-300' }}">
                                                <input 
                                                    type="checkbox" 
                                                    wire:model="categoryIds"
                                                    value="{{ $category->id }}"
                                                    class="sr-only"
                                                >
                                                <span class="text-sm font-medium {{ in_array($category->id, $categoryIds) ? 'text-purple-700' : 'text-gray-700' }}">
                                                    {{ $category->name }}
                                                </span>
                                                @if(in_array($category->id, $categoryIds))
                                                    <svg class="absolute top-2 right-2 w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('categoryIds') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                </div>

                                <!-- Tags -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Tags
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($tags as $tag)
                                            <label class="inline-flex items-center px-4 py-2 rounded-full cursor-pointer transition-all
                                                {{ in_array($tag->id, $tagIds) ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                                <input 
                                                    type="checkbox" 
                                                    wire:model="tagIds"
                                                    value="{{ $tag->id }}"
                                                    class="sr-only"
                                                >
                                                <span class="text-sm">{{ $tag->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Description <span class="text-red-500">*</span>
                                    </label>
                                    <textarea 
                                        wire:model="description"
                                        rows="6"
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                        placeholder="Describe your product in detail..."
                                    ></textarea>
                                    @error('description') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                </div>

                                <!-- Short Description -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Short Description
                                    </label>
                                    <textarea 
                                        wire:model="shortDescription"
                                        rows="3"
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                        placeholder="Brief product summary..."
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Step 2: Pricing & Inventory -->
                    @if($currentStep === 2)
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 animate-fade-in">
                            <h2 class="text-2xl font-bold mb-6 flex items-center">
                                <span class="flex items-center justify-center w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-full mr-3">2</span>
                                Pricing & Inventory
                            </h2>
                            
                            <div class="space-y-6">
                                <!-- Pricing Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Regular Price <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                wire:model="price"
                                                class="w-full pl-8 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0.00"
                                            >
                                        </div>
                                        @error('price') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Compare Price
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                wire:model="comparePrice"
                                                class="w-full pl-8 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0.00"
                                            >
                                        </div>
                                        @error('comparePrice') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Cost per Item
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                wire:model="cost"
                                                class="w-full pl-8 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0.00"
                                            >
                                        </div>
                                        @error('cost') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Inventory Management -->
                                <div class="bg-gray-50 rounded-xl p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold">Inventory Management</h3>
                                        <label class="flex items-center cursor-pointer">
                                            <input 
                                                type="checkbox" 
                                                wire:model="trackQuantity"
                                                class="sr-only"
                                            >
                                            <div class="relative">
                                                <div class="block w-12 h-6 rounded-full {{ $trackQuantity ? 'bg-purple-600' : 'bg-gray-300' }}"></div>
                                                <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform {{ $trackQuantity ? 'transform translate-x-6' : '' }}"></div>
                                            </div>
                                            <span class="ml-3 text-sm font-medium">Track Quantity</span>
                                        </label>
                                    </div>
                                    
                                    @if($trackQuantity)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Available Quantity <span class="text-red-500">*</span>
                                            </label>
                                            <input 
                                                type="number" 
                                                wire:model="quantity"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0"
                                            >
                                            @error('quantity') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                        </div>
                                    @endif
                                </div>

                                <!-- Physical Properties -->
                                <div>
                                    <h3 class="text-lg font-semibold mb-4">Shipping Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Weight (kg)
                                            </label>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                wire:model="weight"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0.0"
                                            >
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Length (cm)
                                            </label>
                                            <input 
                                                type="number" 
                                                step="0.1"
                                                wire:model="length"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0.0"
                                            >
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Width (cm)
                                            </label>
                                            <input 
                                                type="number" 
                                                step="0.1"
                                                wire:model="width"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0.0"
                                            >
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Height (cm)
                                            </label>
                                            <input 
                                                type="number" 
                                                step="0.1"
                                                wire:model="height"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="0.0"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Options -->
                                <div class="flex items-center space-x-6">
                                    <label class="flex items-center cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model="isActive"
                                            class="w-5 h-5 text-purple-600 border-2 border-gray-300 rounded focus:ring-purple-500"
                                        >
                                        <span class="ml-2 text-sm font-medium">Active Product</span>
                                    </label>
                                    
                                    <label class="flex items-center cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model="isFeatured"
                                            class="w-5 h-5 text-purple-600 border-2 border-gray-300 rounded focus:ring-purple-500"
                                        >
                                        <span class="ml-2 text-sm font-medium">Featured Product</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Step 3: Product Images -->
                    @if($currentStep === 3)
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 animate-fade-in">
                            <h2 class="text-2xl font-bold mb-6 flex items-center">
                                <span class="flex items-center justify-center w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-full mr-3">3</span>
                                Product Images
                            </h2>
                            
                            <div class="space-y-6">
                                <!-- Upload Area -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Product Images <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="file" 
                                            wire:model="images"
                                            multiple
                                            accept="image/*"
                                            class="sr-only"
                                            id="image-upload"
                                        >
                                        <label 
                                            for="image-upload"
                                            class="flex flex-col items-center justify-center w-full h-48 border-3 border-dashed border-gray-300 rounded-2xl cursor-pointer hover:border-purple-500 transition-all group"
                                        >
                                            <svg class="w-12 h-12 text-gray-400 group-hover:text-purple-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            <p class="mt-2 text-sm text-gray-600">
                                                <span class="font-medium text-purple-600">Click to upload</span> or drag and drop
                                            </p>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB each</p>
                                        </label>
                                    </div>
                                    
                                    @if($images)
                                        <div class="mt-4">
                                            <button 
                                                type="button"
                                                wire:click="uploadImages"
                                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
                                            >
                                                Upload {{ count($images) }} Image{{ count($images) > 1 ? 's' : '' }}
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <!-- Image Gallery -->
                                @if(count($uploadedImages) > 0)
                                    <div>
                                        <h3 class="text-lg font-semibold mb-4">Uploaded Images</h3>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            @foreach($uploadedImages as $index => $image)
                                                <div class="relative group">
                                                    <img 
                                                        src="{{ $image['url'] }}" 
                                                        alt="Product image"
                                                        class="w-full h-40 object-cover rounded-xl {{ $image['is_primary'] ? 'ring-4 ring-purple-500' : '' }}"
                                                    >
                                                    
                                                    <!-- Primary Badge -->
                                                    @if($image['is_primary'])
                                                        <span class="absolute top-2 left-2 px-2 py-1 bg-purple-600 text-white text-xs rounded-full">
                                                            Primary
                                                        </span>
                                                    @endif
                                                    
                                                    <!-- Actions -->
                                                    <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-xl flex items-center justify-center space-x-2">
                                                        @if(!$image['is_primary'])
                                                            <button 
                                                                type="button"
                                                                wire:click="setPrimaryImage({{ $index }})"
                                                                class="p-2 bg-white rounded-lg hover:bg-gray-100 transition-colors"
                                                                title="Set as primary"
                                                            >
                                                                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                                </svg>
                                                            </button>
                                                        @endif
                                                        
                                                        <button 
                                                            type="button"
                                                            wire:click="removeImage({{ $index }})"
                                                            class="p-2 bg-white rounded-lg hover:bg-gray-100 transition-colors"
                                                            title="Remove image"
                                                        >
                                                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                
                                @error('uploadedImages') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif

                    <!-- Step 4: Attributes & Variants -->
                    @if($currentStep === 4)
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 animate-fade-in">
                            <h2 class="text-2xl font-bold mb-6 flex items-center">
                                <span class="flex items-center justify-center w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-full mr-3">4</span>
                                Product Variants
                            </h2>
                            
                            <div class="space-y-6">
                                <!-- Variant Attributes Selection -->
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold">Select Variant Attributes</h3>
                                        <label class="flex items-center cursor-pointer">
                                            <input 
                                                type="checkbox" 
                                                wire:model="autoGenerateVariants"
                                                class="sr-only"
                                            >
                                            <div class="relative">
                                                <div class="block w-12 h-6 rounded-full {{ $autoGenerateVariants ? 'bg-purple-600' : 'bg-gray-300' }}"></div>
                                                <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform {{ $autoGenerateVariants ? 'transform translate-x-6' : '' }}"></div>
                                            </div>
                                            <span class="ml-3 text-sm font-medium">Auto-generate variants</span>
                                        </label>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        @foreach($attributes as $attribute)
                                            <div class="border-2 border-gray-200 rounded-xl p-4 {{ isset($selectedAttributes[$attribute->id]) && $selectedAttributes[$attribute->id] ? 'border-purple-500 bg-purple-50' : '' }}">
                                                <label class="flex items-center justify-between cursor-pointer">
                                                    <div class="flex items-center">
                                                        <input 
                                                            type="checkbox" 
                                                            wire:model="selectedAttributes.{{ $attribute->id }}"
                                                            wire:change="addAttributeValue({{ $attribute->id }})"
                                                            class="w-5 h-5 text-purple-600 border-2 border-gray-300 rounded focus:ring-purple-500"
                                                        >
                                                        <span class="ml-3 font-medium">{{ $attribute->name }}</span>
                                                    </div>
                                                    
                                                    @if(isset($selectedAttributes[$attribute->id]) && $selectedAttributes[$attribute->id])
                                                        <button 
                                                            type="button"
                                                            wire:click="removeAttributeValue({{ $attribute->id }})"
                                                            class="text-red-600 hover:text-red-700"
                                                        >
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </label>
                                                
                                                @if(isset($selectedAttributes[$attribute->id]) && $selectedAttributes[$attribute->id])
                                                    <div class="mt-4 flex flex-wrap gap-2">
                                                        @foreach($attribute->values as $value)
                                                            <label class="inline-flex items-center px-3 py-1.5 rounded-full cursor-pointer transition-all
                                                                {{ in_array($value->id, $attributeValues[$attribute->id] ?? []) ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                                                <input 
                                                                    type="checkbox" 
                                                                    wire:model="attributeValues.{{ $attribute->id }}"
                                                                    wire:change="{{ $autoGenerateVariants ? 'generateVariants' : '' }}"
                                                                    value="{{ $value->id }}"
                                                                    class="sr-only"
                                                                >
                                                                <span class="text-sm">{{ $value->label }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Generated Variants -->
                                @if(count($variants) > 0)
                                    <div>
                                        <h3 class="text-lg font-semibold mb-4">Product Variants ({{ count($variants) }})</h3>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        @foreach($selectedAttributes as $attrId => $enabled)
                                                            @if($enabled)
                                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                    {{ $attributes->find($attrId)->name }}
                                                                </th>
                                                            @endif
                                                        @endforeach
                                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active</th>
                                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    @foreach($variants as $index => $variant)
                                                        <tr>
                                                            @foreach($variant['attributes'] as $code => $value)
                                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $value }}</td>
                                                            @endforeach
                                                            <td class="px-4 py-3">
                                                                <input 
                                                                    type="text" 
                                                                    wire:model="variants.{{ $index }}.sku"
                                                                    class="w-32 px-2 py-1 border border-gray-300 rounded text-sm"
                                                                >
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <input 
                                                                    type="number" 
                                                                    step="0.01"
                                                                    wire:model="variants.{{ $index }}.price"
                                                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-sm"
                                                                >
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <input 
                                                                    type="number" 
                                                                    wire:model="variants.{{ $index }}.quantity"
                                                                    class="w-20 px-2 py-1 border border-gray-300 rounded text-sm"
                                                                >
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <input 
                                                                    type="checkbox" 
                                                                    wire:model="variants.{{ $index }}.is_active"
                                                                    class="w-4 h-4 text-purple-600 border-gray-300 rounded"
                                                                >
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <button 
                                                                    type="button"
                                                                    wire:click="removeVariant({{ $index }})"
                                                                    class="text-red-600 hover:text-red-700"
                                                                >
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Step 5: SEO & Review -->
                    @if($currentStep === 5)
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 animate-fade-in">
                            <h2 class="text-2xl font-bold mb-6 flex items-center">
                                <span class="flex items-center justify-center w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-full mr-3">5</span>
                                SEO & Final Review
                            </h2>
                            
                            <div class="space-y-6">
                                <!-- SEO Settings -->
                                <div>
                                    <h3 class="text-lg font-semibold mb-4">Search Engine Optimization</h3>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Meta Title
                                            </label>
                                            <input 
                                                type="text" 
                                                wire:model="metaTitle"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="Page title for search engines"
                                            >
                                            <p class="mt-1 text-sm text-gray-500">{{ strlen($metaTitle) }}/60 characters</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Meta Description
                                            </label>
                                            <textarea 
                                                wire:model="metaDescription"
                                                rows="3"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all"
                                                placeholder="Brief description for search engine results"
                                            ></textarea>
                                            <p class="mt-1 text-sm text-gray-500">{{ strlen($metaDescription) }}/160 characters</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Final Review -->
                                <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl p-6">
                                    <h3 class="text-lg font-semibold mb-4">Product Summary</h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2">Basic Information</h4>
                                            <dl class="space-y-1 text-sm">
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">Name:</dt>
                                                    <dd class="font-medium">{{ $productName ?: 'Not set' }}</dd>
                                                </div>
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">SKU:</dt>
                                                    <dd class="font-medium">{{ $sku }}</dd>
                                                </div>
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">Brand:</dt>
                                                    <dd class="font-medium">{{ $brandId ? $brands->find($brandId)?->name : 'Not set' }}</dd>
                                                </div>
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">Categories:</dt>
                                                    <dd class="font-medium">{{ count($categoryIds) }} selected</dd>
                                                </div>
                                            </dl>
                                        </div>
                                        
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2">Pricing & Inventory</h4>
                                            <dl class="space-y-1 text-sm">
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">Price:</dt>
                                                    <dd class="font-medium">${{ number_format($price ?: 0, 2) }}</dd>
                                                </div>
                                                @if($comparePrice)
                                                    <div class="flex justify-between">
                                                        <dt class="text-gray-500">Compare at:</dt>
                                                        <dd class="font-medium">${{ number_format($comparePrice, 2) }}</dd>
                                                    </div>
                                                @endif
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">Inventory:</dt>
                                                    <dd class="font-medium">
                                                        {{ $trackQuantity ? $quantity . ' units' : 'Not tracked' }}
                                                    </dd>
                                                </div>
                                                <div class="flex justify-between">
                                                    <dt class="text-gray-500">Status:</dt>
                                                    <dd>
                                                        @if($isActive)
                                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>
                                                        @else
                                                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">Inactive</span>
                                                        @endif
                                                    </dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <h4 class="font-medium text-gray-700 mb-2">Media & Variants</h4>
                                        <dl class="space-y-1 text-sm">
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500">Images:</dt>
                                                <dd class="font-medium">{{ count($uploadedImages) }} uploaded</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500">Variants:</dt>
                                                <dd class="font-medium">{{ count($variants) }} variants</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Navigation Buttons -->
                    <div class="flex items-center justify-between pt-6">
                        <button 
                            type="button"
                            wire:click="previousStep"
                            @if($currentStep === 1) disabled @endif
                            class="flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Previous
                        </button>
                        
                        <div class="flex items-center space-x-4">
                            @if($currentStep < $totalSteps)
                                <button 
                                    type="button"
                                    wire:click="nextStep"
                                    class="flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl hover:from-purple-700 hover:to-blue-700 transition-all shadow-lg"
                                >
                                    Next
                                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            @else
                                <button 
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    class="flex items-center px-8 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all shadow-lg disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="save">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Create Product
                                    </span>
                                    <span wire:loading wire:target="save">
                                        <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Creating...
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <!-- Side Preview Panel -->
            <div class="lg:col-span-1">
                <div class="sticky top-24">
                    <!-- Live Preview Card -->
                    <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
                        <h3 class="text-lg font-bold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Live Preview
                        </h3>
                        
                        <!-- Product Card Preview -->
                        <div class="border-2 border-gray-100 rounded-xl p-4">
                            <!-- Image Preview -->
                            <div class="relative mb-4">
                                @if(count($uploadedImages) > 0)
                                    <img 
                                        src="{{ $uploadedImages[$primaryImageIndex]['url'] }}" 
                                        alt="Product preview"
                                        class="w-full h-48 object-cover rounded-lg"
                                    >
                                @else
                                    <div class="w-full h-48 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif
                                
                                @if($isFeatured)
                                    <span class="absolute top-2 left-2 px-2 py-1 bg-yellow-400 text-yellow-900 text-xs font-medium rounded-full">
                                        Featured
                                    </span>
                                @endif
                                
                                @if($comparePrice && $price && $comparePrice > $price)
                                    <span class="absolute top-2 right-2 px-2 py-1 bg-red-500 text-white text-xs font-medium rounded-full">
                                        {{ round((($comparePrice - $price) / $comparePrice) * 100) }}% OFF
                                    </span>
                                @endif
                            </div>
                            
                            <!-- Product Info -->
                            <div class="space-y-2">
                                <h4 class="font-semibold text-lg line-clamp-2">
                                    {{ $productName ?: 'Product Name' }}
                                </h4>
                                
                                @if($brandId)
                                    <p class="text-sm text-gray-500">
                                        {{ $brands->find($brandId)?->name }}
                                    </p>
                                @endif
                                
                                <div class="flex items-center space-x-2">
                                    @if($comparePrice && $price && $comparePrice > $price)
                                        <span class="text-lg font-bold text-gray-900">${{ number_format($price, 2) }}</span>
                                        <span class="text-sm text-gray-500 line-through">${{ number_format($comparePrice, 2) }}</span>
                                    @else
                                        <span class="text-lg font-bold text-gray-900">${{ number_format($price ?: 0, 2) }}</span>
                                    @endif
                                </div>
                                
                                @if($trackQuantity)
                                    <p class="text-sm {{ $quantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $quantity > 0 ? $quantity . ' in stock' : 'Out of stock' }}
                                    </p>
                                @endif
                                
                                @if(count($variants) > 0)
                                    <p class="text-sm text-gray-500">
                                        {{ count($variants) }} variants available
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="mt-6 bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
                        <h3 class="text-lg font-bold mb-4">Quick Stats</h3>
                        
                        <div class="space-y-4">
                            <!-- Progress -->
                            <div>
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="text-gray-600">Completion</span>
                                    <span class="font-medium">{{ round((count($completedSteps) / $totalSteps) * 100) }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-purple-600 to-blue-600 h-2 rounded-full transition-all" style="width: {{ (count($completedSteps) / $totalSteps) * 100 }}%"></div>
                                </div>
                            </div>
                            
                            <!-- Checklist -->
                            <div class="space-y-2">
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 rounded" {{ $productName ? 'checked' : '' }} disabled>
                                    <span class="ml-2 {{ $productName ? 'text-gray-700' : 'text-gray-400' }}">Product name added</span>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 rounded" {{ $price ? 'checked' : '' }} disabled>
                                    <span class="ml-2 {{ $price ? 'text-gray-700' : 'text-gray-400' }}">Price configured</span>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 rounded" {{ count($uploadedImages) > 0 ? 'checked' : '' }} disabled>
                                    <span class="ml-2 {{ count($uploadedImages) > 0 ? 'text-gray-700' : 'text-gray-400' }}">Images uploaded</span>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 rounded" {{ count($categoryIds) > 0 ? 'checked' : '' }} disabled>
                                    <span class="ml-2 {{ count($categoryIds) > 0 ? 'text-gray-700' : 'text-gray-400' }}">Categories selected</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    
<style>
    @keyframes fade-in {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-fade-in {
        animation: fade-in 0.5s ease-out;
    }
</style>
</div>
