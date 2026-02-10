<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [
            url('/'),
            route('public.faq'),
            route('public.contact'),
        ];

        $trackingUrls = Shipment::query()
            ->latest('updated_at')
            ->limit(200)
            ->pluck('tracking_number')
            ->map(fn (string $trackingNumber): string => route('public.track', ['trackingNumber' => $trackingNumber]))
            ->toArray();

        $urls = array_merge($urls, $trackingUrls);

        $lastmod = now()->toAtomString();
        $xml = view('sitemap.index', [
            'urls' => $urls,
            'lastmod' => $lastmod,
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
