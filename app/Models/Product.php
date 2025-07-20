<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasTranslations;

class Product extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'brand_id',
        'sku',
        'slug',
        'price',
        'compare_price',
        'cost',
        'quantity',
        'track_quantity',
        'is_active',
        'is_featured',
        'views',
        'weight',
        'dimensions'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'track_quantity' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'dimensions' => 'array',
    ];

    protected $translatable = [
        'name',
        'description',
        'short_description',
        'meta_title',
        'meta_description'
    ];

    /**
     * Get brand
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get categories
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }

    /**
     * Get tags
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps();
    }

    /**
     * Get variants
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get images
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    /**
     * Get reviews
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get approved reviews
     */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    /**
     * Get wishlists
     */
    public function wishlists(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists')
            ->withTimestamps();
    }

    /**
     * Get order items
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get cart items
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get attributes
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
            ->withPivot(['attribute_value_id', 'text_value', 'number_value', 'boolean_value', 'date_value'])
            ->withTimestamps();
    }

    /**
     * Scope active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope in stock products
     */
    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_quantity', false)
                ->orWhere('quantity', '>', 0);
        });
    }

    /**
     * Get primary image
     */
    public function getPrimaryImageAttribute()
    {
        return $this->images()
            ->where('is_primary', true)
            ->first() ?? $this->images()->first();
    }

    /**
     * Get primary image URL
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        $image = $this->primary_image;
        return $image ? asset('storage/' . $image->image) : null;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }

    /**
     * Get final price (considering variants)
     */
    public function getFinalPriceAttribute(): float
    {
        if ($this->variants->count() > 0) {
            return $this->variants->min('price') ?? $this->price;
        }

        return $this->price;
    }

    /**
     * Get available quantity
     */
    public function getAvailableQuantityAttribute(): int
    {
        if (!$this->track_quantity) {
            return 999999; // Unlimited
        }

        if ($this->variants->count() > 0) {
            return $this->variants->sum('quantity');
        }

        return $this->quantity;
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        if (!$this->track_quantity) {
            return true;
        }

        if ($this->variants->count() > 0) {
            return $this->variants->where('is_active', true)->sum('quantity') > 0;
        }

        return $this->quantity > 0;
    }

    /**
     * Check if product has variants
     */
    public function hasVariants(): bool
    {
        return $this->variants->count() > 0;
    }

    /**
     * Get average rating
     */
    public function getAverageRatingAttribute(): float
    {
        $approvedReviews = $this->approvedReviews;
        
        if ($approvedReviews->count() === 0) {
            return 0;
        }

        return round($approvedReviews->avg('rating'), 1);
    }

    /**
     * Get reviews count
     */
    public function getReviewsCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    /**
     * Get URL
     */
    public function getUrlAttribute(): string
    {
        return route('products.show', $this->slug);
    }

    /**
     * Increment views
     */
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    /**
     * Get related products
     */
    public function getRelatedProducts(int $limit = 4)
    {
        $categoryIds = $this->categories->pluck('id');
        
        return static::active()
            ->where('id', '!=', $this->id)
            ->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            })
            ->inStock()
            ->limit($limit)
            ->get();
    }


    public function getAttributeValue(string $attributeCode)
    {
        $attribute = $this->attributes()
            ->where('code', $attributeCode)
            ->first();

        if (!$attribute) {
            return null;
        }

        $pivot = $attribute->pivot;

        return match($attribute->type) {
            'select', 'multiselect' => $pivot->attribute_value_id ? 
                AttributeValue::find($pivot->attribute_value_id) : null,
            'text' => $pivot->text_value,
            'number' => $pivot->number_value,
            'boolean' => $pivot->boolean_value,
            'date' => $pivot->date_value,
            default => null
        };
    }
}