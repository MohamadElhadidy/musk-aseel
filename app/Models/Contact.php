<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    public function scopeReplied($query)
    {
        return $query->where('status', 'replied');
    }

    public function markAsRead()
    {
        if ($this->status === 'new') {
            $this->update(['status' => 'read']);
        }
    }

    public function markAsReplied()
    {
        $this->update(['status' => 'replied']);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'new' => 'yellow',
            'read' => 'blue',
            'replied' => 'green',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'new' => __('New'),
            'read' => __('Read'),
            'replied' => __('Replied'),
            default => $this->status
        };
    }
}