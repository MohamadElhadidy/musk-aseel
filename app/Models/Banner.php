<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'position',
        'title',
        'image',
        'link',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'title' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean'
    ];

    public function getTitleAttribute($value)
    {
        $titles = json_decode($value, true) ?? [];
        return $titles[app()->getLocale()] ?? $titles['en'] ?? '';
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePosition($query, string $position)
    {
        return $query->where('position', $position);
    }
}