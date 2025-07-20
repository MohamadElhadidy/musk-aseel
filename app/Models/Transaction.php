<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'gateway_transaction_id',
        'transactionable_type',
        'transactionable_id',
        'gateway',
        'type',
        'status',
        'amount',
        'currency_code',
        'gateway_request',
        'gateway_response',
        'gateway_status',
        'gateway_message',
        'reference_number',
        'metadata',
        'processed_at',
        'failed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_request' => 'array',
        'gateway_response' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TransactionLog::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'green',
            'processing' => 'blue',
            'pending' => 'yellow',
            'failed' => 'red',
            'cancelled' => 'gray',
            'expired' => 'gray',
            default => 'gray'
        };
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now()
        ]);
    }

    public function markAsFailed(string $message = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'gateway_message' => $message
        ]);
    }
}