<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CategoryTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'locale',
        'name',
        'description',
        'meta_title',
        'meta_description'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}