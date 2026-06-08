<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Premium PIN Docket</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #102a43; font-size: 11px; }
        h1 { color: #075985; margin-bottom: 4px; }
        .summary { background: #eef8f7; border: 1px solid #9ccdc8; padding: 12px; margin: 16px 0; }
        .summary span { display: inline-block; margin-right: 24px; }
        .pin { border: 1px dashed #0f766e; padding: 14px; margin-bottom: 10px; page-break-inside: avoid; }
        .pin-value { font-size: 22px; font-weight: bold; letter-spacing: 3px; margin: 8px 0; color: #0f766e; }
        .muted { color: #526777; }
    </style>
</head>
<body>
    <h1>NiCare Premium PIN Docket</h1>
    <div class="muted">Payment reference: {{ $purchase->payment_reference }}</div>

    <div class="summary">
        <span><strong>Purchaser:</strong> {{ $purchase->payer_name }}</span>
        <span><strong>Plan:</strong> {{ $purchase->plan?->name }}</span>
        <span><strong>Quantity:</strong> {{ $purchase->quantity }}</span>
        <span><strong>Total:</strong> NGN {{ number_format((float) $purchase->amount, 2) }}</span>
    </div>

    @foreach ($purchase->pins as $pin)
        <section class="pin">
            <strong>{{ $pin->plan?->name ?? $purchase->plan?->name }}</strong>
            <div class="pin-value">{{ $pin->pin }}</div>
            <div>Serial: {{ $pin->serial_number }}</div>
            <div>Batch: {{ $pin->batch_code }}</div>
            <div>Value: NGN {{ number_format((float) $pin->amount, 2) }}</div>
            <div class="muted">This PIN is valid for one enrollment only.</div>
        </section>
    @endforeach
</body>
</html>
