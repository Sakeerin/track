<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * Create a new notification subscription
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'required|string|exists:shipments,tracking_number',
            'channel' => 'required|in:email,sms,line,webhook',
            'destination' => 'required|string',
            'events' => 'required|array',
            'events.*' => 'string|in:Created,PickedUp,InTransit,AtHub,OutForDelivery,Delivered,DeliveryAttempted,ExceptionRaised,Returned',
            'consent_given' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $shipment = Shipment::where('tracking_number', $request->tracking_number)->first();

        // Check if subscription already exists
        $existingSubscription = Subscription::where('shipment_id', $shipment->id)
            ->where('channel', $request->channel)
            ->where('destination_hash', Subscription::hashContact($request->destination))
            ->first();

        if ($existingSubscription) {
            // Update existing subscription
            $existingSubscription->update([
                'events' => $request->events,
                'active' => true,
                'consent_given' => $request->consent_given,
                'consent_ip' => $request->ip(),
                'consent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully',
                'subscription' => $existingSubscription,
            ]);
        }

        // Create new subscription
        $subscription = Subscription::create([
            'shipment_id' => $shipment->id,
            'channel' => $request->channel,
            'destination' => $request->destination,
            'events' => $request->events,
            'active' => true,
            'consent_given' => $request->consent_given,
            'consent_ip' => $request->ip(),
            'consent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription created successfully',
            'subscription' => $subscription,
            'unsubscribe_url' => route('unsubscribe', ['token' => $subscription->unsubscribe_token]),
        ], 201);
    }

    /**
     * Get subscriptions for a shipment
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'required|string|exists:shipments,tracking_number',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $shipment = Shipment::where('tracking_number', $request->tracking_number)->first();
        $subscriptions = $shipment->subscriptions()->active()->get();

        return response()->json([
            'success' => true,
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Unsubscribe from notifications
     */
    public function unsubscribe(string $token): JsonResponse
    {
        $success = Subscription::unsubscribeByToken($token);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully unsubscribed from notifications',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired unsubscribe token',
        ], 404);
    }

    /**
     * Update subscription preferences
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'events' => 'sometimes|array',
            'events.*' => 'string|in:Created,PickedUp,InTransit,AtHub,OutForDelivery,Delivered,DeliveryAttempted,ExceptionRaised,Returned',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $subscription->update($request->only(['events', 'active']));

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated successfully',
            'subscription' => $subscription,
        ]);
    }

    /**
     * Get subscription analytics and statistics
     */
    public function analytics(string $id): JsonResponse
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found',
            ], 404);
        }

        $statistics = $subscription->getStatistics();

        // Get recent notification history
        $recentNotifications = $subscription->notificationLogs()
            ->with('event')
            ->orderBy('sent_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'event_code' => $log->event->event_code ?? 'N/A',
                    'status' => $log->status,
                    'sent_at' => $log->sent_at,
                    'delivered_at' => $log->delivered_at,
                    'error_message' => $log->error_message,
                ];
            });

        return response()->json([
            'success' => true,
            'subscription' => $subscription,
            'statistics' => $statistics,
            'recent_notifications' => $recentNotifications,
        ]);
    }

    /**
     * Get delivery tracking for notifications
     */
    public function deliveryTracking(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'required|string|exists:shipments,tracking_number',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $shipment = Shipment::where('tracking_number', $request->tracking_number)->first();

        $query = NotificationLog::whereHas('subscription', function ($q) use ($shipment) {
            $q->where('shipment_id', $shipment->id);
        });

        if ($request->has('start_date')) {
            $query->where('sent_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('sent_at', '<=', $request->end_date);
        }

        $logs = $query->with(['subscription', 'event'])
            ->orderBy('sent_at', 'desc')
            ->get();

        // Calculate aggregate statistics
        $stats = [
            'total_sent' => $logs->where('status', 'sent')->count(),
            'total_failed' => $logs->where('status', 'failed')->count(),
            'total_throttled' => $logs->where('status', 'throttled')->count(),
            'delivery_rate' => $logs->where('status', 'sent')->count() > 0
                ? ($logs->where('status', 'sent')->whereNotNull('delivered_at')->count() / $logs->where('status', 'sent')->count()) * 100
                : 0,
            'by_channel' => $logs->groupBy('channel')->map(function ($channelLogs) {
                return [
                    'total' => $channelLogs->count(),
                    'sent' => $channelLogs->where('status', 'sent')->count(),
                    'failed' => $channelLogs->where('status', 'failed')->count(),
                    'throttled' => $channelLogs->where('status', 'throttled')->count(),
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'tracking_number' => $request->tracking_number,
            'statistics' => $stats,
            'logs' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'channel' => $log->channel,
                    'destination' => $log->destination,
                    'status' => $log->status,
                    'event_code' => $log->event->event_code ?? 'N/A',
                    'sent_at' => $log->sent_at,
                    'delivered_at' => $log->delivered_at,
                    'error_message' => $log->error_message,
                ];
            }),
        ]);
    }

    /**
     * Update notification preferences for a subscription
     */
    public function updatePreferences(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:Created,PickedUp,InTransit,AtHub,OutForDelivery,Delivered,DeliveryAttempted,ExceptionRaised,ExceptionResolved,Customs,Returned',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $subscription->update([
            'events' => $request->events,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
            'subscription' => $subscription,
        ]);
    }

    /**
     * Mark notification as delivered (webhook callback)
     */
    public function markDelivered(Request $request, string $logId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivered_at' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $log = NotificationLog::find($logId);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Notification log not found',
            ], 404);
        }

        $log->update([
            'delivered_at' => $request->delivered_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as delivered',
            'log' => $log,
        ]);
    }
}

