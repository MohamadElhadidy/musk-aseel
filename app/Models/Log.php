<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'level',
        'message',
        'context',
        'loggable_type',
        'loggable_id',
        'user_id',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'context' => 'array'
    ];

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public static function record(string $type, string $message, array $context = [], string $level = 'info')
    {
        return static::create([
            'type' => $type,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}