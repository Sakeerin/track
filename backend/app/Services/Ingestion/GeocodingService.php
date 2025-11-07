<?php

namespace App\Services\Ingestion;

use App\Models\Facility;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const GEOCODING_API_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * Resolve facility location by code or name
     */
    public function resolveFacilityLocation(string $facilityCode, ?string $locationName = null): ?array
    {
        // First try to find existing facility by code
        $facility = $this->findFacilityByCode($facilityCode);
        
        if ($facility && $facility->latitude && $facility->longitude) {
            return [
                'facility_id' => $facility->id,
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
                'address' => $facility->address,
                'source' => 'database'
            ];
        }

        // If no facility found or no coordinates, try geocoding
        if ($locationName) {
            return $this->geocodeLocation($locationName, $facilityCode);
        }

        return null;
    }

    /**
     * Geocode a location name to coordinates
     */
    public function geocodeLocation(string $locationName, ?string $facilityCode = null): ?array
    {
        $cacheKey = 'geocode:' . md5(strtolower($locationName));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($locationName, $facilityCode) {
            try {
                // Clean and prepare location name for geocoding
                $cleanLocation = $this->cleanLocationName($locationName);
                
                Log::info('Geocoding location', [
                    'original' => $locationName,
                    'cleaned' => $cleanLocation,
                    'facility_code' => $facilityCode
                ]);

                // Try different geocoding strategies
                $result = $this->tryGeocodingStrategies($cleanLocation);
                
                if ($result) {
                    // Store geocoded result as a new facility if we have a facility code
                    if ($facilityCode && !$this->findFacilityByCode($facilityCode)) {
                        $this->createFacilityFromGeocode($facilityCode, $locationName, $result);
                    }
                    
                    $result['source'] = 'geocoded';
                    return $result;
                }

                Log::warning('Failed to geocode location', [
                    'location' => $locationName,
                    'facility_code' => $facilityCode
                ]);

                return null;

            } catch (\Exception $e) {
                Log::error('Geocoding error', [
                    'location' => $locationName,
                    'error' => $e->getMessage()
                ]);
                
                return null;
            }
        });
    }

    /**
     * Try multiple geocoding strategies
     */
    private function tryGeocodingStrategies(string $location): ?array
    {
        $strategies = [
            // Strategy 1: Exact location with Thailand
            $location . ', Thailand',
            // Strategy 2: Just the location
            $location,
            // Strategy 3: Extract city name if it looks like "City Hub" or "City Facility"
            $this->extractCityName($location) . ', Thailand',
        ];

        foreach ($strategies as $searchTerm) {
            if (empty($searchTerm) || $searchTerm === ', Thailand') {
                continue;
            }

            $result = $this->callGeocodingApi($searchTerm);
            if ($result) {
                Log::info('Geocoding successful', [
                    'strategy' => $searchTerm,
                    'result' => $result
                ]);
                return $result;
            }
        }

        return null;
    }

    /**
     * Call external geocoding API
     */
    private function callGeocodingApi(string $query): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'ParcelTrackingSystem/1.0'
                ])
                ->get(self::GEOCODING_API_URL, [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'th', // Limit to Thailand
                    'addressdetails' => 1
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data) && isset($data[0])) {
                    $result = $data[0];
                    
                    return [
                        'latitude' => (float) $result['lat'],
                        'longitude' => (float) $result['lon'],
                        'address' => $result['display_name'] ?? $query,
                        'raw_response' => $result
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('Geocoding API call failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Clean location name for better geocoding results
     */
    private function cleanLocationName(string $location): string
    {
        // Remove common facility suffixes
        $suffixes = [
            'Hub', 'Facility', 'Center', 'Centre', 'Depot', 'Terminal',
            'Sorting Center', 'Distribution Center', 'Warehouse'
        ];

        $cleaned = $location;
        foreach ($suffixes as $suffix) {
            $cleaned = preg_replace('/\s+' . preg_quote($suffix, '/') . '\s*$/i', '', $cleaned);
        }

        // Clean up extra spaces and normalize
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));
        
        return $cleaned;
    }

    /**
     * Extract city name from facility name
     */
    private function extractCityName(string $location): string
    {
        // Common patterns: "Bangkok Hub", "Chiang Mai Facility", etc.
        if (preg_match('/^([A-Za-z\s]+?)\s+(Hub|Facility|Center|Centre|Depot|Terminal)/i', $location, $matches)) {
            return trim($matches[1]);
        }

        // If no pattern matches, return the cleaned location
        return $this->cleanLocationName($location);
    }

    /**
     * Find facility by code
     */
    private function findFacilityByCode(string $code): ?Facility
    {
        return Cache::remember(
            'facility_by_code:' . $code,
            1800, // 30 minutes
            fn() => Facility::where('code', strtoupper($code))->first()
        );
    }

    /**
     * Create a new facility from geocoding result
     */
    private function createFacilityFromGeocode(string $code, string $name, array $geocodeResult): Facility
    {
        try {
            $facility = Facility::create([
                'code' => strtoupper($code),
                'name' => $name,
                'facility_type' => 'HUB', // Default type
                'latitude' => $geocodeResult['latitude'],
                'longitude' => $geocodeResult['longitude'],
                'address' => $geocodeResult['address'],
                'active' => true,
            ]);

            Log::info('Created facility from geocoding', [
                'facility_id' => $facility->id,
                'code' => $code,
                'name' => $name,
                'coordinates' => [$geocodeResult['latitude'], $geocodeResult['longitude']]
            ]);

            // Clear cache for this facility code
            Cache::forget('facility_by_code:' . $code);

            return $facility;

        } catch (\Exception $e) {
            Log::error('Failed to create facility from geocoding', [
                'code' => $code,
                'name' => $name,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Reverse geocode coordinates to address
     */
    public function reverseGeocode(float $latitude, float $longitude): ?string
    {
        $cacheKey = 'reverse_geocode:' . md5("{$latitude},{$longitude}");
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($latitude, $longitude) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'ParcelTrackingSystem/1.0'
                    ])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'format' => 'json',
                        'addressdetails' => 1
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['display_name'] ?? null;
                }

                return null;

            } catch (\Exception $e) {
                Log::warning('Reverse geocoding failed', [
                    'coordinates' => [$latitude, $longitude],
                    'error' => $e->getMessage()
                ]);
                
                return null;
            }
        });
    }

    /**
     * Get distance between two coordinates (in kilometers)
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}