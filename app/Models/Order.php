<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'shipping_method_details' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->order_number = static::generateOrderNumber();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function getShippingAddress()
    {
        return $this->addresses()->where('type', 'shipping')->first();
    }


    public function shippingAddress()
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    public function billingAddress()
    {
        return $this->addresses()->where('type', 'billing')->first();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered', 'completed']);
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $year = date('Y');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$year}-{$random}";
    }

    public function updateStatus(string $status, ?string $comment = null, ?int $userId = null): void
    {
        $this->update(['status' => $status]);

        $this->statusHistories()->create([
            'status' => $status,
            'comment' => $comment,
            'created_by' => $userId
        ]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->status, ['delivered', 'completed']);
    }

    public function getFormattedTotalAttribute(): string
    {
        $currency = Currency::where('code', $this->currency_code)->first();
        return $currency ? $currency->symbol . ' ' . number_format($this->total, 2) : $this->total;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'indigo',
            'delivered' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => __('Pending'),
            'processing' => __('Processing'),
            'shipped' => __('Shipped'),
            'delivered' => __('Delivered'),
            'cancelled' => __('Cancelled'),
            'refunded' => __('Refunded'),
            default => $this->status
        };
    }

    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum('total');

        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + $this->shipping_amount + $this->tax_amount - $this->discount_amount
        ]);
    }
}
