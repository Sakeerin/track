<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->where('destination', $request->destination)
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
}
