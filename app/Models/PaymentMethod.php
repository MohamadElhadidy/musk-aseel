<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gateway',
        'type',
        'gateway_customer_id',
        'gateway_payment_method_id',
        'details',
        'is_default',
        'expires_at'
    ];

    protected $casts = [
        'details' => 'array',
        'is_default' => 'boolean',
        'expires_at' => 'datetime'
    ];

    protected $hidden = [
        'gateway_customer_id',
        'gateway_payment_method_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getMaskedDetailsAttribute(): array
    {
        $details = $this->details;
        
        if ($this->type === 'card' && isset($details['last4'])) {
            $details['number'] = '**** **** **** ' . $details['last4'];
        }
        
        return $details;
    }
}