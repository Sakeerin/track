<?php

namespace App\Services\Tracking;

use App\Models\Shipment;
use App\Models\Event;
use Carbon\Carbon;

class ShipmentFormatter
{
    /**
     * Progress milestone mapping
     */
    private const MILESTONES = [
        'created' => ['weight' => 0, 'label' => ['en' => 'Created', 'th' => 'สร้างรายการ']],
        'picked_up' => ['weight' => 20, 'label' => ['en' => 'Picked up', 'th' => 'รับพัสดุแล้ว']],
        'in_transit' => ['weight' => 40, 'label' => ['en' => 'In transit', 'th' => 'อยู่ระหว่างขนส่ง']],
        'at_hub' => ['weight' => 60, 'label' => ['en' => 'At hub', 'th' => 'อยู่ที่ศูนย์คัดแยก']],
        'out_for_delivery' => ['weight' => 80, 'label' => ['en' => 'Out for delivery', 'th' => 'ออกส่ง']],
        'delivered' => ['weight' => 100, 'label' => ['en' => 'Delivered', 'th' => 'ส่งแล้ว']],
    ];

    /**
     * Exception event codes that require special handling
     */
    private const EXCEPTION_CODES = [
        'EXCEPTION',
        'ADDRESS_ISSUE',
        'CUSTOMS_HOLD',
        'DELIVERY_FAILED',
        'DAMAGED',
        'LOST',
        'REFUSED',
    ];

    /**
     * Format shipment data with enhanced timeline and progress information
     */
    public function formatShipmentData(Shipment $shipment): array
    {
        $events = $shipment->events;
        $timeline = $this->formatTimeline($events);
        $progress = $this->calculateProgress($shipment->current_status, $events);
        $exceptions = $this->detectExceptions($events);

        return [
            'tracking_number' => $shipment->tracking_number,
            'reference_number' => $shipment->reference_number,
            'service_type' => $shipment->service_type,
            'current_status' => $shipment->current_status,
            'estimated_delivery' => $shipment->estimated_delivery?->toISOString(),
            'origin' => $this->formatLocationData($shipment->originFacility),
            'destination' => $this->formatLocationData($shipment->destinationFacility),
            'current_location' => $this->formatLocationData($shipment->currentLocation),
            'progress' => $progress,
            'timeline' => $timeline,
            'exceptions' => $exceptions,
            'map_data' => $this->formatMapData($events, $shipment),
            'created_at' => $shipment->created_at->toISOString(),
            'updated_at' => $shipment->updated_at->toISOString(),
        ];
    }

    /**
     * Format timeline data with enhanced event information
     */
    public function formatTimeline($events): array
    {
        return $events->map(function ($event) {
            return [
                'id' => $event->id,
                'event_id' => $event->event_id,
                'event_code' => $event->event_code,
                'event_time' => $event->event_time->toISOString(),
                'event_time_local' => $event->event_time->setTimezone('Asia/Bangkok')->toISOString(),
                'description' => $event->description,
                'remarks' => $event->remarks,
                'source' => $event->source,
                'location' => $this->formatLocationData($event->facility ?: $event->location),
                'display_name' => [
                    'en' => $event->getEventDisplayName('en'),
                    'th' => $event->getEventDisplayName('th'),
                ],
                'is_exception' => in_array($event->event_code, self::EXCEPTION_CODES),
                'time_ago' => $this->formatTimeAgo($event->event_time),
            ];
        })->toArray();
    }

