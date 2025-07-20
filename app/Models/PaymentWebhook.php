<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'event_id',
        'event_type',
        'payload',
        'headers',
        'status',
        'transaction_id',
        'error_message',
        'processed_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'processed_at' => 'datetime'
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now()
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message
        ]);
    }
}
        'subject',
        'content',
        'from_name',
        'from_email',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'opened',
        'clicked',
        'unsubscribed',
        'bounced',
        'stats'
    ];

    protected $casts = [
        'subject' => 'array',
        'content' => 'array',
        'stats' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'opened' => 'integer',
        'clicked' => 'integer',
        'unsubscribed' => 'integer',
        'bounced' => 'integer'
    ];

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterSubscriber::class, 'newsletter_campaign_subscribers')
            ->withPivot(['status', 'sent_at', 'opened_at', 'clicked_at', 'open_count', 'click_count'])
            ->withTimestamps();
    }

    public function getSubjectAttribute($value)
    {
        $subjects = json_decode($value, true) ?? [];
        return $subjects[app()->getLocale()] ?? $subjects['en'] ?? '';
    }

    public function getContentAttribute($value)
    {
        $contents = json_decode($value, true) ?? [];
        return $contents[app()->getLocale()] ?? $contents['en'] ?? '';
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function getOpenRateAttribute(): float
    {
        return $this->total_recipients > 0 ? 
            round(($this->opened / $this->total_recipients) * 100, 2) : 0;
    }

    public function getClickRateAttribute(): float
    {
        return $this->total_recipients > 0 ? 
            round(($this->clicked / $this->total_recipients) * 100, 2) : 0;
    }
}