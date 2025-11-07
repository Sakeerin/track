<?php

namespace App\Services\Ingestion;

use App\Models\Event;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EventOrderingService
{
    /**
     * Handle out-of-order events and compute current status
     */
    public function processEventOrdering(Shipment $shipment, Event $newEvent): array
    {
        Log::info('Processing event ordering', [
            'shipment_id' => $shipment->id,
            'new_event_id' => $newEvent->id,
            'new_event_time' => $newEvent->event_time,
            'new_event_code' => $newEvent->event_code
        ]);

        // Get all events for this shipment ordered by event_time
        $allEvents = Event::where('shipment_id', $shipment->id)
            ->orderBy('event_time', 'desc')
            ->orderBy('created_at', 'desc') // Secondary sort for events with same timestamp
            ->get();

        // Find the chronologically latest event
        $latestEvent = $allEvents->first();
        
        // Determine if shipment status needs updating
        $statusChanged = false;
        $oldStatus = $shipment->current_status;
        $newStatus = $oldStatus;

        if ($latestEvent && $latestEvent->id === $newEvent->id) {
            // This new event is the chronologically latest
            $newStatus = $this->mapEventCodeToShipmentStatus($newEvent->event_code);
            $statusChanged = ($newStatus !== $oldStatus);
            
            if ($statusChanged) {
                $shipment->update([
                    'current_status' => $newStatus,
                    'current_location' => $newEvent->location,
                    'updated_at' => now()
                ]);
            }
        } else {
            // This is an out-of-order event, but we still need to check if it affects status
            $this->handleOutOfOrderEvent($shipment, $newEvent, $allEvents);
        }

        // Compute event sequence and detect anomalies
        $sequenceAnalysis = $this->analyzeEventSequence($allEvents);

        Log::info('Event ordering processed', [
            'shipment_id' => $shipment->id,
            'status_changed' => $statusChanged,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'is_latest_event' => $latestEvent && $latestEvent->id === $newEvent->id,
            'sequence_anomalies' => count($sequenceAnalysis['anomalies'])
        ]);

        return [
            'status_changed' => $statusChanged,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'is_latest_event' => $latestEvent && $latestEvent->id === $newEvent->id,
            'sequence_analysis' => $sequenceAnalysis
        ];
    }

    /**
     * Handle out-of-order events
     */
    private function handleOutOfOrderEvent(Shipment $shipment, Event $outOfOrderEvent, $allEvents): void
    {
        Log::info('Handling out-of-order event', [
            'shipment_id' => $shipment->id,
            'event_id' => $outOfOrderEvent->id,
            'event_time' => $outOfOrderEvent->event_time,
            'event_code' => $outOfOrderEvent->event_code
        ]);

        // Check if this out-of-order event represents a more significant status
        // For example, if we receive a "DELIVERED" event that's older than current events,
        // we might still want to update the status if current status is less significant
        
        $currentLatestEvent = $allEvents->first();
        $outOfOrderSignificance = $this->getEventSignificance($outOfOrderEvent->event_code);
        $currentSignificance = $this->getEventSignificance($currentLatestEvent->event_code);

        // If the out-of-order event is more significant and within reasonable time window
        $timeDifference = abs($outOfOrderEvent->event_time->diffInHours($currentLatestEvent->event_time));
        
        if ($outOfOrderSignificance > $currentSignificance && $timeDifference <= 24) {
            Log::warning('Out-of-order event has higher significance', [
                'shipment_id' => $shipment->id,
                'out_of_order_event' => $outOfOrderEvent->event_code,
                'out_of_order_significance' => $outOfOrderSignificance,
                'current_event' => $currentLatestEvent->event_code,
                'current_significance' => $currentSignificance,
                'time_difference_hours' => $timeDifference
            ]);

            // This could trigger additional business logic or alerts
            // For now, we just log it for investigation
        }
    }

    /**
     * Get event significance score for prioritization
     */
    private function getEventSignificance(string $eventCode): int
    {
        $significance = [
            'CREATED' => 1,
            'PICKUP_SCHEDULED' => 2,
            'PICKED_UP' => 3,
            'IN_TRANSIT' => 4,
            'ARRIVED_AT_HUB' => 5,
            'DEPARTED_FROM_HUB' => 6,
            'CUSTOMS_CLEARANCE' => 7,
            'OUT_FOR_DELIVERY' => 8,
            'DELIVERY_ATTEMPTED' => 9,
            'DELIVERED' => 10,
            'EXCEPTION' => 5, // Exceptions can happen at various stages
            'RETURNED' => 10, // Final state like delivered
        ];

        return $significance[$eventCode] ?? 0;
    }

    /**
     * Map event code to shipment status
     */
    private function mapEventCodeToShipmentStatus(string $eventCode): string
    {
        $statusMap = [
            'CREATED' => 'CREATED',
            'PICKUP_SCHEDULED' => 'PICKUP_SCHEDULED',
            'PICKED_UP' => 'IN_TRANSIT',
            'IN_TRANSIT' => 'IN_TRANSIT',
            'ARRIVED_AT_HUB' => 'AT_HUB',
            'DEPARTED_FROM_HUB' => 'IN_TRANSIT',
            'CUSTOMS_CLEARANCE' => 'CUSTOMS',
            'OUT_FOR_DELIVERY' => 'OUT_FOR_DELIVERY',
            'DELIVERY_ATTEMPTED' => 'DELIVERY_ATTEMPTED',
            'DELIVERED' => 'DELIVERED',
            'EXCEPTION' => 'EXCEPTION',
            'RETURNED' => 'RETURNED',
        ];

        return $statusMap[$eventCode] ?? 'IN_TRANSIT';
    }

    /**
     * Analyze event sequence for anomalies
     */
    private function analyzeEventSequence($events): array
    {
        $anomalies = [];
        $expectedSequence = $this->getExpectedEventSequence();
        
        $eventCodes = $events->pluck('event_code')->toArray();
        $eventTimes = $events->pluck('event_time')->toArray();

        // Check for logical sequence violations
        $anomalies = array_merge($anomalies, $this->detectSequenceViolations($eventCodes));
        
        // Check for time anomalies
        $anomalies = array_merge($anomalies, $this->detectTimeAnomalies($events));
        
        // Check for duplicate events (same code within short time window)
        $anomalies = array_merge($anomalies, $this->detectDuplicateEvents($events));

        return [
            'total_events' => count($events),
            'anomalies' => $anomalies,
            'sequence_score' => $this->calculateSequenceScore($eventCodes, $expectedSequence)
        ];
    }

    /**
     * Get expected event sequence patterns
     */
    private function getExpectedEventSequence(): array
    {
        return [
            'normal_delivery' => [
                'CREATED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'IN_TRANSIT',
                'ARRIVED_AT_HUB', 'DEPARTED_FROM_HUB', 'OUT_FOR_DELIVERY', 'DELIVERED'
            ],
            'with_customs' => [
                'CREATED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'IN_TRANSIT',
                'CUSTOMS_CLEARANCE', 'ARRIVED_AT_HUB', 'DEPARTED_FROM_HUB',
                'OUT_FOR_DELIVERY', 'DELIVERED'
            ],
            'with_exception' => [
                'CREATED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'IN_TRANSIT',
                'EXCEPTION', 'ARRIVED_AT_HUB', 'DEPARTED_FROM_HUB',
                'OUT_FOR_DELIVERY', 'DELIVERED'
            ]
        ];
    }

    /**
     * Detect logical sequence violations
     */
    private function detectSequenceViolations(array $eventCodes): array
    {
        $violations = [];
        
        // Check for impossible sequences
        $impossibleAfter = [
            'DELIVERED' => ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'], // Can't go back after delivery
            'RETURNED' => ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'], // Can't go back after return
        ];

        for ($i = 0; $i < count($eventCodes) - 1; $i++) {
            $currentEvent = $eventCodes[$i];
            $nextEvent = $eventCodes[$i + 1];
            
            if (isset($impossibleAfter[$currentEvent]) && 
                in_array($nextEvent, $impossibleAfter[$currentEvent])) {
                
                $violations[] = [
                    'type' => 'impossible_sequence',
                    'description' => "Event {$nextEvent} cannot occur after {$currentEvent}",
                    'current_event' => $currentEvent,
                    'next_event' => $nextEvent
                ];
            }
        }

        return $violations;
    }

    /**
     * Detect time-based anomalies
     */
    private function detectTimeAnomalies($events): array
    {
        $anomalies = [];
        
        foreach ($events as $index => $event) {
            // Check for events in the far future
            if ($event->event_time->gt(now()->addHours(2))) {
                $anomalies[] = [
                    'type' => 'future_event',
                    'description' => 'Event time is too far in the future',
                    'event_id' => $event->id,
                    'event_time' => $event->event_time,
                    'hours_in_future' => $event->event_time->diffInHours(now())
                ];
            }
            
            // Check for events too far in the past (more than 1 year)
            if ($event->event_time->lt(now()->subYear())) {
                $anomalies[] = [
                    'type' => 'very_old_event',
                    'description' => 'Event time is more than 1 year old',
                    'event_id' => $event->id,
                    'event_time' => $event->event_time,
                    'days_old' => $event->event_time->diffInDays(now())
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Detect duplicate events
     */
    private function detectDuplicateEvents($events): array
    {
        $duplicates = [];
        $eventGroups = $events->groupBy('event_code');
        
        foreach ($eventGroups as $eventCode => $groupedEvents) {
            if ($groupedEvents->count() > 1) {
                // Check if events are within 1 hour of each other
                $sortedEvents = $groupedEvents->sortBy('event_time');
                
                for ($i = 0; $i < $sortedEvents->count() - 1; $i++) {
                    $current = $sortedEvents->values()[$i];
                    $next = $sortedEvents->values()[$i + 1];
                    
                    $timeDiff = abs($current->event_time->diffInMinutes($next->event_time));
                    
                    if ($timeDiff <= 60) { // Within 1 hour
                        $duplicates[] = [
                            'type' => 'potential_duplicate',
                            'description' => "Multiple {$eventCode} events within 1 hour",
                            'event_code' => $eventCode,
                            'event_ids' => [$current->id, $next->id],
                            'time_difference_minutes' => $timeDiff
                        ];
                    }
                }
            }
        }

        return $duplicates;
    }

    /**
     * Calculate sequence score (0-100, higher is better)
     */
    private function calculateSequenceScore(array $actualSequence, array $expectedSequences): int
    {
        $bestScore = 0;
        
        foreach ($expectedSequences as $expectedSequence) {
            $score = $this->compareSequences($actualSequence, $expectedSequence);
            $bestScore = max($bestScore, $score);
        }
        
        return $bestScore;
    }

    /**
     * Compare actual sequence with expected sequence
     */
    private function compareSequences(array $actual, array $expected): int
    {
        if (empty($actual)) {
            return 0;
        }

        $matches = 0;
        $actualIndex = 0;
        
        foreach ($expected as $expectedEvent) {
            if ($actualIndex < count($actual) && $actual[$actualIndex] === $expectedEvent) {
                $matches++;
                $actualIndex++;
            }
        }
        
        return (int) (($matches / count($expected)) * 100);
    }
}