<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactSupportRequest;
use App\Models\SupportTicket;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicTrackingController extends Controller
{
    private TrackingService $trackingService;

    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    public function show(string $trackingNumber): View
    {
        $shipment = $this->trackingService->getShipment($trackingNumber);

        abort_if($shipment === null, 404, 'Tracking number not found');

        $shareUrl = route('public.track', ['trackingNumber' => $trackingNumber]);

        $metaTitle = sprintf('Track Parcel %s | Parcel Tracking', $trackingNumber);
        $metaDescription = sprintf(
            'Track parcel %s with live updates, status timeline, and estimated delivery.',
            $trackingNumber
        );

        return view('tracking.public', [
            'trackingNumber' => $trackingNumber,
            'shipment' => $shipment,
            'shareUrl' => $shareUrl,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'faqItems' => $this->faqItems(),
            'statusExplanations' => $this->statusExplanations(),
            'serviceEstimates' => $this->serviceEstimates(),
            'structuredData' => $this->buildStructuredData($shipment, $shareUrl),
        ]);
    }

    public function faq(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $items = $this->faqItems();

        if ($query !== '') {
            $items = array_values(array_filter($items, function (array $item) use ($query): bool {
                $needle = Str::lower($query);

                return str_contains(Str::lower($item['question']), $needle)
                    || str_contains(Str::lower($item['answer']), $needle);
            }));
        }

        return view('tracking.faq', [
            'query' => $query,
            'faqItems' => $items,
            'serviceEstimates' => $this->serviceEstimates(),
            'metaTitle' => 'Tracking FAQ | Parcel Tracking',
            'metaDescription' => 'Answers about shipment statuses, delivery estimates, and support options.',
        ]);
    }

    public function contact(Request $request): View
    {
        $trackingNumber = trim((string) $request->query('tracking_number', ''));

        return view('tracking.contact', [
            'trackingNumber' => $trackingNumber,
            'metaTitle' => 'Contact Support | Parcel Tracking',
            'metaDescription' => 'Contact support for shipment updates and tracking assistance.',
        ]);
    }

    public function submitContact(ContactSupportRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ticket = SupportTicket::create([
            'ticket_number' => 'SUP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'tracking_number' => $data['tracking_number'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'source' => 'public_web',
            'status' => 'open',
        ]);

        return redirect()
            ->route('public.contact', ['tracking_number' => $data['tracking_number'] ?? null])
            ->with('success', 'Support request received. Ticket: ' . $ticket->ticket_number);
    }

    private function faqItems(): array
    {
        return [
            [
                'question' => 'What does In Transit mean?',
                'answer' => 'Your parcel is moving between facilities and is on its planned route.',
            ],
            [
                'question' => 'What does Out for Delivery mean?',
                'answer' => 'Your parcel is with a courier and is expected to arrive today.',
            ],
            [
                'question' => 'Why is my parcel delayed?',
                'answer' => 'Weather, traffic, customs processing, and incorrect address details can cause delays.',
            ],
            [
                'question' => 'How accurate is ETA?',
                'answer' => 'ETA is calculated from service type, lane, and current events and may change with new scans.',
            ],
            [
                'question' => 'How can I contact support?',
                'answer' => 'Use the contact form and include the tracking number for faster assistance.',
            ],
        ];
    }

    private function statusExplanations(): array
    {
        return [
            'created' => 'Shipment has been registered.',
            'picked_up' => 'Courier has collected the parcel.',
            'in_transit' => 'Parcel is moving between facilities.',
            'at_hub' => 'Parcel is being sorted at a hub.',
            'out_for_delivery' => 'Parcel is with a courier for final delivery.',
            'delivered' => 'Parcel has been delivered successfully.',
            'exception' => 'An issue needs attention before delivery can continue.',
            'returned' => 'Parcel is being returned to sender.',
            'cancelled' => 'Shipment has been cancelled.',
        ];
    }

    private function serviceEstimates(): array
    {
        return [
            ['service' => 'Express', 'estimate' => '1-2 business days'],
            ['service' => 'Standard', 'estimate' => '2-4 business days'],
            ['service' => 'Economy', 'estimate' => '3-7 business days'],
        ];
    }

    private function buildStructuredData(array $shipment, string $shareUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ParcelDelivery',
            'trackingNumber' => $shipment['tracking_number'],
            'provider' => [
                '@type' => 'Organization',
                'name' => 'Parcel Tracking',
            ],
            'deliveryStatus' => $this->schemaDeliveryStatus((string) ($shipment['current_status'] ?? 'in_transit')),
            'url' => $shareUrl,
            'expectedArrivalUntil' => $shipment['estimated_delivery'] ?? null,
        ];
    }

    private function schemaDeliveryStatus(string $status): string
    {
        $map = [
            'delivered' => 'https://schema.org/DeliveryDelivered',
            'out_for_delivery' => 'https://schema.org/OutForDelivery',
            'exception' => 'https://schema.org/DeliveryIssue',
            'returned' => 'https://schema.org/DeliveryReturned',
        ];

        return $map[$status] ?? 'https://schema.org/InTransit';
    }
}
