<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];
    protected $casts = ['name' => 'array'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function getNameAttribute($value)
    {
        $names = json_decode($value, true) ?? [];
        return $names[app()->getLocale()] ?? $names['en'] ?? '';
    }

    public function getTranslatedName($locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        $names = json_decode($this->attributes['name'], true) ?? [];
        return $names[$locale] ?? $names['en'] ?? '';
    }
}