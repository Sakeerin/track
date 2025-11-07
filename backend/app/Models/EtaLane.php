<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtaLane extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'origin_facility_id',
        'destination_facility_id',
        'service_type',
        'base_hours',
        'min_hours',
        'max_hours',
        'day_adjustments',
        'active',
    ];

    protected $casts = [
        'base_hours' => 'integer',
        'min_hours' => 'integer',
        'max_hours' => 'integer',
        'day_adjustments' => 'array',
        'active' => 'boolean',
    ];

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
     * Scope to get only active lanes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get lanes by service type
     */
    public function scopeForService($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * Find lane for specific origin, destination, and service type
     */
    public static function findLane(string $originFacilityId, string $destinationFacilityId, string $serviceType): ?self
    {
        return self::active()
            ->where('origin_facility_id', $originFacilityId)
            ->where('destination_facility_id', $destinationFacilityId)
            ->where('service_type', $serviceType)
            ->first();
    }

    /**
     * Get the base delivery time adjusted for day of week
     */
    public function getAdjustedBaseHours(\DateTime $date): int
    {
        $dayOfWeek = strtolower($date->format('l')); // monday, tuesday, etc.
        
        if ($this->day_adjustments && isset($this->day_adjustments[$dayOfWeek])) {
            return $this->base_hours + $this->day_adjustments[$dayOfWeek];
        }

        return $this->base_hours;
    }

    /**
     * Calculate ETA based on pickup time and day adjustments
     */
    public function calculateEta(\DateTime $pickupTime): \DateTime
    {
        $eta = clone $pickupTime;
        $adjustedHours = $this->getAdjustedBaseHours($pickupTime);
        
        $eta->modify(sprintf('+%d hours', $adjustedHours));
        
        // Ensure ETA is within min/max bounds if specified
        if ($this->min_hours) {
            $minEta = clone $pickupTime;
            $minEta->modify(sprintf('+%d hours', $this->min_hours));
            if ($eta < $minEta) {
                $eta = $minEta;
            }
        }
        
        if ($this->max_hours) {
            $maxEta = clone $pickupTime;
            $maxEta->modify(sprintf('+%d hours', $this->max_hours));
            if ($eta > $maxEta) {
                $eta = $maxEta;
            }
        }
        
        return $eta;
    }
}