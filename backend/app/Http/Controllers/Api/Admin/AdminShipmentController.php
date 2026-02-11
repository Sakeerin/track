<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Subscription;

class AdminShipmentController extends Controller
{
    /**
     * Search shipments with advanced filters
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'tracking_number' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|max:50',
            'service_type' => 'nullable|string|max:50',
            'facility_id' => 'nullable|uuid',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|in:tracking_number,created_at,updated_at,current_status,estimated_delivery',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        try {
            $query = Shipment::with(['originFacility', 'destinationFacility', 'currentLocation']);

            // Apply filters
            if ($request->filled('tracking_number')) {
                $query->where('tracking_number', 'like', '%' . $request->tracking_number . '%');
            }

            if ($request->filled('reference_number')) {
                $query->where('reference_number', 'like', '%' . $request->reference_number . '%');
            }

            if ($request->filled('status')) {
                $query->where('current_status', $request->status);
            }

            if ($request->filled('service_type')) {
                $query->where('service_type', $request->service_type);
            }

            if ($request->filled('facility_id')) {
                $query->where(function ($q) use ($request) {
                    $q->where('origin_facility_id', $request->facility_id)
                        ->orWhere('destination_facility_id', $request->facility_id)
                        ->orWhere('current_location_id', $request->facility_id);
                });
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            // Search by phone or email in subscriptions
            if ($request->filled('phone')) {
                $query->whereHas('subscriptions', function ($q) use ($request) {
                    $q->where('destination_hash', Subscription::hashContact($request->phone))
                        ->where('channel', 'sms');
                });
            }

            if ($request->filled('email')) {
                $query->whereHas('subscriptions', function ($q) use ($request) {
                    $q->where('destination_hash', Subscription::hashContact($request->email))
                        ->where('channel', 'email');
                });
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input('per_page', 20);
            $shipments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'shipments' => $shipments->items(),
                    'pagination' => [
                        'current_page' => $shipments->currentPage(),
                        'per_page' => $shipments->perPage(),
                        'total' => $shipments->total(),
                        'last_page' => $shipments->lastPage(),
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Admin shipment search failed', [
                'error' => $e->getMessage(),
                'filters' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Search failed',
                'error_code' => 'SEARCH_ERROR',
            ], 500);
        }
    }

    /**
     * Get detailed shipment information
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $shipment = Shipment::with([
                'originFacility',
                'destinationFacility',
                'currentLocation',
                'events' => function ($query) {
                    $query->orderBy('event_time', 'desc');
                },
                'events.facility',
                'subscriptions',
            ])->find($id);

            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Shipment not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            // Get raw event payloads if requested
            $includeRaw = $request->boolean('include_raw', false);
            
            $events = $shipment->events->map(function ($event) use ($includeRaw) {
                $data = [
                    'id' => $event->id,
                    'event_code' => $event->event_code,
                    'event_time' => $event->event_time?->toISOString(),
                    'description' => $event->description,
                    'facility' => $event->facility ? [
                        'id' => $event->facility->id,
                        'name' => $event->facility->name,
                        'code' => $event->facility->code,
                    ] : null,
                    'location' => $event->location,
                    'created_at' => $event->created_at?->toISOString(),
                ];

                if ($includeRaw) {
                    $data['raw_payload'] = $event->raw_payload;
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'shipment' => [
                        'id' => $shipment->id,
                        'tracking_number' => $shipment->tracking_number,
                        'reference_number' => $shipment->reference_number,
                        'service_type' => $shipment->service_type,
                        'current_status' => $shipment->current_status,
                        'estimated_delivery' => $shipment->estimated_delivery?->toISOString(),
                        'origin_facility' => $shipment->originFacility,
                        'destination_facility' => $shipment->destinationFacility,
                        'current_location' => $shipment->currentLocation,
                        'created_at' => $shipment->created_at?->toISOString(),
                        'updated_at' => $shipment->updated_at?->toISOString(),
                    ],
                    'events' => $events,
                    'subscriptions' => $shipment->subscriptions->map(function ($sub) {
                        return [
                            'id' => $sub->id,
                            'channel' => $sub->channel,
                            'contact_value' => $this->maskContactValue($sub->destination, $sub->channel),
                            'active' => $sub->active,
                            'events' => $sub->events,
                            'created_at' => $sub->created_at?->toISOString(),
                        ];
                    }),
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Admin shipment show failed', [
                'shipment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve shipment',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Add a manual event to a shipment
     */
    public function addEvent(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'event_code' => 'required|string|max:50',
            'event_time' => 'required|date',
            'description' => 'nullable|string|max:500',
            'facility_id' => 'nullable|uuid|exists:facilities,id',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $shipment = Shipment::find($id);

            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Shipment not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            $event = Event::create([
                'shipment_id' => $shipment->id,
                'event_id' => 'MANUAL-' . Str::uuid(),
                'tracking_number' => $shipment->tracking_number,
                'event_code' => $request->event_code,
                'event_time' => $request->event_time,
                'description' => $request->description,
                'facility_id' => $request->facility_id,
                'location' => $request->location,
                'raw_payload' => [
                    'source' => 'manual',
                    'created_by' => $request->user()->id,
                    'notes' => $request->notes,
                ],
            ]);

            // Update shipment status
            $shipment->updateCurrentStatus();

            // Invalidate cache
            Cache::forget("shipment:{$shipment->tracking_number}");

            // Audit log
            AuditLog::log(
                AuditLog::ACTION_MANUAL_EVENT,
                $request->user(),
                Shipment::class,
                $shipment->id,
                null,
                [
                    'event_id' => $event->id,
                    'event_code' => $request->event_code,
                    'event_time' => $request->event_time,
                ],
                ['notes' => $request->notes]
            );

            Log::info('Manual event added', [
                'shipment_id' => $shipment->id,
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'event_code' => $event->event_code,
                        'event_time' => $event->event_time?->toISOString(),
                        'description' => $event->description,
                    ],
                ],
                'message' => 'Event added successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to add manual event', [
                'shipment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to add event',
                'error_code' => 'CREATE_ERROR',
            ], 500);
        }
    }

    /**
     * Update/correct an existing event
     */
    public function updateEvent(Request $request, string $shipmentId, string $eventId): JsonResponse
    {
        $request->validate([
            'event_code' => 'nullable|string|max:50',
            'event_time' => 'nullable|date',
            'description' => 'nullable|string|max:500',
            'facility_id' => 'nullable|uuid|exists:facilities,id',
            'location' => 'nullable|string|max:255',
            'notes' => 'required|string|max:1000', // Reason for correction is required
        ]);

        try {
            $shipment = Shipment::find($shipmentId);

            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Shipment not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            $event = Event::where('id', $eventId)
                ->where('shipment_id', $shipmentId)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'error' => 'Event not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            $oldValues = $event->only(['event_code', 'event_time', 'description', 'facility_id', 'location']);

            // Update only provided fields
            $updateData = array_filter($request->only([
                'event_code',
                'event_time',
                'description',
                'facility_id',
                'location',
            ]), fn($value) => $value !== null);

            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No fields to update',
                    'error_code' => 'NO_CHANGES',
                ], 400);
            }

            $event->update($updateData);

            // Update shipment status
            $shipment->updateCurrentStatus();

            // Invalidate cache
            Cache::forget("shipment:{$shipment->tracking_number}");

            // Audit log
            AuditLog::log(
                AuditLog::ACTION_EVENT_CORRECTION,
                $request->user(),
                Event::class,
                $event->id,
                $oldValues,
                $updateData,
                [
                    'shipment_id' => $shipment->id,
                    'notes' => $request->notes,
                ]
            );

            Log::info('Event corrected', [
                'shipment_id' => $shipment->id,
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'changes' => $updateData,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'event_code' => $event->event_code,
                        'event_time' => $event->event_time?->toISOString(),
                        'description' => $event->description,
                    ],
                ],
                'message' => 'Event updated successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update event', [
                'shipment_id' => $shipmentId,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update event',
                'error_code' => 'UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Delete an event
     */
    public function deleteEvent(Request $request, string $shipmentId, string $eventId): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:1000', // Reason for deletion is required
        ]);

        try {
            $shipment = Shipment::find($shipmentId);

            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Shipment not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            $event = Event::where('id', $eventId)
                ->where('shipment_id', $shipmentId)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'error' => 'Event not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            $eventData = $event->toArray();
            $event->delete();

            // Update shipment status
            $shipment->updateCurrentStatus();

            // Invalidate cache
            Cache::forget("shipment:{$shipment->tracking_number}");

            // Audit log
            AuditLog::log(
                AuditLog::ACTION_DELETE,
                $request->user(),
                Event::class,
                $eventId,
                $eventData,
                null,
                [
                    'shipment_id' => $shipment->id,
                    'notes' => $request->notes,
                ]
            );

            Log::info('Event deleted', [
                'shipment_id' => $shipment->id,
                'event_id' => $eventId,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete event', [
                'shipment_id' => $shipmentId,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete event',
                'error_code' => 'DELETE_ERROR',
            ], 500);
        }
    }

    /**
     * Export shipments to CSV
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'tracking_numbers' => 'nullable|array|max:1000',
            'tracking_numbers.*' => 'string|max:50',
            'status' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        try {
            $query = Shipment::with(['originFacility', 'destinationFacility']);

            if ($request->filled('tracking_numbers')) {
                $query->whereIn('tracking_number', $request->tracking_numbers);
            }

            if ($request->filled('status')) {
                $query->where('current_status', $request->status);
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $shipments = $query->limit(10000)->get();

            // Audit log
            AuditLog::log(
                AuditLog::ACTION_EXPORT,
                $request->user(),
                Shipment::class,
                null,
                null,
                null,
                [
                    'count' => $shipments->count(),
                    'filters' => $request->only(['tracking_numbers', 'status', 'date_from', 'date_to']),
                ]
            );

            $data = $shipments->map(function ($s) {
                return [
                    'tracking_number' => $s->tracking_number,
                    'reference_number' => $s->reference_number,
                    'service_type' => $s->service_type,
                    'current_status' => $s->current_status,
                    'origin' => $s->originFacility?->name,
                    'destination' => $s->destinationFacility?->name,
                    'estimated_delivery' => $s->estimated_delivery?->toISOString(),
                    'created_at' => $s->created_at?->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Export failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Export failed',
                'error_code' => 'EXPORT_ERROR',
            ], 500);
        }
    }

    /**
     * Mask contact value for privacy
     */
    private function maskContactValue(string $value, string $channel): string
    {
        if ($channel === 'email') {
            $parts = explode('@', $value);
            if (count($parts) === 2) {
                $name = $parts[0];
                $domain = $parts[1];
                $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
                return $maskedName . '@' . $domain;
            }
        }

        if ($channel === 'sms') {
            return substr($value, 0, 3) . str_repeat('*', max(0, strlen($value) - 5)) . substr($value, -2);
        }

        return $value;
    }
}
