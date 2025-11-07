<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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