<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminConfigController extends Controller
{
    /**
     * Get all facilities
     */
    public function facilities(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $query = Facility::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $perPage = $request->input('per_page', 50);
            $facilities = $query->orderBy('name')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'facilities' => $facilities->items(),
                    'pagination' => [
                        'current_page' => $facilities->currentPage(),
                        'per_page' => $facilities->perPage(),
                        'total' => $facilities->total(),
                        'last_page' => $facilities->lastPage(),
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get facilities', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve facilities',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Create a new facility
     */
    public function createFacility(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:facilities,code',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:hub,warehouse,depot,pickup_point,delivery_point',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $facility = Facility::create([
                'code' => $request->code,
                'name' => $request->name,
                'type' => $request->type,
                'address' => $request->address,
                'city' => $request->city,
                'province' => $request->province,
                'postal_code' => $request->postal_code,
                'country' => $request->input('country', 'TH'),
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'timezone' => $request->input('timezone', 'Asia/Bangkok'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            Cache::forget('facilities:all');

            AuditLog::log(
                AuditLog::ACTION_CREATE,
                $request->user(),
                Facility::class,
                $facility->id,
                null,
                $facility->toArray()
            );

            return response()->json([
                'success' => true,
                'data' => $facility,
                'message' => 'Facility created successfully',
                'timestamp' => now()->toISOString(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create facility', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create facility',
                'error_code' => 'CREATE_ERROR',
            ], 500);
        }
    }

    /**
     * Update a facility
     */
    public function updateFacility(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'code' => 'nullable|string|max:20|unique:facilities,code,' . $id,
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:hub,warehouse,depot,pickup_point,delivery_point',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $facility = Facility::find($id);

            if (!$facility) {
                return response()->json([
                    'success' => false,
                    'error' => 'Facility not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            $oldValues = $facility->toArray();

            $updateData = array_filter($request->only([
                'code', 'name', 'type', 'address', 'city', 'province',
                'postal_code', 'country', 'latitude', 'longitude', 'timezone', 'is_active',
            ]), fn($v) => $v !== null);

            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No fields to update',
                    'error_code' => 'NO_CHANGES',
                ], 400);
            }

            $facility->update($updateData);

            Cache::forget('facilities:all');

            AuditLog::log(
                AuditLog::ACTION_CONFIG_CHANGE,
                $request->user(),
                Facility::class,
                $facility->id,
                $oldValues,
                $updateData
            );

            return response()->json([
                'success' => true,
                'data' => $facility->fresh(),
                'message' => 'Facility updated successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update facility', ['facility_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update facility',
                'error_code' => 'UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Get event code mappings
     */
    public function eventCodes(): JsonResponse
    {
        try {
            // These would typically come from a database table or config file
            $eventCodes = Cache::remember('config:event_codes', 3600, function () {
                return [
                    'PICKUP' => [
                        'description' => 'Parcel picked up from sender',
                        'status' => 'picked_up',
                        'category' => 'collection',
                    ],
                    'IN_TRANSIT' => [
                        'description' => 'Parcel in transit',
                        'status' => 'in_transit',
                        'category' => 'transit',
                    ],
                    'AT_HUB' => [
                        'description' => 'Parcel arrived at hub',
                        'status' => 'at_hub',
                        'category' => 'transit',
                    ],
                    'OUT_FOR_DELIVERY' => [
                        'description' => 'Parcel out for delivery',
                        'status' => 'out_for_delivery',
                        'category' => 'delivery',
                    ],
                    'DELIVERED' => [
                        'description' => 'Parcel delivered',
                        'status' => 'delivered',
                        'category' => 'delivery',
                    ],
                    'EXCEPTION' => [
                        'description' => 'Delivery exception',
                        'status' => 'exception',
                        'category' => 'exception',
                    ],
                    'RETURNED' => [
                        'description' => 'Parcel returned to sender',
                        'status' => 'returned',
                        'category' => 'return',
                    ],
                    'HELD' => [
                        'description' => 'Parcel held at facility',
                        'status' => 'held',
                        'category' => 'exception',
                    ],
                    'CUSTOMS_CLEARED' => [
                        'description' => 'Customs clearance completed',
                        'status' => 'in_transit',
                        'category' => 'transit',
                    ],
                    'CUSTOMS_HELD' => [
                        'description' => 'Held at customs',
                        'status' => 'exception',
                        'category' => 'exception',
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $eventCodes,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get event codes', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve event codes',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Get ETA rules
     */
    public function etaRules(): JsonResponse
    {
        try {
            // Load ETA rules from database
            $rules = \App\Models\EtaRule::orderBy('origin_region')
                ->orderBy('destination_region')
                ->get();

            $lanes = \App\Models\EtaLane::orderBy('origin_facility_id')
                ->orderBy('destination_facility_id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'rules' => $rules,
                    'lanes' => $lanes,
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get ETA rules', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve ETA rules',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Get system configuration
     */
    public function systemConfig(): JsonResponse
    {
        try {
            $config = [
                'tracking' => [
                    'max_tracking_numbers' => config('services.tracking.max_numbers', 20),
                    'cache_ttl_seconds' => config('services.tracking.cache_ttl', 30),
                ],
                'notifications' => [
                    'throttle_hours' => config('services.notifications.throttle_hours', 2),
                    'max_per_day' => config('services.notifications.max_per_day', 10),
                ],
                'rate_limits' => [
                    'public_api' => config('services.rate_limits.public', 60),
                    'admin_api' => config('services.rate_limits.admin', 300),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $config,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get system config', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve configuration',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }
}
