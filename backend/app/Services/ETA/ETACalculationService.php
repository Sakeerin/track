<?php

namespace App\Services\ETA;

use App\Models\EtaLane;
use App\Models\EtaRule;
use App\Models\Event;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ETACalculationService
{
    /**
     * Calculate ETA for a shipment
     */
    public function calculateETA(Shipment $shipment, ?\DateTime $pickupTime = null): ?\DateTime
    {
        // Use pickup time from the first pickup event if not provided
        if (!$pickupTime) {
            $pickupEvent = $shipment->events()
                ->where('event_code', 'PICKUP')
                ->orderBy('event_time', 'asc')
                ->first();
            
            if (!$pickupEvent) {
                return null; // Cannot calculate ETA without pickup time
            }
            
            $pickupTime = $pickupEvent->event_time;
        }

        // Find the appropriate lane
        $lane = $this->findLane($shipment);
        if (!$lane) {
            return null; // No lane configuration found
        }

        // Calculate base ETA from lane
        $eta = $lane->calculateEta($pickupTime);

        // Apply ETA rules for adjustments
        $eta = $this->applyETARules($eta, $shipment, $pickupTime);

        return $eta;
    }

    /**
     * Recalculate ETA for a shipment based on latest events
     */
    public function recalculateETA(Shipment $shipment): ?\DateTime
    {
        $eta = $this->calculateETA($shipment);
        
        if ($eta) {
            $shipment->update(['estimated_delivery' => $eta]);
        }
        
        return $eta;
    }

    /**
     * Check if ETA should be recalculated based on event
     */
    public function shouldRecalculateETA(Event $event): bool
    {
        $triggerEvents = [
            'PICKUP',
            'AT_HUB',
            'OUT_FOR_DELIVERY',
            'EXCEPTION',
            'CUSTOMS',
        ];

        return in_array($event->event_code, $triggerEvents);
    }

    /**
     * Find the appropriate lane for a shipment
     */
    private function findLane(Shipment $shipment): ?EtaLane
    {
        if (!$shipment->origin_facility_id || !$shipment->destination_facility_id) {
            return null;
        }

        return EtaLane::findLane(
            $shipment->origin_facility_id,
            $shipment->destination_facility_id,
            $shipment->service_type
        );
    }

    /**
     * Apply ETA rules to adjust the calculated ETA
     */
    private function applyETARules(\DateTime $eta, Shipment $shipment, \DateTime $pickupTime): \DateTime
    {
        $context = $this->buildRuleContext($shipment, $pickupTime, $eta);
        
        $rules = EtaRule::active()
            ->byPriority()
            ->get();

        $adjustedEta = clone $eta;

        foreach ($rules as $rule) {
            if ($rule->appliesTo($context)) {
                $adjustedEta = $rule->applyAdjustments($adjustedEta, $context);
                
                // Update context with new ETA for subsequent rules
                $context['eta'] = $adjustedEta;
            }
        }

        return $adjustedEta;
    }

    /**
     * Build context array for rule evaluation
     */
    private function buildRuleContext(Shipment $shipment, \DateTime $pickupTime, \DateTime $eta): array
    {
        $now = new \DateTime();
        $pickupCarbon = Carbon::instance($pickupTime);
        $etaCarbon = Carbon::instance($eta);

        return [
            'service_type' => $shipment->service_type,
            'current_status' => $shipment->current_status,
            'pickup_day_of_week' => $pickupCarbon->dayOfWeek, // 0 = Sunday, 1 = Monday, etc.
            'pickup_hour' => (int) $pickupCarbon->format('H'),
            'eta_day_of_week' => $etaCarbon->dayOfWeek,
            'is_weekend_pickup' => $pickupCarbon->isWeekend(),
            'is_weekend_delivery' => $etaCarbon->isWeekend(),
            'is_holiday_pickup' => $this->isHoliday($pickupCarbon),
            'is_holiday_delivery' => $this->isHoliday($etaCarbon),
            'has_exceptions' => $shipment->hasExceptions(),
            'origin_facility_type' => $shipment->originFacility?->facility_type,
            'destination_facility_type' => $shipment->destinationFacility?->facility_type,
            'pickup_time' => $pickupTime,
            'eta' => $eta,
            'current_time' => $now,
        ];
    }

    /**
     * Check if a date is a holiday (simplified implementation)
     * In production, this would check against a holidays table or external service
     */
    private function isHoliday(Carbon $date): bool
    {
        // Thai public holidays (simplified - would be better to use a holidays table)
        $holidays = [
            '01-01', // New Year's Day
            '02-14', // Valentine's Day (not official but affects business)
            '04-06', // Chakri Day
            '04-13', // Songkran
            '04-14', // Songkran
            '04-15', // Songkran
            '05-01', // Labour Day
            '05-04', // Coronation Day
            '07-28', // King's Birthday
            '08-12', // Mother's Day
            '10-13', // King Bhumibol Memorial Day
            '10-23', // Chulalongkorn Day
            '12-05', // Father's Day
            '12-10', // Constitution Day
            '12-31', // New Year's Eve
        ];

        $dateString = $date->format('m-d');
        return in_array($dateString, $holidays);
    }

    /**
     * Get all applicable rules for a shipment
     */
    public function getApplicableRules(Shipment $shipment, ?\DateTime $pickupTime = null): Collection
    {
        if (!$pickupTime) {
            $pickupEvent = $shipment->events()
                ->where('event_code', 'PICKUP')
                ->orderBy('event_time', 'asc')
                ->first();
            
            if (!$pickupEvent) {
                return collect();
            }
            
            $pickupTime = $pickupEvent->event_time;
        }

        $eta = $this->calculateETA($shipment, $pickupTime);
        if (!$eta) {
            return collect();
        }

        $context = $this->buildRuleContext($shipment, $pickupTime, $eta);
        
        return EtaRule::active()
            ->byPriority()
            ->get()
            ->filter(function ($rule) use ($context) {
                return $rule->appliesTo($context);
            });
    }
}