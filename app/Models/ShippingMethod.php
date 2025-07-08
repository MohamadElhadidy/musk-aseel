<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'base_cost', 'calculation_type',
        'rates', 'min_days', 'max_days', 'is_active'
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'base_cost' => 'decimal:2',
        'rates' => 'array',
        'is_active' => 'boolean',
    ];

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(ShippingZone::class, 'shipping_method_zone')
            ->withPivot('cost_override')
            ->withTimestamps();
    }

    public function getNameAttribute($value)
    {
        $names = json_decode($value, true) ?? [];
        return $names[app()->getLocale()] ?? $names['en'] ?? '';
    }

    public function calculateCost($weight = 0, $subtotal = 0, $zone = null): float
    {
        $cost = $this->base_cost;
        
        if ($zone) {
            $zoneOverride = $this->zones()
                ->where('shipping_zone_id', $zone->id)
                ->first();
            
            if ($zoneOverride && $zoneOverride->pivot->cost_override) {
                return $zoneOverride->pivot->cost_override;
            }
        }
        
        switch ($this->calculation_type) {
            case 'weight_based':
                foreach ($this->rates as $rate) {
                    if ($weight >= $rate['min'] && $weight <= $rate['max']) {
                        $cost = $rate['cost'];
                        break;
                    }
                }
                break;
                
            case 'price_based':
                foreach ($this->rates as $rate) {
                    if ($subtotal >= $rate['min'] && $subtotal <= $rate['max']) {
                        $cost = $rate['cost'];
                        break;
                    }
                }
                break;
        }
        
        return $cost;
    }
}