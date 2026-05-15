<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 20px; }
        h1   { font-size: 18px; color: #1a3a5c; border-bottom: 2px solid #1a3a5c; padding-bottom: 6px; }
        h2   { font-size: 13px; color: #2c5f8a; }
        .meta { color: #555; margin-bottom: 10px; }
        .filters { background: #f4f7fb; border: 1px solid #d0d9e4; padding: 8px 12px; margin-bottom: 14px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th    { background: #1a3a5c; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        td    { padding: 5px 8px; border-bottom: 1px solid #e0e7ef; }
        tr:nth-child(even) td { background: #f4f7fb; }
        .kpi-grid { display: table; width: 100%; margin: 10px 0; }
        .kpi-card { display: table-cell; border: 1px solid #d0d9e4; padding: 10px 14px; text-align: center; background: #eaf1f8; border-radius: 4px; }
        .kpi-label { font-size: 9px; color: #555; text-transform: uppercase; letter-spacing: .5px; }
        .kpi-value { font-size: 20px; font-weight: bold; color: #1a3a5c; }
        footer { margin-top: 20px; font-size: 9px; color: #aaa; text-align: right; }
    </style>
</head>
<body>
    <h1>NiCare Health Insurance — {{ $title ?? 'Report' }}</h1>

    <div class="meta">
        <strong>Generated:</strong> {{ $generated_at ?? now()->format('d M Y H:i') }}&nbsp;&nbsp;
        <strong>By:</strong> {{ $generated_by ?? 'System' }}
    </div>

    @if (!empty($filters))
    <div class="filters">
        <strong>Filters applied:</strong>
        @foreach ($filters as $key => $value)
            @if ($value)
                <span>{{ ucfirst(str_replace('_', ' ', $key)) }}: <em>{{ $value }}</em></span>&nbsp; &bull; &nbsp;
            @endif
        @endforeach
    </div>
    @endif

    @yield('content')

    <footer>NiCare Health Insurance &mdash; Confidential &mdash; {{ now()->format('d M Y') }}</footer>
</body>
</html>