    /**
     * Calculate progress milestones
     */
    private function calculateProgress(string $currentStatus, $events): array
    {
        $currentWeight = self::MILESTONES[$currentStatus]['weight'] ?? 0;
        
        $milestones = [];
        foreach (self::MILESTONES as $status => $milestone) {
            $isCompleted = $milestone['weight'] <= $currentWeight;
            $isCurrent = $status === $currentStatus;
            
            // Find the event that triggered this milestone
            $triggerEvent = null;
            if ($isCompleted) {
                $triggerEvent = $events->first(function ($event) use ($status) {
                    return $this->mapEventCodeToStatus($event->event_code) === $status;
                });
            }

            $milestones[] = [
                'status' => $status,
                'label' => $milestone['label'],
                'weight' => $milestone['weight'],
                'is_completed' => $isCompleted,
                'is_current' => $isCurrent,
                'completed_at' => $triggerEvent?->event_time?->toISOString(),
                'location' => $triggerEvent ? $this->formatLocationData($triggerEvent->facility ?: $triggerEvent->location) : null,
            ];
        }

        return [
            'milestones' => $milestones,
            'current_weight' => $currentWeight,
            'percentage' => $currentWeight,
            'status_label' => self::MILESTONES[$currentStatus]['label'] ?? ['en' => $currentStatus, 'th' => $currentStatus],
        ];
    }

    /**
     * Detect and format exceptions
     */
    private function detectExceptions($events): array
    {
        $exceptions = $events->filter(function ($event) {
            return in_array($event->event_code, self::EXCEPTION_CODES);
        });

        return $exceptions->map(function ($event) use ($events) {
            return [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_time' => $event->event_time->toISOString(),
                'description' => $event->description,
                'remarks' => $event->remarks,
                'location' => $this->formatLocationData($event->facility ?: $event->location),
                'severity' => $this->getExceptionSeverity($event->event_code),
                'guidance' => $this->getExceptionGuidance($event->event_code),
                'is_resolved' => $this->isExceptionResolved($event, $events),
            ];
        })->values()->toArray();
    }

    /**
     * Format location data for map integration
     */
    private function formatLocationData($facility): ?array
    {
        if (!$facility) {
            return null;
        }

        return [
            'id' => $facility->id,
            'code' => $facility->code,
            'name' => $facility->name,
            'name_th' => $facility->name_th,
            'facility_type' => $facility->facility_type,
            'coordinates' => [
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
            ],
            'address' => $facility->address,
            'timezone' => $facility->timezone,
        ];
    }

