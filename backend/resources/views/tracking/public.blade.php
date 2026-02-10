<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES) !!}</script>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; background: #f7f7fb; color: #1f2937; }
        .container { max-width: 880px; margin: 0 auto; padding: 24px 16px 40px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .label { font-size: 12px; color: #6b7280; }
        .value { font-weight: 600; }
        .small { color: #4b5563; font-size: 14px; }
        .timeline { list-style: none; padding: 0; margin: 0; }
        .timeline li { padding: 10px 0; border-top: 1px solid #f3f4f6; }
        .status-list { display: grid; gap: 8px; }
        .status-row { display: flex; justify-content: space-between; gap: 12px; border-top: 1px solid #f3f4f6; padding-top: 8px; }
        .faq-item { border-top: 1px solid #f3f4f6; padding-top: 10px; margin-top: 10px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        button, input, textarea { font: inherit; }
        .btn { border: 1px solid #d1d5db; background: #fff; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
        .btn-primary { background: #111827; color: #fff; border-color: #111827; }
        .field { display: grid; gap: 6px; margin-bottom: 10px; }
        input, textarea { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; width: 100%; box-sizing: border-box; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main class="container">
    <div class="card">
        <h1>Track Parcel {{ $trackingNumber }}</h1>
        <p class="small">Live status, event timeline, and estimated delivery.</p>
        <div class="actions">
            <input id="share-link" type="text" value="{{ $shareUrl }}" readonly>
            <button class="btn" type="button" onclick="copyShareLink()">Copy Share Link</button>
            <a class="btn" href="{{ route('public.faq') }}">View FAQ</a>
        </div>
    </div>

    <div class="card">
        <div class="grid">
            <div>
                <div class="label">Current Status</div>
                <div class="value">{{ $shipment['current_status'] }}</div>
            </div>
            <div>
                <div class="label">Service Type</div>
                <div class="value">{{ $shipment['service_type'] }}</div>
            </div>
            <div>
                <div class="label">Estimated Delivery</div>
                <div class="value">{{ $shipment['estimated_delivery'] ?? 'Pending update' }}</div>
            </div>
            <div>
                <div class="label">Route</div>
                <div class="value">{{ $shipment['origin']['name'] ?? 'N/A' }} -> {{ $shipment['destination']['name'] ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Timeline</h2>
        <ul class="timeline">
            @foreach($shipment['timeline'] as $event)
                <li>
                    <div class="value">{{ $event['display_name']['en'] ?? $event['event_code'] }}</div>
                    <div class="small">{{ $event['event_time_local'] }} | {{ $event['location']['name'] ?? 'Unknown location' }}</div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="card">
        <h2>Status Guide</h2>
        <div class="status-list">
            @foreach($statusExplanations as $status => $text)
                <div class="status-row">
                    <strong>{{ $status }}</strong>
                    <span class="small">{{ $text }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <h2>FAQ</h2>
        <input id="faq-search" type="search" placeholder="Search FAQ" oninput="filterFaq()">
        <div id="faq-list">
            @foreach($faqItems as $item)
                <article class="faq-item" data-faq>
                    <strong>{{ $item['question'] }}</strong>
                    <p class="small">{{ $item['answer'] }}</p>
                </article>
            @endforeach
        </div>
        <h3>Delivery Time Estimates</h3>
        <ul>
            @foreach($serviceEstimates as $estimate)
                <li>{{ $estimate['service'] }}: {{ $estimate['estimate'] }}</li>
            @endforeach
        </ul>
    </div>

    <div class="card">
        <h2>Contact Support</h2>
        <form method="post" action="{{ route('public.contact.submit') }}">
            @csrf
            <div class="field">
                <label for="tracking_number">Tracking Number</label>
                <input id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $trackingNumber) }}" readonly>
            </div>
            <div class="field">
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label for="subject">Subject</label>
                <input id="subject" name="subject" value="{{ old('subject', 'Tracking support request') }}" required>
            </div>
            <div class="field">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="5" required>{{ old('message') }}</textarea>
            </div>
            <button class="btn btn-primary" type="submit">Submit Ticket</button>
        </form>
    </div>
</main>

<script>
    function copyShareLink() {
        const input = document.getElementById('share-link');
        input.select();
        document.execCommand('copy');
    }

    function filterFaq() {
        const query = document.getElementById('faq-search').value.toLowerCase();
        const items = document.querySelectorAll('[data-faq]');

        items.forEach((item) => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    }
</script>
</body>
</html>
