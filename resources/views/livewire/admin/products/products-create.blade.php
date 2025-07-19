<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Tag;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithFileUploads;

    public ?Product $product = null;
    public ?int $productId = null;
    
    // Basic Info
    public string $sku = '';
    public string $slug = '';
    public float $price = 0;
    public ?float $compare_price = null;
    public ?float $cost = null;
    public int $quantity = 0;
    public bool $track_quantity = true;
    public bool $is_active = true;
    public bool $is_featured = false;
    public ?float $weight = null;
    public array $dimensions = ['length' => null, 'width' => null, 'height' => null];
    
    // Relations
    public ?int $brand_id = null;
    public array $category_ids = [];
    public array $tag_ids = [];
    
    // Translations
    public array $translations = [];
    
    // Images
    public array $images = [];
    public array $newImages = [];
    public array $imagesToDelete = [];
    
    // Variants
    public array $variants = [];
    public array $newVariants = [];
    public array $variantsToDelete = [];
    public array $variantAttributes = [];
    
    // Data
    public $categories;
    public $brands;
    public $tags;
    public $locales = ['en', 'ar'];

    #[Layout('components.layouts.admin')]
    public function mount($productId = null)
    {
        $this->productId = $productId;
        
        $this->categories = Category::active()->get();
        $this->brands = Brand::active()->get();
        $this->tags = Tag::all();
        
        if ($productId) {
            $this->loadProduct();
        } else {
            $this->initializeEmptyTranslations();
        }
    }

    public function loadProduct()
    {
        $this->product = Product::with(['translations', 'images', 'variants', 'categories', 'tags'])
            ->findOrFail($this->productId);
            
        // Basic info
        $this->sku = $this->product->sku;
        $this->slug = $this->product->slug;
        $this->price = $this->product->price;
        $this->compare_price = $this->product->compare_price;
        $this->cost = $this->product->cost;
        $this->quantity = $this->product->quantity;
        $this->track_quantity = $this->product->track_quantity;
        $this->is_active = $this->product->is_active;
        $this->is_featured = $this->product->is_featured;
        $this->weight = $this->product->weight;
        $this->dimensions = $this->product->dimensions ?? ['length' => null, 'width' => null, 'height' => null];
        $this->brand_id = $this->product->brand_id;
        
        // Relations
        $this->category_ids = $this->product->categories->pluck('id')->toArray();
        $this->tag_ids = $this->product->tags->pluck('id')->toArray();
        
        // Translations
        foreach ($this->locales as $locale) {
            $translation = $this->product->translations->where('locale', $locale)->first();
            $this->translations[$locale] = [
                'name' => $translation?->name ?? '',
                'description' => $translation?->description ?? '',
                'short_description' => $translation?->short_description ?? '',
                'meta_title' => $translation?->meta_title ?? '',
                'meta_description' => $translation?->meta_description ?? '',
            ];
        }
        
        // Images
        $this->images = $this->product->images->map(function ($image) {
            return [
                'id' => $image->id,
                'image' => $image->image,
                'url' => $image->image_url,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ];
        })->toArray();
        
        // Variants
        $this->variants = $this->product->variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'quantity' => $variant->quantity,
                'attributes' => $variant->attributes,
                'is_active' => $variant->is_active,
            ];
        })->toArray();
        
        // Extract variant attributes
        $this->extractVariantAttributes();
    }

    public function initializeEmptyTranslations()
    {
        foreach ($this->locales as $locale) {
            $this->translations[$locale] = [
                'name' => '',
                'description' => '',
                'short_description' => '',
                'meta_title' => '',
                'meta_description' => '',
            ];
        }
    }

    public function updatedTranslationsEnName($value)
    {
        if (!$this->productId && $value) {
            $this->slug = Str::slug($value);
        }
    }

    public function generateSku()
    {
        $prefix = strtoupper(substr($this->translations['en']['name'] ?? 'PRD', 0, 3));
        $this->sku = $prefix . '-' . strtoupper(Str::random(6));
    }

    public function addImage()
    {
        $this->validate([
            'newImages.*' => 'image|max:2048',
        ]);

        foreach ($this->newImages as $image) {
            $path = $image->store('products', 'public');
            $this->images[] = [
                'id' => null,
                'image' => $path,
                'url' => asset('storage/' . $path),
                'is_primary' => count($this->images) === 0,
                'sort_order' => count($this->images),
            ];
        }

        $this->newImages = [];
    }

    public function removeImage($index)
    {
        if (isset($this->images[$index]['id'])) {
            $this->imagesToDelete[] = $this->images[$index]['id'];
        }
        
        unset($this->images[$index]);
        $this->images = array_values($this->images);
        
        // Ensure at least one image is primary
        if (count($this->images) > 0 && !collect($this->images)->contains('is_primary', true)) {
            $this->images[0]['is_primary'] = true;
        }
    }

    public function setPrimaryImage($index)
    {
        foreach ($this->images as $i => &$image) {
            $image['is_primary'] = $i === $index;
        }
    }

    public function addVariantAttribute()
    {
        $this->variantAttributes[] = ['name' => '', 'values' => ['']];
    }

    public function removeVariantAttribute($index)
    {
        unset($this->variantAttributes[$index]);
        $this->variantAttributes = array_values($this->variantAttributes);
    }

    public function addAttributeValue($attributeIndex)
    {
        $this->variantAttributes[$attributeIndex]['values'][] = '';
    }

    public function removeAttributeValue($attributeIndex, $valueIndex)
    {
        unset($this->variantAttributes[$attributeIndex]['values'][$valueIndex]);
        $this->variantAttributes[$attributeIndex]['values'] = array_values($this->variantAttributes[$attributeIndex]['values']);
    }

    public function generateVariants()
    {
        // Clear existing new variants
        $this->newVariants = [];
        
        // Generate all combinations
        $combinations = $this->generateCombinations($this->variantAttributes);
        
        foreach ($combinations as $combination) {
            $attributes = [];
            foreach ($combination as $attr) {
                $attributes[$attr['name']] = $attr['value'];
            }
            
            // Check if variant already exists
            $exists = collect($this->variants)->contains(function ($variant) use ($attributes) {
                return $variant['attributes'] == $attributes;
            });
            
            if (!$exists) {
                $this->newVariants[] = [
                    'id' => null,
                    'sku' => $this->sku . '-' . strtoupper(Str::random(4)),
                    'price' => $this->price,
                    'quantity' => 0,
                    'attributes' => $attributes,
                    'is_active' => true,
                ];
            }
        }
    }

    private function generateCombinations($attributes, $i = 0, $current = [])
    {
        if ($i == count($attributes)) {
            return [$current];
        }
        
        $results = [];
        foreach ($attributes[$i]['values'] as $value) {
            if ($value !== '') {
                $newCurrent = $current;
                $newCurrent[] = ['name' => $attributes[$i]['name'], 'value' => $value];
                $results = array_merge($results, $this->generateCombinations($attributes, $i + 1, $newCurrent));
            }
        }
        
        return $results;
    }

    public function removeVariant($index, $isNew = false)
    {
        if ($isNew) {
            unset($this->newVariants[$index]);
            $this->newVariants = array_values($this->newVariants);
        } else {
            if (isset($this->variants[$index]['id'])) {
                $this->variantsToDelete[] = $this->variants[$index]['id'];
            }
            unset($this->variants[$index]);
            $this->variants = array_values($this->variants);
        }
    }

    private function extractVariantAttributes()
    {
        $attributes = [];
        
        foreach ($this->variants as $variant) {
            foreach ($variant['attributes'] as $name => $value) {
                if (!isset($attributes[$name])) {
                    $attributes[$name] = [];
                }
                if (!in_array($value, $attributes[$name])) {
                    $attributes[$name][] = $value;
                }
            }
        }
        
        $this->variantAttributes = [];
        foreach ($attributes as $name => $values) {
            $this->variantAttributes[] = ['name' => $name, 'values' => $values];
        }
    }

    public function save()
    {
        $this->validate([
            'sku' => 'required|unique:products,sku,' . $this->productId,
            'slug' => 'required|unique:products,slug,' . $this->productId,
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0|gt:price',
            'cost' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string',
            'translations.*.short_description' => 'nullable|string|max:500',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'exists:categories,id',
        ]);

        DB::transaction(function () {
            $productData = [
                'sku' => $this->sku,
                'slug' => $this->slug,
                'price' => $this->price,
                'compare_price' => $this->compare_price,
                'cost' => $this->cost,
                'quantity' => $this->quantity,
                'track_quantity' => $this->track_quantity,
                'is_active' => $this->is_active,
                'is_featured' => $this->is_featured,
                'weight' => $this->weight,
                'dimensions' => $this->dimensions,
                'brand_id' => $this->brand_id,
            ];

            if ($this->productId) {
                $this->product->update($productData);
            } else {
                $this->product = Product::create($productData);
            }

            // Update translations
            foreach ($this->translations as $locale => $translation) {
                $this->product->translations()->updateOrCreate(
                    ['locale' => $locale],
                    $translation
                );
            }

            // Update categories
            $this->product->categories()->sync($this->category_ids);

            // Update tags
            $this->product->tags()->sync($this->tag_ids);

            // Handle images
            foreach ($this->imagesToDelete as $imageId) {
                ProductImage::find($imageId)?->delete();
            }

            foreach ($this->images as $index => $image) {
                if ($image['id']) {
                    ProductImage::where('id', $image['id'])->update([
                        'is_primary' => $image['is_primary'],
                        'sort_order' => $index,
                    ]);
                } else {
                    $this->product->images()->create([
                        'image' => $image['image'],
                        'is_primary' => $image['is_primary'],
                        'sort_order' => $index,
                    ]);
                }
            }

            // Handle variants
            foreach ($this->variantsToDelete as $variantId) {
                ProductVariant::find($variantId)?->delete();
            }

            // Update existing variants
            foreach ($this->variants as $variant) {
                if ($variant['id']) {
                    ProductVariant::where('id', $variant['id'])->update([
                        'sku' => $variant['sku'],
                        'price' => $variant['price'],
                        'quantity' => $variant['quantity'],
                        'is_active' => $variant['is_active'],
                    ]);
                }
            }

            // Create new variants
            foreach ($this->newVariants as $variant) {
                $this->product->variants()->create($variant);
            }
        });

        $this->dispatch('toast', 
            type: 'success',
            message: $this->productId ? __('Product updated successfully') : __('Product created successfully')
        );

        $this->redirect('/admin/products', navigate: true);
    }

    public function with()
    {
        return [
            'layout' => 'admin.layout',
        ];
    }
}; ?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
            {{ $productId ? __('Edit Product') : __('Create Product') }}
        </h1>
        <p class="text-gray-600">{{ __('Fill in the product information below') }}</p>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Basic Information') }}</h2>
                    
                    <!-- Language Tabs -->
                    <div x-data="{ activeTab: 'en' }" class="mb-6">
                        <div class="border-b">
                            <nav class="flex -mb-px">
                                @foreach($locales as $locale)
                                    <button 
                                        type="button"
                                        @click="activeTab = '{{ $locale }}'"
                                        :class="activeTab === '{{ $locale }}' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                                        class="py-2 px-4 text-sm font-medium"
                                    >
                                        {{ $locale === 'en' ? 'English' : 'العربية' }}
                                    </button>
                                @endforeach
                            </nav>
                        </div>

                        @foreach($locales as $locale)
                            <div x-show="activeTab === '{{ $locale }}'" class="mt-4 space-y-4">
                                <!-- Product Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ __('Product Name') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model.lazy="translations.{{ $locale }}.name"
                                        class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                        required
                                    >
                                    @error('translations.'.$locale.'.name')
                                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Short Description -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ __('Short Description') }}
                                    </label>
                                    <textarea 
                                        wire:model="translations.{{ $locale }}.short_description"
                                        rows="2"
                                        class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    ></textarea>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ __('Description') }}
                                    </label>
                                    <textarea 
                                        wire:model="translations.{{ $locale }}.description"
                                        rows="5"
                                        class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    ></textarea>
                                </div>

                                <!-- SEO -->
                                <div class="border-t pt-4">
                                    <h3 class="font-medium mb-3">{{ __('SEO') }}</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Meta Title') }}
                                            </label>
                                            <input 
                                                type="text" 
                                                wire:model="translations.{{ $locale }}.meta_title"
                                                class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                            >
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Meta Description') }}
                                            </label>
                                            <textarea 
                                                wire:model="translations.{{ $locale }}.meta_description"
                                                rows="2"
                                                class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Images -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Product Images') }}</h2>
                    
                    <!-- Current Images -->
                    @if(count($images) > 0)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            @foreach($images as $index => $image)
                                <div class="relative group">
                                    <img 
                                        src="{{ $image['url'] }}" 
                                        alt="Product image"
                                        class="w-full h-32 object-cover rounded-lg"
                                    >
                                    <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
                                        <button 
                                            type="button"
                                            wire:click="setPrimaryImage({{ $index }})"
                                            class="p-1 bg-white rounded {{ $image['is_primary'] ? 'text-blue-600' : 'text-gray-600' }}"
                                            title="{{ __('Set as primary') }}"
                                        >
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        </button>
                                        <button 
                                            type="button"
                                            wire:click="removeImage({{ $index }})"
                                            class="p-1 bg-white rounded text-red-600"
                                            title="{{ __('Remove') }}"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    @if($image['is_primary'])
                                        <span class="absolute top-2 right-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                            {{ __('Primary') }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Upload New Images -->
                    <div>
                        <input 
                            type="file" 
                            wire:model="newImages"
                            multiple
                            accept="image/*"
                            class="hidden"
                            id="image-upload"
                        >
                        <label 
                            for="image-upload"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 cursor-pointer"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            {{ __('Upload Images') }}
                        </label>
                        
                        @if($newImages)
                            <button 
                                type="button"
                                wire:click="addImage"
                                class="ml-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                {{ __('Add') }} ({{ count($newImages) }})
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Variants -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Product Variants') }}</h2>
                    
                    <!-- Variant Attributes -->
                    <div class="mb-6">
                        <h3 class="font-medium mb-3">{{ __('Variant Options') }}</h3>
                        
                        @foreach($variantAttributes as $attrIndex => $attribute)
                            <div class="mb-4 p-4 border rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <input 
                                        type="text" 
                                        wire:model="variantAttributes.{{ $attrIndex }}.name"
                                        placeholder="{{ __('Option name (e.g. Size, Color)') }}"
                                        class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    >
                                    <button 
                                        type="button"
                                        wire:click="removeVariantAttribute({{ $attrIndex }})"
                                        class="text-red-600 hover:text-red-700"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="space-y-2">
                                    @foreach($attribute['values'] as $valueIndex => $value)
                                        <div class="flex items-center gap-2">
                                            <input 
                                                type="text" 
                                                wire:model="variantAttributes.{{ $attrIndex }}.values.{{ $valueIndex }}"
                                                placeholder="{{ __('Option value') }}"
                                                class="flex-1 px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                            >
                                            <button 
                                                type="button"
                                                wire:click="removeAttributeValue({{ $attrIndex }}, {{ $valueIndex }})"
                                                class="text-red-600"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                    
                                    <button 
                                        type="button"
                                        wire:click="addAttributeValue({{ $attrIndex }})"
                                        class="text-sm text-blue-600 hover:text-blue-700"
                                    >
                                        + {{ __('Add value') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                        
                        <button 
                            type="button"
                            wire:click="addVariantAttribute"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            + {{ __('Add option') }}
                        </button>
                        
                        @if(count($variantAttributes) > 0)
                            <button 
                                type="button"
                                wire:click="generateVariants"
                                class="ml-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
                            >
                                {{ __('Generate Variants') }}
                            </button>
                        @endif
                    </div>

                    <!-- Variants List -->
                    @if(count($variants) > 0 || count($newVariants) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Variant') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('SKU') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Price') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Stock') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($variants as $index => $variant)
                                        <tr>
                                            <td class="px-4 py-3 text-sm">
                                                @foreach($variant['attributes'] as $name => $value)
                                                    <span class="inline-block bg-gray-100 rounded px-2 py-1 text-xs mr-1">
                                                        {{ $name }}: {{ $value }}
                                                    </span>
                                                @endforeach
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="text" 
                                                    wire:model="variants.{{ $index }}.sku"
                                                    class="w-24 px-2 py-1 border rounded text-sm"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    wire:model="variants.{{ $index }}.price"
                                                    step="0.01"
                                                    class="w-20 px-2 py-1 border rounded text-sm"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    wire:model="variants.{{ $index }}.quantity"
                                                    class="w-16 px-2 py-1 border rounded text-sm"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <label class="inline-flex items-center">
                                                    <input 
                                                        type="checkbox" 
                                                        wire:model="variants.{{ $index }}.is_active"
                                                        class="rounded text-blue-600"
                                                    >
                                                </label>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button 
                                                    type="button"
                                                    wire:click="removeVariant({{ $index }})"
                                                    class="text-red-600 hover:text-red-700"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    
                                    @foreach($newVariants as $index => $variant)
                                        <tr class="bg-green-50">
                                            <td class="px-4 py-3 text-sm">
                                                @foreach($variant['attributes'] as $name => $value)
                                                    <span class="inline-block bg-green-100 rounded px-2 py-1 text-xs mr-1">
                                                        {{ $name }}: {{ $value }}
                                                    </span>
                                                @endforeach
                                                <span class="text-green-600 text-xs ml-2">{{ __('New') }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="text" 
                                                    wire:model="newVariants.{{ $index }}.sku"
                                                    class="w-24 px-2 py-1 border rounded text-sm"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    wire:model="newVariants.{{ $index }}.price"
                                                    step="0.01"
                                                    class="w-20 px-2 py-1 border rounded text-sm"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input 
                                                    type="number" 
                                                    wire:model="newVariants.{{ $index }}.quantity"
                                                    class="w-16 px-2 py-1 border rounded text-sm"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <label class="inline-flex items-center">
                                                    <input 
                                                        type="checkbox" 
                                                        wire:model="newVariants.{{ $index }}.is_active"
                                                        class="rounded text-blue-600"
                                                    >
                                                </label>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button 
                                                    type="button"
                                                    wire:click="removeVariant({{ $index }}, true)"
                                                    class="text-red-600 hover:text-red-700"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Status') }}</h2>
                    
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input 
                                type="checkbox" 
                                wire:model="is_active"
                                class="rounded text-blue-600"
                            >
                            <span class="ml-2">{{ __('Active') }}</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input 
                                type="checkbox" 
                                wire:model="is_featured"
                                class="rounded text-blue-600"
                            >
                            <span class="ml-2">{{ __('Featured') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Product Organization -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Product Organization') }}</h2>
                    
                    <!-- Brand -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }}</label>
                        <select 
                            wire:model="brand_id"
                            class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">{{ __('Select Brand') }}</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Categories -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Categories') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="border rounded-lg p-3 max-h-48 overflow-y-auto">
                            @foreach($categories as $category)
                                <label class="flex items-center mb-2">
                                    <input 
                                        type="checkbox" 
                                        wire:model="category_ids"
                                        value="{{ $category->id }}"
                                        class="rounded text-blue-600"
                                    >
                                    <span class="ml-2 text-sm">{{ $category->name }}</span>
                                </label>
                                
                                @foreach($category->children as $child)
                                    <label class="flex items-center mb-2 ml-4">
                                        <input 
                                            type="checkbox" 
                                            wire:model="category_ids"
                                            value="{{ $child->id }}"
                                            class="rounded text-blue-600"
                                        >
                                        <span class="ml-2 text-sm">{{ $child->name }}</span>
                                    </label>
                                @endforeach
                            @endforeach
                        </div>
                        @error('category_ids')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Tags') }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($tags as $tag)
                                <label class="inline-flex items-center">
                                    <input 
                                        type="checkbox" 
                                        wire:model="tag_ids"
                                        value="{{ $tag->id }}"
                                        class="rounded text-blue-600"
                                    >
                                    <span class="ml-1 text-sm">{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Pricing & Inventory -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Pricing & Inventory') }}</h2>
                    
                    <div class="space-y-4">
                        <!-- SKU -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('SKU') }} <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <input 
                                    type="text" 
                                    wire:model="sku"
                                    class="flex-1 px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                <button 
                                    type="button"
                                    wire:click="generateSku"
                                    class="px-3 py-2 text-sm bg-gray-100 rounded-lg hover:bg-gray-200"
                                >
                                    {{ __('Generate') }}
                                </button>
                            </div>
                            @error('sku')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Slug -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('URL Slug') }} <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="slug"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                            @error('slug')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Price -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Price') }} <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="number" 
                                wire:model="price"
                                step="0.01"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                            @error('price')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Compare Price -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Compare at Price') }}
                            </label>
                            <input 
                                type="number" 
                                wire:model="compare_price"
                                step="0.01"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                            >
                            @error('compare_price')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Cost -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Cost per item') }}
                            </label>
                            <input 
                                type="number" 
                                wire:model="cost"
                                step="0.01"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                            >
                        </div>

                        <!-- Track Quantity -->
                        <label class="flex items-center">
                            <input 
                                type="checkbox" 
                                wire:model="track_quantity"
                                class="rounded text-blue-600"
                            >
                            <span class="ml-2 text-sm">{{ __('Track quantity') }}</span>
                        </label>

                        <!-- Quantity -->
                        @if($track_quantity)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Quantity') }} <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    wire:model="quantity"
                                    class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                @error('quantity')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Shipping -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">{{ __('Shipping') }}</h2>
                    
                    <div class="space-y-4">
                        <!-- Weight -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Weight') }} (kg)
                            </label>
                            <input 
                                type="number" 
                                wire:model="weight"
                                step="0.01"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                            >
                        </div>

                        <!-- Dimensions -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Dimensions') }} (cm)
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <input 
                                    type="number" 
                                    wire:model="dimensions.length"
                                    placeholder="{{ __('Length') }}"
                                    class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                >
                                <input 
                                    type="number" 
                                    wire:model="dimensions.width"
                                    placeholder="{{ __('Width') }}"
                                    class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                >
                                <input 
                                    type="number" 
                                    wire:model="dimensions.height"
                                    placeholder="{{ __('Height') }}"
                                    class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-end gap-3">
            <a 
                href="/admin/products" 
                wire:navigate
                class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
                {{ __('Cancel') }}
            </a>
            <button 
                type="submit"
                wire:loading.attr="disabled"
                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
            >
                <span wire:loading.remove>{{ $productId ? __('Update Product') : __('Create Product') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>
</div>