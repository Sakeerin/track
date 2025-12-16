<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'subscription_id',
        'event_id',
        'channel',
        'destination',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the subscription this log belongs to
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the event this log is for
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Scope to get successful notifications
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope to get failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get throttled notifications
     */
    public function scopeThrottled($query)
    {
        return $query->where('status', 'throttled');
    }

    /**
     * Scope to get notifications within time range
     */
    public function scopeWithinTimeRange($query, $startTime, $endTime = null)
    {
        $query->where('sent_at', '>=', $startTime);
        
        if ($endTime) {
            $query->where('sent_at', '<=', $endTime);
        }
        
        return $query;
    }

    /**
     * Mark notification as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'delivered_at' => now(),
        ]);
    }
}
