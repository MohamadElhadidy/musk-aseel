<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total',
        'currency_code',
        'exchange_rate',
        'coupon_id',
        'coupon_code',
        'shipping_method_id',
        'shipping_method_details',
        'payment_method',
        'notes',
        'shipped_at',
        'delivered_at',
        'is_cod',
        'cod_fee',
        'amount_to_collect',
        'payment_collected_at',
        'collected_by'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'shipping_method_details' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'is_cod' => 'boolean',
        'cod_fee' => 'decimal:2',
        'amount_to_collect' => 'decimal:2',
        'payment_collected_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Get user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get items
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get addresses
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /**
     * Get billing address
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    /**
     * Get shipping address
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    /**
     * Get payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get latest payment
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latest();
    }

    /**
     * Get coupon
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get shipping method
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Get status histories
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Get transactions
     */
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Get collector user
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return __('order.status.' . $this->status);
    }

    /**
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        $currency = Currency::where('code', $this->currency_code)->first();
        return $currency ? $currency->format($this->total / $this->exchange_rate) : number_format($this->total, 2);
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded(): bool
    {
        return in_array($this->status, ['delivered']) && 
               $this->delivered_at && 
               $this->delivered_at->gt(now()->subDays(30));
    }

    /**
     * Update status
     */
    public function updateStatus(string $status, ?string $comment = null, ?int $userId = null): void
    {
        $this->update(['status' => $status]);

        $this->statusHistories()->create([
            'status' => $status,
            'comment' => $comment,
            'created_by' => $userId ?? auth()->id()
        ]);

        // Update timestamps based on status
        switch ($status) {
            case 'shipped':
                $this->update(['shipped_at' => now()]);
                break;
            case 'delivered':
                $this->update(['delivered_at' => now()]);
                break;
        }
    }

    /**
     * Calculate totals
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum('total');
        $total = $subtotal - $this->discount_amount + $this->tax_amount + $this->shipping_amount;
        
        if ($this->is_cod) {
            $total += $this->cod_fee;
        }

        $this->update([
            'subtotal' => $subtotal,
            'total' => $total,
            'amount_to_collect' => $this->is_cod ? $total : 0
        ]);
    }

    /**
     * Scope pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered']);
    }

    /**
     * Scope COD orders
     */
    public function scopeCod($query)
    {
        return $query->where('is_cod', true);
    }

    /**
     * Scope unpaid COD orders
     */
    public function scopeUnpaidCod($query)
    {
        return $query->cod()->whereNull('payment_collected_at');
    }
}