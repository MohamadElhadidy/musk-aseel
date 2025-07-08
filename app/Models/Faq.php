<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = ['sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    protected $translatable = ['question', 'answer'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}