<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'coupon_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    /**
     * Get current cart for user or session
     */
    public static function getCurrentCart(): ?Cart
    {
        if (auth()->check()) {
            return static::firstOrCreate(['user_id' => auth()->id()]);
        } else {
            $sessionId = session('cart_session_id');
            
            if (!$sessionId) {
                $sessionId = Str::uuid()->toString();
                session(['cart_session_id' => $sessionId]);
            }
            
            return static::firstOrCreate(['session_id' => $sessionId]);
        }
    }

    /**
     * Get user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get items
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get coupon
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Add item to cart
     */
    public function addItem(Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        // Check if item already exists
        $existingItem = $this->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $quantity);
            $existingItem->update(['price' => $variant ? $variant->price : $product->price]);
            return $existingItem;
        }

        // Create new item
        $item = $this->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
            'price' => $variant ? $variant->price : $product->price
        ]);

        $this->calculateTotals();

        return $item;
    }

    /**
     * Update item quantity
     */
    public function updateItemQuantity(int $itemId, int $quantity): void
    {
        $item = $this->items()->find($itemId);
        
        if ($item) {
            if ($quantity <= 0) {
                $item->delete();
            } else {
                $item->update(['quantity' => $quantity]);
            }
            
            $this->calculateTotals();
        }
    }

    /**
     * Remove item
     */
    public function removeItem(int $itemId): void
    {
        $this->items()->find($itemId)?->delete();
        $this->calculateTotals();
    }

    /**
     * Apply coupon
     */
    public function applyCoupon(string $code): bool
    {
        $coupon = Coupon::where('code', $code)
            ->active()
            ->valid()
            ->first();

        if (!$coupon) {
            return false;
        }

        // Check if user has already used this coupon
        if (auth()->check() && !$coupon->canBeUsedBy(auth()->user())) {
            return false;
        }

        // Check minimum amount
        if ($coupon->minimum_amount && $this->subtotal < $coupon->minimum_amount) {
            return false;
        }

        $this->update(['coupon_id' => $coupon->id]);
        $this->calculateTotals();

        return true;
    }

    /**
     * Remove coupon
     */
    public function removeCoupon(): void
    {
        $this->update(['coupon_id' => null]);
        $this->calculateTotals();
    }

    /**
     * Calculate totals
     */
    public function calculateTotals(): void
    {
        // Calculate subtotal
        $subtotal = $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Calculate discount
        $discountAmount = 0;
        if ($this->coupon) {
            if ($this->coupon->type === 'percentage') {
                $discountAmount = ($subtotal * $this->coupon->value) / 100;
            } else {
                $discountAmount = min($this->coupon->value, $subtotal);
            }
        }

        // Calculate tax (assuming a fixed rate for now)
        $taxRate = Setting::get('tax_rate', 0);
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = ($taxableAmount * $taxRate) / 100;

        // Calculate total
        $total = $subtotal - $discountAmount + $taxAmount + $this->shipping_amount;

        // Update cart
        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => max(0, $total)
        ]);
    }

    /**
     * Clear cart
     */
    public function clear(): void
    {
        $this->items()->delete();
        $this->update([
            'coupon_id' => null,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total' => 0
        ]);
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return $this->items->count() === 0;
    }

    /**
     * Get item count
     */
    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Check if all items are in stock
     */
    public function canCheckout(): bool
    {
        foreach ($this->items as $item) {
            if (!$item->isInStock()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge with another cart
     */
    public function mergeWith(Cart $otherCart): void
    {
        foreach ($otherCart->items as $item) {
            $this->addItem($item->product, $item->quantity, $item->variant);
        }

        $otherCart->clear();
    }
}