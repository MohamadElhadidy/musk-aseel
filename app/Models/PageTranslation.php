<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'locale',
        'title',
        'content',
        'meta_title',
        'meta_description'
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}