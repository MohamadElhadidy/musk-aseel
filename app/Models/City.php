<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use HasFactory;

    protected $fillable = ['country_id', 'name', 'is_active'];
    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getNameAttribute($value)
    {
        $names = json_decode($value, true) ?? [];
        return $names[app()->getLocale()] ?? $names['en'] ?? '';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}