<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'name_th',
        'facility_type',
        'latitude',
        'longitude',
        'address',
        'timezone',
        'active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'active' => 'boolean',
    ];

    /**
     * Get shipments that originate from this facility
     */
    public function originShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'origin_facility_id');
    }

    /**
     * Get shipments that are destined for this facility
     */
    public function destinationShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'destination_facility_id');
    }

    /**
     * Get shipments currently at this facility
     */
    public function currentShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'current_location_id');
    }

    /**
     * Get events that occurred at this facility
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'facility_id');
    }

    /**
     * Get events where this facility is the location
     */
    public function locationEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'location_id');
    }

    /**
     * Scope to get only active facilities
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get facilities by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('facility_type', $type);
    }
}