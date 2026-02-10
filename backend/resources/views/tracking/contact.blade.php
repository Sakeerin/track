<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; background: #f7f7fb; margin: 0; color: #1f2937; }
        .container { max-width: 680px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .field { display: grid; gap: 6px; margin-bottom: 10px; }
        input, textarea { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; box-sizing: border-box; width: 100%; }
        .btn { border: 1px solid #111827; background: #111827; color: #fff; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
        .notice { border-radius: 8px; padding: 10px; margin-bottom: 10px; }
        .success { background: #ecfdf5; border: 1px solid #a7f3d0; }
    </style>
</head>
<body>
<main class="container">
    <div class="card">
        <h1>Contact Support</h1>

        @if(session('success'))
            <div class="notice success">{{ session('success') }}</div>
        @endif

        <form method="post" action="{{ route('public.contact.submit') }}">
            @csrf
            <div class="field">
                <label for="tracking_number">Tracking Number</label>
                <input id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $trackingNumber) }}">
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
                <input id="subject" name="subject" value="{{ old('subject') }}" required>
            </div>
            <div class="field">
                <label for="message">Message</label>
                <textarea id="message" rows="6" name="message" required>{{ old('message') }}</textarea>
            </div>
            <button class="btn" type="submit">Submit Ticket</button>
        </form>
    </div>
</main>
</body>
</html>
