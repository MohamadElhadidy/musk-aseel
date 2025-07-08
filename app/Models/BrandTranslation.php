<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'locale',
        'name',
        'description'
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}