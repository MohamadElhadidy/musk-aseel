<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CodRemittance extends Model
{
    use HasFactory;

    protected $fillable = [
        'remittance_number',
        'delivery_person_id',
        'total_amount',
        'order_count',
        'status',
        'submitted_by',
        'verified_by',
        'submitted_at',
        'verified_at',
        'notes',
        'discrepancy_notes'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'order_count' => 'integer',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($remittance) {
            if (empty($remittance->remittance_number)) {
                $remittance->remittance_number = static::generateRemittanceNumber();
            }
        });
    }

    public static function generateRemittanceNumber(): string
    {
        do {
            $number = 'RMT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::where('remittance_number', $number)->exists());

        return $number;
    }

    public function deliveryPerson(): BelongsTo
    {
        return $this->belongsTo(DeliveryPerson::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'cod_remittance_orders')
            ->withPivot('amount')
            ->withTimestamps();
    }
}