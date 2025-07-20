<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'date_of_birth',
        'gender',
        'preferred_locale',
        'is_active',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'is_admin' => 'boolean',
    ];

    /**
     * Get user's orders
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get user's addresses
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    /**
     * Get user's default address
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    /**
     * Get user's carts
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get user's current cart
     */
    public function currentCart(): HasOne
    {
        return $this->hasOne(Cart::class)->latest();
    }

    /**
     * Get user's reviews
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get user's wishlist
     */
    public function wishlist(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')
            ->withTimestamps();
    }

    /**
     * Get user's coupons
     */
    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class)
            ->withPivot('usage_count')
            ->withTimestamps();
    }

    /**
     * Get user's payment methods
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get user's default payment method
     */
    public function defaultPaymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class)->where('is_default', true);
    }

    /**
     * Get user's transactions
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get logs created by this user
     */
    public function logs(): HasMany
    {
        return $this->hasMany(Log::class);
    }

    /**
     * Get user's newsletter subscriptions
     */
    public function newsletterSubscription(): HasOne
    {
        return $this->hasOne(NewsletterSubscriber::class, 'email', 'email');
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Check if user has completed any order
     */
    public function hasCompletedOrder(): bool
    {
        return $this->orders()
            ->whereIn('status', ['delivered', 'completed'])
            ->exists();
    }

    /**
     * Check if user can review a product
     */
    public function canReviewProduct(Product $product): bool
    {
        // Check if user has purchased the product
        $hasPurchased = $this->orders()
            ->whereHas('items', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            })
            ->whereIn('status', ['delivered'])
            ->exists();
            
        // Check if user hasn't already reviewed
        $hasReviewed = $this->reviews()
            ->where('product_id', $product->id)
            ->exists();
            
        return $hasPurchased && !$hasReviewed;
    }

    /**
     * Check if user has used a coupon
     */
    public function hasUsedCoupon(Coupon $coupon): bool
    {
        return $this->coupons()
            ->where('coupon_id', $coupon->id)
            ->exists();
    }

    /**
     * Get coupon usage count
     */
    public function getCouponUsageCount(Coupon $coupon): int
    {
        $pivot = $this->coupons()
            ->where('coupon_id', $coupon->id)
            ->first();
            
        return $pivot ? $pivot->pivot->usage_count : 0;
    }

    /**
     * Check if product is wishlisted
     */
    public function isWishlisted(Product $product): bool
    {
        return $this->wishlist()
            ->where('product_id', $product->id)
            ->exists();
    }

    /**
     * Get total spent attribute
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->orders()
            ->whereIn('status', ['delivered', 'completed'])
            ->sum('total');
    }

    /**
     * Get orders count attribute
     */
    public function getOrdersCountAttribute(): int
    {
        return $this->orders()->count();
    }

    /**
     * Get average order value
     */
    public function getAverageOrderValueAttribute(): float
    {
        $completedOrders = $this->orders()
            ->whereIn('status', ['delivered', 'completed']);
            
        $count = $completedOrders->count();
        
        return $count > 0 ? $completedOrders->sum('total') / $count : 0;
    }

    /**
     * Scope active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope admin users
     */
    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    /**
     * Scope customers (non-admin users)
     */
    public function scopeCustomers($query)
    {
        return $query->where('is_admin', false);
    }

    /**
     * Check if user is subscribed to newsletter
     */
    public function isSubscribedToNewsletter(): bool
    {
        return $this->newsletterSubscription()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get user's preferred currency
     */
    public function getPreferredCurrency(): Currency
    {
        // You can store user's preferred currency in the database
        // For now, return the default
        return Currency::getDefault();
    }

    /**
     * Send email verification notification
     */
    public function sendEmailVerificationNotification()
    {
        // Implement your email verification logic
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordResetNotification($token)
    {
        // Implement your password reset logic
    }
}