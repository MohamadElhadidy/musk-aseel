<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'subtitle', 'image', 'mobile_image',
        'link', 'button_text', 'sort_order', 'is_active'
    ];

    protected $casts = [
        'title' => 'array',
        'subtitle' => 'array',
        'button_text' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}