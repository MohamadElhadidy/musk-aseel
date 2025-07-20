<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'token',
        'status',
        'ip_address',
        'confirmed_at',
        'subscribed_at',
        'unsubscribed_at'
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscriber) {
            if (empty($subscriber->token)) {
                $subscriber->token = Str::random(32);
            }
            if (empty($subscriber->subscribed_at)) {
                $subscriber->subscribed_at = now();
            }
        });
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterCampaign::class, 'newsletter_campaign_subscribers')
            ->withPivot(['status', 'sent_at', 'opened_at', 'clicked_at', 'open_count', 'click_count'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function confirm(): void
    {
        $this->update([
            'status' => 'active',
            'confirmed_at' => now()
        ]);
    }

    public function unsubscribe(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now()
        ]);
    }

    public function getUnsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe', $this->token);
    }
}