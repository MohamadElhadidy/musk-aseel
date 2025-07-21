<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryPerson extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'vehicle_number',
        'id_number',
        'is_active',
        'cod_balance'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cod_balance' => 'decimal:2'
    ];

    /**
     * Get delivery assignments
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    /**
     * Get COD collections
     */
    public function collections(): HasMany
    {
        return $this->hasMany(CodCollection::class);
    }

    /**
     * Get COD remittances
     */
    public function remittances(): HasMany
    {
        return $this->hasMany(CodRemittance::class);
    }

    /**
     * Scope active delivery persons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get current assignments
     */
    public function getCurrentAssignments()
    {
        return $this->assignments()
            ->whereIn('status', ['assigned', 'accepted', 'in_transit'])
            ->with('order');
    }

    /**
     * Get today's deliveries count
     */
    public function getTodaysDeliveriesCountAttribute(): int
    {
        return $this->assignments()
            ->whereDate('delivered_at', today())
            ->where('status', 'delivered')
            ->count();
    }

    /**
     * Get pending COD amount
     */
    public function getPendingCodAmountAttribute(): float
    {
        return $this->collections()
            ->where('status', 'collected')
            ->sum('amount');
    }

    /**
     * Get total collected amount
     */
    public function getTotalCollectedAttribute(): float
    {
        return $this->collections()
            ->where('status', 'collected')
            ->sum('amount');
    }

    /**
     * Get total remitted amount
     */
    public function getTotalRemittedAttribute(): float
    {
        return $this->remittances()
            ->where('status', 'verified')
            ->sum('total_amount');
    }

    /**
     * Get pending remittance amount
     */
    public function getPendingRemittanceAttribute(): float
    {
        return $this->total_collected - $this->total_remitted;
    }

    /**
     * Get completed deliveries count
     */
    public function getCompletedDeliveriesCountAttribute(): int
    {
        return $this->assignments()
            ->where('status', 'delivered')
            ->count();
    }

    /**
     * Get failed deliveries count
     */
    public function getFailedDeliveriesCountAttribute(): int
    {
        return $this->assignments()
            ->where('status', 'failed')
            ->count();
    }

    /**
     * Get success rate
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->completed_deliveries_count + $this->failed_deliveries_count;
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($this->completed_deliveries_count / $total) * 100, 2);
    }

    /**
     * Check if can be assigned new delivery
     */
    public function canBeAssigned(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check max concurrent assignments (configurable)
        $maxAssignments = config('delivery.max_concurrent_assignments', 10);
        $currentAssignments = $this->getCurrentAssignments()->count();

        return $currentAssignments < $maxAssignments;
    }

    /**
     * Update COD balance
     */
    public function updateCodBalance(): void
    {
        $balance = $this->collections()
            ->where('status', 'collected')
            ->whereDoesntHave('remittance')
            ->sum('amount');

        $this->update(['cod_balance' => $balance]);
    }
}