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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_quantity', false)
                ->orWhere('quantity', '>', 0);
        });
    }

    public function getPrimaryImageAttribute()
    {
        return $this->images()
            ->where('is_primary', true)
            ->first() ?? $this->images()->first();
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $image = $this->primary_image;
        return $image ? asset('storage/' . $image->image) : null;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }

    public function getFormattedPriceAttribute(): string
    {
        return app('currency')->format($this->price);
    }

    public function getFormattedComparePriceAttribute(): ?string
    {
        if (!$this->compare_price) return null;

        return app('currency')->format($this->compare_price);
    }

    public function getAverageRatingAttribute(): float
    {
        return round($this->reviews()->approved()->avg('rating') ?? 0, 1);
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->approved()->count();
    }

    public function isInStock(): bool
    {
        if (!$this->track_quantity) {
            return true;
        }

        return $this->quantity > 0;
    }

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function getAvailableQuantity(): int
    {
        if (!$this->track_quantity) {
            return 999999; // Unlimited
        }

        return $this->quantity;
    }

    public function decrementQuantity(int $quantity): void
    {
        if ($this->track_quantity) {
            $this->decrement('quantity', $quantity);
        }
    }

    public function incrementQuantity(int $quantity): void
    {
        if ($this->track_quantity) {
            $this->increment('quantity', $quantity);
        }
    }
}
