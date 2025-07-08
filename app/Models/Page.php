<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = ['slug', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    protected $translatable = ['title', 'content', 'meta_title', 'meta_description'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}