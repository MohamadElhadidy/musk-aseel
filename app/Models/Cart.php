<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public static function getCurrentCart()
    {
        if (auth()->check()) {
            $cart = static::where('user_id', auth()->id())->latest()->first();
        } else {
            $cart = static::where('session_id', session()->getId())->latest()->first();
        }

        if (!$cart) {
            $cart = static::create([
                'session_id' => auth()->check() ? null : session()->getId(),
                'user_id' => auth()->id(),
            ]);
        }

        return $cart;
    }

    public function addItem(Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        $price = $variant ? $variant->price : $product->price;
        
        $item = $this->items()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id
            ],
            [
                'quantity' => $quantity,
                'price' => $price
            ]
        );

        $this->calculateTotals();

        return $item;
    }

    public function updateItemQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => $quantity]);
        }

        $this->calculateTotals();
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
        $this->calculateTotals();
    }

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

    public function applyCoupon(Coupon $coupon): bool
    {
        if (!$coupon->isValid() || !$coupon->canBeUsedBy(auth()->user())) {
            return false;
        }

        $this->update(['coupon_id' => $coupon->id]);
        $this->calculateTotals();

        return true;
    }

    public function removeCoupon(): void
    {
        $this->update(['coupon_id' => null]);
        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $subtotal = 0;

        foreach ($this->items as $item) {
            $subtotal += $item->price * $item->quantity;
        }

        $discountAmount = 0;
        if ($this->coupon) {
            if ($this->coupon->type === 'percentage') {
                $discountAmount = $subtotal * ($this->coupon->value / 100);
            } else {
                $discountAmount = min($this->coupon->value, $subtotal);
            }
        }

        $taxRate = config('shop.tax_rate', 0);
        $taxAmount = ($subtotal - $discountAmount) * ($taxRate / 100);

        $total = $subtotal - $discountAmount + $taxAmount + $this->shipping_amount;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total
        ]);
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items->count() === 0;
    }

    public function merge(Cart $otherCart): void
    {
        foreach ($otherCart->items as $item) {
            $this->addItem($item->product, $item->quantity, $item->variant);
        }

        $otherCart->delete();
    }

    public function canCheckout(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        foreach ($this->items as $item) {
            if (!$item->product->isInStock() || $item->quantity > $item->product->getAvailableQuantity()) {
                return false;
            }
        }

        return true;
    }
}