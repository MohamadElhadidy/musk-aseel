<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

new class extends Component
{
    use WithPagination;

    public string $query = '';
    public string $sortBy = 'relevance';
    public array $selectedCategories = [];
    public array $selectedBrands = [];
    public ?float $minPrice = null;
    public ?float $maxPrice = null;

    public function mount()
    {
        $this->query = request()->get('q', '');
    }

    public function updatedQuery()
    {
        $this->resetPage();
    }

    public function updatedSortBy()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategories()
    {
        $this->resetPage();
    }

    public function updatedSelectedBrands()
    {
        $this->resetPage();
    }

    public function applyPriceFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->selectedCategories = [];
        $this->selectedBrands = [];
        $this->minPrice = null;
        $this->maxPrice = null;
        $this->resetPage();
    }

    public function with()
    {
        $productsQuery = Product::active()
            ->with(['images', 'translations', 'brand', 'categories']);

        // Search query
        if ($this->query) {
            $productsQuery->where(function ($q) {
                // Search in translations
                $q->whereHas('translations', function ($tq) {
                    $tq->where('locale', app()->getLocale())
                       ->where(function ($sq) {
                           $sq->where('name', 'like', "%{$this->query}%")
                              ->orWhere('description', 'like', "%{$this->query}%")
                              ->orWhere('short_description', 'like', "%{$this->query}%");
                       });
                })
                // Search in SK