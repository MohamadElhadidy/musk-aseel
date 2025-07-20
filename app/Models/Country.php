<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active'];
    
    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean'
    ];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
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