<?php

namespace App\Services\Ingestion;

use App\Models\Facility;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EventNormalizationService
{
    /**
     * Normalize event data from partner format to canonical format
     */
    public function normalizeEvent(array $eventData): array
    {
        $normalized = $eventData;

        // Normalize event code
        $normalized['event_code'] = $this->normalizeEventCode(
            $eventData['event_code'],
            $eventData['source'] ?? 'unknown'
        );

        // Resolve facility information
        if (!empty($eventData['facility_code'])) {
            $facility = $this->resolveFacility($eventData['facility_code']);
            $normalized['facility_id'] = $facility?->id;
            
            if ($facility) {
                $normalized['location'] = $normalized['location'] ?? $facility->name;
            }
        }

        // Normalize location data
        $normalized['location'] = $this->normalizeLocation($normalized['location'] ?? '');

        // Normalize description
        $normalized['description'] = $this->normalizeDescription(
            $eventData['description'] ?? '',
            $normalized['event_code']
        );

        Log::debug('Event normalized', [
            'original_event_code' => $eventData['event_code'],
            'normalized_event_code' => $normalized['event_code'],
            'facility_resolved' => !empty($normalized['facility_id']),
            'tracking_number' => $eventData['tracking_number']
        ]);

        return $normalized;
    }

    /**
     * Normalize event codes from partner-specific codes to canonical codes
     */
    private function normalizeEventCode(string $eventCode, string $source): string
    {
        // Cache the mapping for performance
        $mappingKey = "event_code_mapping:{$source}";
        $mapping = Cache::remember($mappingKey, 3600, function () use ($source) {
            return $this->getEventCodeMapping($source);
        });

        $normalizedCode = $mapping[strtoupper($eventCode)] ?? $eventCode;

        Log::debug('Event code normalized', [
            'source' => $source,
            'original' => $eventCode,
            'normalized' => $normalizedCode
        ]);

        return strtoupper($normalizedCode);
    }

    /**
     * Get event code mapping for a specific source/partner
     */
    private function getEventCodeMapping(string $source): array
    {
        // This would typically be stored in database or config
        // For now, using hardcoded mappings
        $mappings = [
            'webhook' => [
                'PU' => 'PICKED_UP',
                'IT' => 'IN_TRANSIT',
                'AH' => 'ARRIVED_AT_HUB',
                'DH' => 'DEPARTED_FROM_HUB',
                'OFD' => 'OUT_FOR_DELIVERY',
                'DEL' => 'DELIVERED',
                'EXC' => 'EXCEPTION',
                'RET' => 'RETURNED',
                'CUS' => 'CUSTOMS_CLEARANCE',
            ],
            'batch' => [
                'PICKUP' => 'PICKED_UP',
                'TRANSIT' => 'IN_TRANSIT',
                'HUB_IN' => 'ARRIVED_AT_HUB',
                'HUB_OUT' => 'DEPARTED_FROM_HUB',
                'DELIVERY' => 'OUT_FOR_DELIVERY',
                'COMPLETE' => 'DELIVERED',
                'PROBLEM' => 'EXCEPTION',
                'RETURN' => 'RETURNED',
                'CUSTOMS' => 'CUSTOMS_CLEARANCE',
            ],
            'handheld' => [
                'SCAN_PICKUP' => 'PICKED_UP',
                'SCAN_TRANSIT' => 'IN_TRANSIT',
                'SCAN_HUB_ARRIVAL' => 'ARRIVED_AT_HUB',
                'SCAN_HUB_DEPARTURE' => 'DEPARTED_FROM_HUB',
                'SCAN_DELIVERY' => 'OUT_FOR_DELIVERY',
                'SCAN_DELIVERED' => 'DELIVERED',
                'SCAN_EXCEPTION' => 'EXCEPTION',
                'SCAN_RETURN' => 'RETURNED',
            ],
            'partner_api' => [
                // Partner-specific mappings would go here
                'COLLECTED' => 'PICKED_UP',
                'MOVING' => 'IN_TRANSIT',
                'AT_DEPOT' => 'ARRIVED_AT_HUB',
                'LEFT_DEPOT' => 'DEPARTED_FROM_HUB',
                'DELIVERING' => 'OUT_FOR_DELIVERY',
                'DELIVERED' => 'DELIVERED',
                'FAILED' => 'EXCEPTION',
                'RETURNED' => 'RETURNED',
            ]
        ];

        return $mappings[$source] ?? [];
    }

    /**
     * Resolve facility by code
     */
    private function resolveFacility(string $facilityCode): ?Facility
    {
        $cacheKey = "facility:{$facilityCode}";
        
        return Cache::remember($cacheKey, 1800, function () use ($facilityCode) {
            return Facility::where('code', strtoupper($facilityCode))
                ->where('active', true)
                ->first();
        });
    }

    /**
     * Normalize location text
     */
    private function normalizeLocation(string $location): string
    {
        if (empty($location)) {
            return '';
        }

        // Clean up location text
        $location = trim($location);
        $location = preg_replace('/\s+/', ' ', $location); // Multiple spaces to single
        $location = ucwords(strtolower($location)); // Title case

        // Common location normalizations
        $replacements = [
            'Bkk' => 'Bangkok',
            'Cnx' => 'Chiang Mai',
            'Hkt' => 'Phuket',
            'Utp' => 'Udon Thani',
            'Nma' => 'Nakhon Ratchasima',
        ];

        foreach ($replacements as $search => $replace) {
            $location = str_ireplace($search, $replace, $location);
        }

        return $location;
    }

    /**
     * Normalize event description
     */
    private function normalizeDescription(string $description, string $eventCode): string
    {
        if (!empty($description)) {
            return trim($description);
        }

        // Generate default description based on event code
        $defaultDescriptions = [
            'PICKED_UP' => 'Package picked up from sender',
            'IN_TRANSIT' => 'Package in transit',
            'ARRIVED_AT_HUB' => 'Package arrived at sorting facility',
            'DEPARTED_FROM_HUB' => 'Package departed from sorting facility',
            'OUT_FOR_DELIVERY' => 'Package out for delivery',
            'DELIVERED' => 'Package delivered successfully',
            'EXCEPTION' => 'Delivery exception occurred',
            'RETURNED' => 'Package returned to sender',
            'CUSTOMS_CLEARANCE' => 'Package undergoing customs clearance',
        ];

        return $defaultDescriptions[$eventCode] ?? 'Package status updated';
    }

    /**
     * Validate normalized event data
     */
    public function validateNormalizedEvent(array $eventData): array
    {
        $errors = [];

        // Check required fields after normalization
        $requiredFields = ['event_code', 'tracking_number', 'event_time'];
        foreach ($requiredFields as $field) {
            if (empty($eventData[$field])) {
                $errors[] = "Missing required field after normalization: {$field}";
            }
        }

        // Validate event code is in canonical format
        $validEventCodes = [
            'CREATED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'IN_TRANSIT',
            'ARRIVED_AT_HUB', 'DEPARTED_FROM_HUB', 'OUT_FOR_DELIVERY',
            'DELIVERY_ATTEMPTED', 'DELIVERED', 'EXCEPTION', 'RETURNED',
            'CUSTOMS_CLEARANCE'
        ];

        if (!in_array($eventData['event_code'], $validEventCodes)) {
            $errors[] = "Invalid canonical event code: {$eventData['event_code']}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}