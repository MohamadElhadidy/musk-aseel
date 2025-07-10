<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class NewsletterSubscriber extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'token',
        'is_active',
        'confirmed_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function confirm(): void
    {
        $this->update([
            'confirmed_at' => now(),
        ]);
    }

    public function unsubscribe(): void
    {
        $this->update([
            'is_active' => false,
        ]);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function getUnsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe', ['token' => $this->token]);
    }
}