<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate',
        'is_active',
        'is_default'
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function format($amount): string
    {
        $converted = $amount * $this->exchange_rate;
        return $this->symbol . ' ' . number_format($converted, 2);
    }

    public function convert($amount): float
    {
        return round($amount * $this->exchange_rate, 2);
    }

    public static function getDefault()
    {
        return static::default()->first() ?? static::first();
    }
}