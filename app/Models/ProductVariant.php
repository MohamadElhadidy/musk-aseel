<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'attributes', 'sku', 
        'price', 'quantity', 'is_active'
    ];

    protected $casts = [
        'attributes' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    public function getAttributesStringAttribute(): string
    {
        $parts = [];
        foreach ($this->attributes as $key => $value) {
            $parts[] = ucfirst($key) . ': ' . $value;
        }
        return implode(', ', $parts);
    }
}