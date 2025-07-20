<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasTranslations;

class Attribute extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'code',
        'type',
        'is_filterable',
        'is_variant',
        'is_required',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'is_variant' => 'boolean',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    protected $translatable = ['name'];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attributes')
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(AttributeGroup::class, 'attribute_group_mappings')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attributes')
            ->withPivot(['attribute_value_id', 'text_value', 'number_value', 'boolean_value', 'date_value'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    public function scopeVariant($query)
    {
        return $query->where('is_variant', true);
    }
}