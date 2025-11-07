<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shipment_id',
        'event_id',
        'event_code',
        'event_time',
        'facility_id',
        'location_id',
        'description',
        'remarks',
        'raw_payload',
        'source',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'raw_payload' => 'array',
    ];

    /**
     * Get the shipment this event belongs to
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Get the facility where this event occurred
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the location facility for this event
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'location_id');
    }

    /**
     * Scope to order events chronologically
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('event_time', 'asc');
    }

    /**
     * Scope to order events reverse chronologically
     */
    public function scopeReverseChronological($query)
    {
        return $query->orderBy('event_time', 'desc');
    }

    /**
     * Scope to filter by event code
     */
    public function scopeWithEventCode($query, string $eventCode)
    {
        return $query->where('event_code', $eventCode);
    }

    /**
     * Scope to filter by source
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Check if this event is a duplicate based on event_id, shipment_id, and event_time
     */
    public static function isDuplicate(string $shipmentId, string $eventId, string $eventTime): bool
    {
        return self::where('shipment_id', $shipmentId)
            ->where('event_id', $eventId)
            ->where('event_time', $eventTime)
            ->exists();
    }

    /**
     * Get the display name for the event code
     */
    public function getEventDisplayName(string $locale = 'en'): string
    {
        $eventNames = [
            'en' => [
                'PICKUP' => 'Picked up',
                'IN_TRANSIT' => 'In transit',
                'AT_HUB' => 'At hub',
                'OUT_FOR_DELIVERY' => 'Out for delivery',
                'DELIVERED' => 'Delivered',
                'EXCEPTION' => 'Exception',
                'RETURNED' => 'Returned',
                'CUSTOMS' => 'Customs processing',
            ],
            'th' => [
                'PICKUP' => 'รับพัสดุแล้ว',
                'IN_TRANSIT' => 'อยู่ระหว่างขนส่ง',
                'AT_HUB' => 'อยู่ที่ศูนย์คัดแยก',
                'OUT_FOR_DELIVERY' => 'ออกส่ง',
                'DELIVERED' => 'ส่งแล้ว',
                'EXCEPTION' => 'มีปัญหา',
                'RETURNED' => 'ส่งคืน',
                'CUSTOMS' => 'อยู่ระหว่างตรวจศุลกากร',
            ],
        ];

        return $eventNames[$locale][$this->event_code] ?? $this->event_code;
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Update shipment status when a new event is created
        static::created(function ($event) {
            $event->shipment->updateCurrentStatus();
        });
    }
}