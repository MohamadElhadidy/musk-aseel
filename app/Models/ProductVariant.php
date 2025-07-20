<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'attributes',
        'sku',
        'price',
        'quantity',
        'is_active'
    ];

    protected $casts = [
        'attributes' => 'array',
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'is_active' => 'boolean'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attributes')
            ->withPivot('attribute_id')
            ->withTimestamps();
    }

    public function getAttributesStringAttribute(): string
    {
        if (is_array($this->attributes)) {
            return collect($this->attributes)
                ->map(fn($value, $key) => ucfirst($key) . ': ' . $value)
                ->implode(', ');
        }
        
        return '';
    }

    public function getNameAttribute(): string
    {
        return $this->product->name . ' - ' . $this->attributes_string;
    }

    public function isInStock(): bool
    {
        return !$this->product->track_quantity || $this->quantity > 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}