    /**
     * Format map data for visualization
     */
    private function formatMapData($events, Shipment $shipment): array
    {
        $locations = [];
        $route = [];

        // Collect unique locations from events
        foreach ($events as $event) {
            $facility = $event->facility ?: $event->location;
            if ($facility && $facility->latitude && $facility->longitude) {
                $locationKey = $facility->id;
                if (!isset($locations[$locationKey])) {
                    $locations[$locationKey] = [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'name_th' => $facility->name_th,
                        'coordinates' => [
                            'latitude' => (float) $facility->latitude,
                            'longitude' => (float) $facility->longitude,
                        ],
                        'facility_type' => $facility->facility_type,
                        'events' => [],
                    ];
                }
                
                $locations[$locationKey]['events'][] = [
                    'event_code' => $event->event_code,
                    'event_time' => $event->event_time->toISOString(),
                    'description' => $event->description,
                ];
            }
        }

        // Create route polyline (chronological order)
        $chronologicalEvents = $events->sortBy('event_time');
        foreach ($chronologicalEvents as $event) {
            $facility = $event->facility ?: $event->location;
            if ($facility && $facility->latitude && $facility->longitude) {
                $route[] = [
                    'latitude' => (float) $facility->latitude,
                    'longitude' => (float) $facility->longitude,
                    'timestamp' => $event->event_time->toISOString(),
                ];
            }
        }

        return [
            'locations' => array_values($locations),
            'route' => $route,
            'current_location' => $this->formatLocationData($shipment->currentLocation),
            'origin' => $this->formatLocationData($shipment->originFacility),
            'destination' => $this->formatLocationData($shipment->destinationFacility),
        ];
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
     * Get exception severity level
     */
    private function getExceptionSeverity(string $eventCode): string
    {
        $severityMap = [
            'EXCEPTION' => 'medium',
            'ADDRESS_ISSUE' => 'medium',
            'CUSTOMS_HOLD' => 'low',
            'DELIVERY_FAILED' => 'high',
            'DAMAGED' => 'high',
            'LOST' => 'critical',
            'REFUSED' => 'medium',
        ];

        return $severityMap[$eventCode] ?? 'low';
    }

    /**
     * Get exception guidance message
     */
    private function getExceptionGuidance(string $eventCode): array
    {
        $guidanceMap = [
            'EXCEPTION' => [
                'en' => 'Please contact customer service for assistance.',
                'th' => 'กรุณาติดต่อฝ่ายบริการลูกค้าเพื่อขอความช่วยเหลือ',
            ],
            'ADDRESS_ISSUE' => [
                'en' => 'Please verify the delivery address is correct.',
                'th' => 'กรุณาตรวจสอบที่อยู่จัดส่งให้ถูกต้อง',
            ],
            'CUSTOMS_HOLD' => [
                'en' => 'Your package is being processed by customs. This may take 1-3 business days.',
                'th' => 'พัสดุของคุณอยู่ระหว่างการตรวจสอบของศุลกากร อาจใช้เวลา 1-3 วันทำการ',
            ],
            'DELIVERY_FAILED' => [
                'en' => 'Delivery attempt failed. We will try again on the next business day.',
                'th' => 'การจัดส่งไม่สำเร็จ เราจะพยายามจัดส่งอีกครั้งในวันทำการถัดไป',
            ],
            'DAMAGED' => [
                'en' => 'Package appears to be damaged. Please contact customer service immediately.',
                'th' => 'พัสดุอาจเสียหาย กรุณาติดต่อฝ่ายบริการลูกค้าทันที',
            ],
            'LOST' => [
                'en' => 'Package cannot be located. Please contact customer service for investigation.',
                'th' => 'ไม่สามารถติดตามพัสดุได้ กรุณาติดต่อฝ่ายบริการลูกค้าเพื่อตรวจสอบ',
            ],
            'REFUSED' => [
                'en' => 'Package was refused by recipient. It will be returned to sender.',
                'th' => 'ผู้รับปฏิเสธรับพัสดุ พัสดุจะถูกส่งคืนผู้ส่ง',
            ],
        ];

        return $guidanceMap[$eventCode] ?? [
            'en' => 'Please contact customer service if you have questions.',
            'th' => 'กรุณาติดต่อฝ่ายบริการลูกค้าหากมีข้อสงสัย',
        ];
    }

    /**
     * Check if exception is resolved
     */
    private function isExceptionResolved(Event $exceptionEvent, $allEvents): bool
    {
        // Look for events after the exception that indicate resolution
        $resolutionCodes = ['DELIVERED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'];
        
        return $allEvents->filter(function ($event) use ($exceptionEvent, $resolutionCodes) {
            return $event->event_time > $exceptionEvent->event_time 
                && in_array($event->event_code, $resolutionCodes);
        })->isNotEmpty();
    }

    /**
     * Format time ago string
     */
    private function formatTimeAgo(Carbon $eventTime): array
    {
        $now = Carbon::now();
        $diffInMinutes = $eventTime->diffInMinutes($now);
        
        if ($diffInMinutes < 60) {
            return [
                'en' => $diffInMinutes . ' minutes ago',
                'th' => $diffInMinutes . ' นาทีที่แล้ว',
            ];
        }
        
        $diffInHours = $eventTime->diffInHours($now);
        if ($diffInHours < 24) {
            return [
                'en' => $diffInHours . ' hours ago',
                'th' => $diffInHours . ' ชั่วโมงที่แล้ว',
            ];
        }
        
        $diffInDays = $eventTime->diffInDays($now);
        return [
            'en' => $diffInDays . ' days ago',
            'th' => $diffInDays . ' วันที่แล้ว',
        ];
    }
}