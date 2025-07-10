<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'gender',
        'preferred_locale',
        'is_active',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlist(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')
            ->withTimestamps();
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class)
            ->withPivot('usage_count')
            ->withTimestamps();
    }

    public function cart()
    {
        return $this->hasOne(Cart::class)->latest();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'notifiable_id')
            ->where('notifiable_type', static::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    public function getDefaultAddressAttribute()
    {
        return $this->addresses()
            ->where('is_default', true)
            ->where('type', 'shipping')
            ->first();
    }

    public function getDefaultBillingAddressAttribute()
    {
        return $this->addresses()
            ->where('is_default', true)
            ->where('type', 'billing')
            ->first();
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function hasCompletedOrder(): bool
    {
        return $this->orders()
            ->whereIn('status', ['delivered', 'completed'])
            ->exists();
    }

    public function canReviewProduct(Product $product): bool
    {
        // Check if user has purchased the product
        $hasPurchased = $this->orders()
            ->whereHas('items', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            })
            ->whereIn('status', ['delivered']) // Only delivered orders can be reviewed
            ->exists();
            
        // Check if user hasn't already reviewed
        $hasReviewed = $this->reviews()
            ->where('product_id', $product->id)
            ->exists();
            
        return $hasPurchased && !$hasReviewed;
    }

    public function hasUsedCoupon(Coupon $coupon): bool
    {
        return $this->coupons()
            ->where('coupon_id', $coupon->id)
            ->exists();
    }

    public function getCouponUsageCount(Coupon $coupon): int
    {
        $pivot = $this->coupons()
            ->where('coupon_id', $coupon->id)
            ->first();
            
        return $pivot ? $pivot->pivot->usage_count : 0;
    }

    public function isWishlisted(Product $product): bool
    {
        return $this->wishlist()
            ->where('product_id', $product->id)
            ->exists();
    }

    public function getTotalSpentAttribute(): float
    {
        return $this->orders()
            ->whereIn('status', ['delivered', 'completed'])
            ->sum('total');
    }

    public function getOrdersCountAttribute(): int
    {
        return $this->orders()->count();
    }
}