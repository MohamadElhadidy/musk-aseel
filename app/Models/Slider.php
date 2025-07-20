<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Slider extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'mobile_image',
        'link',
        'button_text',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'title' => 'array',
        'subtitle' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean'
    ];

    public function getTitleAttribute($value)
    {
        $titles = json_decode($value, true) ?? [];
        return $titles[app()->getLocale()] ?? $titles['en'] ?? '';
    }

    public function getSubtitleAttribute($value)
    {
        $subtitles = json_decode($value, true) ?? [];
        return $subtitles[app()->getLocale()] ?? $subtitles['en'] ?? '';
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image);
    }

    public function getMobileImageUrlAttribute(): ?string
    {
        return $this->mobile_image ? asset('storage/' . $this->mobile_image) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}