<?php
// NewsletterCampaign.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NewsletterCampaign extends Model
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

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CodCollection::class);
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(CodRemittance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getCurrentAssignments()
    {
        return $this->assignments()
            ->whereIn('status', ['assigned', 'accepted', 'in_transit'])
            ->with('order');
    }

    public function getTodaysDeliveriesCountAttribute(): int
    {
        return $this->assignments()
            ->whereDate('delivered_at', today())
            ->where('status', 'delivered')
            ->count();
    }

    public function getPendingCodAmountAttribute(): float
    {
        return $this->collections()
            ->where('status', 'collected')
            ->sum('amount');
    }
