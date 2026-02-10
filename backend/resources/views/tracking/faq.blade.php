<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; background: #f7f7fb; margin: 0; color: #1f2937; }
        .container { max-width: 760px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .faq-item { border-top: 1px solid #f3f4f6; padding-top: 10px; margin-top: 10px; }
        .small { font-size: 14px; color: #4b5563; }
        input { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; box-sizing: border-box; }
    </style>
</head>
<body>
<main class="container">
    <div class="card">
        <h1>Tracking FAQ</h1>
        <form method="get" action="{{ route('public.faq') }}">
            <input type="search" name="q" value="{{ $query }}" placeholder="Search FAQ">
        </form>
    </div>

    <div class="card">
        @foreach($faqItems as $item)
            <article class="faq-item">
                <strong>{{ $item['question'] }}</strong>
                <p class="small">{{ $item['answer'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="card">
        <h2>Delivery Time Estimates</h2>
        <ul>
            @foreach($serviceEstimates as $estimate)
                <li>{{ $estimate['service'] }}: {{ $estimate['estimate'] }}</li>
            @endforeach
        </ul>
    </div>
</main>
</body>
</html>
