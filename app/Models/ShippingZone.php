<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];
    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    public function cities()
    {
        return $this->belongsToMany(City::class, 'shipping_zone_cities');
    }

    public function shippingMethods()
    {
        return $this->belongsToMany(ShippingMethod::class, 'shipping_method_zone')
            ->withPivot('cost_override')
            ->withTimestamps();
    }

    public function getNameAttribute($value)
    {
        $names = json_decode($value, true) ?? [];
        return $names[app()->getLocale()] ?? $names['en'] ?? '';
    }
}