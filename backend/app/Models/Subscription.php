<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shipment_id',
        'channel',
        'destination',
        'events',
        'active',
        'consent_given',
        'consent_ip',
        'consent_at',
        'unsubscribe_token',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'consent_given' => 'boolean',
        'consent_at' => 'datetime',
    ];

    /**
     * Get the shipment this subscription belongs to
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Get notification logs for this subscription
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Scope to get active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get subscriptions with consent
     */
    public function scopeWithConsent($query)
    {
        return $query->where('consent_given', true);
    }

    /**
     * Scope to filter by channel
     */
    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Check if this subscription should be notified for a given event code
     */
    public function shouldNotifyForEvent(string $eventCode): bool
    {
        return $this->active && 
               $this->consent_given && 
               in_array($eventCode, $this->events ?? []);
    }

    /**
     * Generate and set unsubscribe token
     */
    public function generateUnsubscribeToken(): string
    {
        $token = Str::random(100);
        $this->update(['unsubscribe_token' => $token]);
        return $token;
    }

    /**
     * Unsubscribe using token
     */
    public static function unsubscribeByToken(string $token): bool
    {
        $subscription = self::where('unsubscribe_token', $token)->first();
        
        if ($subscription) {
            $subscription->update(['active' => false]);
            return true;
        }
        
        return false;
    }

    /**
     * Get last notification time for this subscription
     */
    public function getLastNotificationTime(): ?string
    {
        $lastLog = $this->notificationLogs()
            ->where('status', 'sent')
            ->orderBy('sent_at', 'desc')
            ->first();
        
        return $lastLog ? $lastLog->sent_at : null;
    }

    /**
     * Check if subscription should be throttled
     * Max 1 notification per 2 hours unless critical event
     */
    public function shouldThrottle(string $eventCode): bool
    {
        $criticalEvents = ['DeliveryAttempted', 'Delivered', 'ExceptionRaised', 'Returned'];
        
        // Don't throttle critical events
        if (in_array($eventCode, $criticalEvents)) {
            return false;
        }

        // Check last notification time
        $lastNotification = $this->notificationLogs()
            ->where('status', 'sent')
            ->orderBy('sent_at', 'desc')
            ->first();

        if (!$lastNotification) {
            return false;
        }

        // Throttle if last notification was within 2 hours
        $twoHoursAgo = now()->subHours(2);
        return $lastNotification->sent_at > $twoHoursAgo;
    }

    /**
     * Get notification statistics for this subscription
     */
    public function getStatistics(): array
    {
        $logs = $this->notificationLogs;

        return [
            'total_sent' => $logs->where('status', 'sent')->count(),
            'total_failed' => $logs->where('status', 'failed')->count(),
            'total_throttled' => $logs->where('status', 'throttled')->count(),
            'last_sent_at' => $this->getLastNotificationTime(),
            'delivery_rate' => $logs->where('status', 'sent')->count() > 0 
                ? ($logs->whereNotNull('delivered_at')->count() / $logs->where('status', 'sent')->count()) * 100 
                : 0,
        ];
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Generate unsubscribe token when creating subscription
        static::creating(function ($subscription) {
            if (empty($subscription->unsubscribe_token)) {
                $subscription->unsubscribe_token = Str::random(100);
            }
        });
    }
}