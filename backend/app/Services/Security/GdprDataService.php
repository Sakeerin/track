<?php

namespace App\Services\Security;

use App\Models\Subscription;
use App\Models\SupportTicket;
use Illuminate\Support\Arr;

class GdprDataService
{
    public function exportByEmail(string $email): array
    {
        $hash = $this->hashValue($email);

        $subscriptions = Subscription::query()
            ->where('channel', 'email')
            ->where('destination_hash', $hash)
            ->get()
            ->map(function (Subscription $subscription): array {
                return Arr::only($subscription->toArray(), [
                    'id',
                    'shipment_id',
                    'channel',
                    'destination',
                    'events',
                    'active',
                    'consent_given',
                    'consent_at',
                    'created_at',
                ]);
            })
            ->all();

        $tickets = SupportTicket::query()
            ->where('email_hash', $hash)
            ->get()
            ->map(function (SupportTicket $ticket): array {
                return Arr::only($ticket->toArray(), [
                    'id',
                    'ticket_number',
                    'tracking_number',
                    'name',
                    'email',
                    'subject',
                    'message',
                    'status',
                    'created_at',
                ]);
            })
            ->all();

        return [
            'email' => strtolower(trim($email)),
            'subscriptions' => $subscriptions,
            'support_tickets' => $tickets,
        ];
    }

    public function deleteByEmail(string $email): array
    {
        $hash = $this->hashValue($email);

        $subscriptionIds = Subscription::query()
            ->where('channel', 'email')
            ->where('destination_hash', $hash)
            ->pluck('id');

        $deletedSubscriptions = Subscription::query()
            ->whereIn('id', $subscriptionIds)
            ->delete();

        $deletedTickets = SupportTicket::query()
            ->where('email_hash', $hash)
            ->delete();

        return [
            'email' => strtolower(trim($email)),
            'deleted_subscriptions' => $deletedSubscriptions,
            'deleted_support_tickets' => $deletedTickets,
        ];
    }

    private function hashValue(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }
}
