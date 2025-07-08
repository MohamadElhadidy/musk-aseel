<?php

use Livewire\Volt\Component;
use App\Models\Slider;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

new class extends Component
{
    public $sliders;
    public $banners;
    public $featuredCategories;
    public $featuredProducts;
    public $newArrivals;
    public $bestSellers;

    public function mount()
    {
        $this->sliders = Slider::active()
            ->orderBy('sort_order')
            ->get();

        $this->banners = Banner::active()
            ->where('position', 'home_top')
            ->orderBy('sort_order')
            ->take(3)
            ->get();

        $this->featuredCategories = Category::active()
            ->featured()
            ->with('translations')
            ->take(6)
            ->get();

        $this->featuredProducts = Product::active()
            ->featured()
            ->with(['images', 'translations'])
            ->take(8)
            ->get();

        $this->newArrivals = Product::active()
            ->whereHas('tags', function ($q) {
                $q->where('slug', 'new-arrival');
            })
            ->with(['images', 'translations'])
            ->latest()
            ->take(8)
            ->get();

        $this->bestSellers = Product::active()
            ->whereHas('tags', function ($q) {
                $q->where('slug', 'best-seller');
            })
            ->with(['images', 'translations'])
            ->orderBy('views', 'desc')
            ->take(8)
            ->get();
    }

    public function with()
    {
        return [
            'layout' => 'components.layouts.app',
        ];
    }
}; ?>

<div>

    <!-- Hero Slider -->
    @if($sliders->count() > 0)
        <section class="relative" x-data="{ currentSlide: 0 }">
            <div class="relative h-[400px] md:h-[500px] overflow-hidden">
                @foreach($sliders as $index => $slider)
                    <div 
                        x-show="currentSlide === {{ $index }}"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-300"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                        class="absolute inset-0"
                    >
                        <img 
                            src="{{ asset('storage/' . $slider->image) }}" 
                            alt="{{ is_array($slider->title) ? $slider->title[app()->getLocale()] ?? $slider->title['en'] : $slider->title }}"
                            class="w-full h-full object-cover"
                        >
                        <div class="absolute inset-0 bg-black bg-opacity-40">
                            <div class="container mx-auto px-4 h-full flex items-center">
                                <div class="text-white max-w-lg">
                                    @if($slider->title)
                                        <h2 class="text-3xl md:text-5xl font-bold mb-4">
                                            {{ is_array($slider->title) ? $slider->title[app()->getLocale()] ?? $slider->title['en'] : $slider->title }}
                                        </h2>
                                    @endif
                                    @if($slider->subtitle)
                                        <p class="text-lg md:text-xl mb-6">
                                            {{ is_array($slider->subtitle) ? $slider->subtitle[app()->getLocale()] ?? $slider->subtitle['en'] : $slider->subtitle }}
                                        </p>
                                    @endif
                                    @if($slider->link && $slider->button_text)
                                        <a href="{{ $slider->link }}" wire:navigate class="inline-block bg-white text-gray-900 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                                            {{ is_array($slider->button_text) ? $slider->button_text[app()->getLocale()] ?? $slider->button_text['en'] : $slider->button_text }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Slider Controls -->
            <button 
                @click="currentSlide = currentSlide === 0 ? {{ $sliders->count() - 1 }} : currentSlide - 1"
                class="absolute top-1/2 {{ app()->getLocale() === 'ar' ? 'right-4' : 'left-4' }} transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-75 rounded-full p-2"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ app()->getLocale() === 'ar' ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7' }}"></path>
                </svg>
            </button>
            <button 
                @click="currentSlide = currentSlide === {{ $sliders->count() - 1 }} ? 0 : currentSlide + 1"
                class="absolute top-1/2 {{ app()->getLocale() === 'ar' ? 'left-4' : 'right-4' }} transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-75 rounded-full p-2"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ app()->getLocale() === 'ar' ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' }}"></path>
                </svg>
            </button>

            <!-- Slider Dots -->
            <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2">
                @foreach($sliders as $index => $slider)
                    <button 
                        @click="currentSlide = {{ $index }}"
                        :class="currentSlide === {{ $index }} ? 'bg-white' : 'bg-white bg-opacity-50'"
                        class="w-3 h-3 rounded-full transition"
                    ></button>
                @endforeach
            </div>

            <!-- Auto-play -->
            <div x-init="setInterval(() => { currentSlide = currentSlide === {{ $sliders->count() - 1 }} ? 0 : currentSlide + 1 }, 5000)"></div>
        </section>
    @endif


        <!-- Promotional Banners -->
    @if($banners->count() > 0)
        <section class="container mx-auto px-4 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($banners as $banner)
                    <a href="{{ $banner->link }}" wire:navigate class="group relative overflow-hidden rounded-lg">
                        <img 
                            src="{{ asset('storage/' . $banner->image) }}" 
                           alt="{{ is_array($banner->title) ? $banner->title[app()->getLocale()] ?? $banner->title['en'] : $banner->title }}"
                            class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300"
                        >
                        @if($banner->title)
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent flex items-end p-4">
                                <h3 class="text-white text-xl font-semibold">
                                    {{ is_array($banner->title) ? $banner->title[app()->getLocale()] ?? $banner->title['en'] : $banner->title }}
                                </h3>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

  <!-- Featured Categories -->
    @if($featuredCategories->count() > 0)
        <section class="bg-gray-100 py-12">
            <div class="container mx-auto px-4">
                <h2 class="text-2xl md:text-3xl font-bold text-center mb-8">{{ __('Shop by Category') }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                    @foreach($featuredCategories as $category)
                        <a href="/categories/{{ $category->slug }}" wire:navigate class="group text-center">
                            <div class="bg-white rounded-lg p-6 group-hover:shadow-lg transition">
                                @if($category->icon)
                                    <div class="w-16 h-16 mx-auto mb-4 text-blue-600">
                                        <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <!-- This would be replaced with actual icon -->
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                @endif
                                <h3 class="font-semibold text-gray-900 group-hover:text-blue-600 transition">
                                    {{ $category->name }}
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ $category->products_count }} {{ __('Products') }}
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif


    <!-- Featured Products -->
    @if($featuredProducts->count() > 0)
        <section class="container mx-auto px-4 py-12">
            <h2 class="text-2xl md:text-3xl font-bold text-center mb-8">{{ __('Featured Products') }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                @foreach($featuredProducts as $product)
                    <livewire:product.card :product="$product" :key="'featured-'.$product->id" />
                @endforeach
            </div>
        </section>
    @endif


    <!-- New Arrivals -->
    @if($newArrivals->count() > 0)
        <section class="bg-gray-100 py-12">
            <div class="container mx-auto px-4">
                <h2 class="text-2xl md:text-3xl font-bold text-center mb-8">{{ __('New Arrivals') }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    @foreach($newArrivals as $product)
                        <livewire:product.card :product="$product" :key="'new-'.$product->id" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif




    <!-- Best Sellers -->
    @if($bestSellers->count() > 0)
        <section class="container mx-auto px-4 py-12">
            <h2 class="text-2xl md:text-3xl font-bold text-center mb-8">{{ __('Best Sellers') }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                @foreach($bestSellers as $product)
                    <livewire:product.card :product="$product" :key="'best-'.$product->id" />
                @endforeach
            </div>
        </section>
    @endif

    <!-- Newsletter Section -->
    <section class="bg-blue-600 py-12">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">{{ __('Stay Updated') }}</h2>
            <p class="text-white mb-8">{{ __('Subscribe to our newsletter and get exclusive offers') }}</p>
            <livewire:shared.newsletter-form />
        </div>
    </section>
</div>