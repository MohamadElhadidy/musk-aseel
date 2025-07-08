<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'description', 'type', 'value', 'minimum_amount',
        'usage_limit', 'usage_limit_per_user', 'used_count',
        'is_active', 'valid_from', 'valid_until'
    ];

    protected $casts = [
        'description' => 'array',
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('usage_count')
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getDescriptionAttribute($value)
    {
        $descriptions = json_decode($value, true) ?? [];
        return $descriptions[app()->getLocale()] ?? $descriptions['en'] ?? '';
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        
        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_until && $now->gt($this->valid_until)) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        
        return true;
    }

    public function canBeUsedBy(?User $user): bool
    {
        if (!$this->isValid()) return false;
        if (!$user) return true;
        
        if ($this->usage_limit_per_user) {
            $userUsage = $user->getCouponUsageCount($this);
            if ($userUsage >= $this->usage_limit_per_user) return false;
        }
        
        return true;
    }
}