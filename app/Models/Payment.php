<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'payment_method', 'status',
        'amount', 'currency_code', 'transaction_id',
        'gateway_response'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'green',
            'processing' => 'blue',
            'pending' => 'yellow',
            'failed' => 'red',
            'cancelled' => 'gray',
            'refunded' => 'purple',
            default => 'gray'
        };
    }
}