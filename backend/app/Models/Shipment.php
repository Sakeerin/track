<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tracking_number',
        'reference_number',
        'service_type',
        'origin_facility_id',
        'destination_facility_id',
        'current_status',
        'current_location_id',
        'estimated_delivery',
    ];

    protected $casts = [
        'estimated_delivery' => 'datetime',
    ];

    /**
     * Get the events for this shipment ordered by event time descending
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('event_time', 'desc');
    }

    /**
     * Get the latest event for this shipment
     */
    public function latestEvent(): HasMany
    {
        return $this->hasMany(Event::class)->latest('event_time')->limit(1);
    }

    /**
     * Get subscriptions for this shipment
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the origin facility
     */
    public function originFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'origin_facility_id');
    }

    /**
     * Get the destination facility
     */
    public function destinationFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'destination_facility_id');
    }

    /**
     * Get the current location facility
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'current_location_id');
    }

    /**
     * Scope to filter by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('current_status', $status);
    }

    /**
     * Scope to filter by service type
     */
    public function scopeWithServiceType($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * Update the current status based on the latest event
     */
    public function updateCurrentStatus(): void
    {
        $latestEvent = $this->events()->latest('event_time')->first();
        
        if ($latestEvent) {
            $this->update([
                'current_status' => $this->mapEventCodeToStatus($latestEvent->event_code),
                'current_location_id' => $latestEvent->facility_id ?? $latestEvent->location_id,
            ]);
        }
    }

    /**
     * Map event code to shipment status
     */
    private function mapEventCodeToStatus(string $eventCode): string
    {
        $statusMap = [
            'PICKUP' => 'picked_up',
            'IN_TRANSIT' => 'in_transit',
            'AT_HUB' => 'at_hub',
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            'EXCEPTION' => 'exception',
            'RETURNED' => 'returned',
        ];

        return $statusMap[$eventCode] ?? 'unknown';
    }

    /**
     * Check if shipment has exceptions
     */
    public function hasExceptions(): bool
    {
        return $this->events()->where('event_code', 'EXCEPTION')->exists();
    }

    /**
     * Get active subscriptions
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('active', true);
    }
}