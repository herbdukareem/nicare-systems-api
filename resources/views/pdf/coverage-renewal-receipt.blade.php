<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #15233d; font-size: 12px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 14px; margin-bottom: 24px; }
        h1 { font-size: 22px; margin: 0 0 5px; color: #0f766e; }
        .muted { color: #64748b; } .status { color: #166534; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        td:last-child { text-align: right; font-weight: bold; }
        .total td { font-size: 15px; border-top: 2px solid #0f766e; border-bottom: 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Coverage Renewal Receipt</h1>
        <div class="muted">Niger State Contributory Health Scheme</div>
    </div>
    <p><strong>Enrollee:</strong> {{ $enrollee->full_name ?: $enrollee->name }}</p>
    <p><strong>Enrollee ID:</strong> {{ $enrollee->enrollee_id }}</p>
    <p><strong>Plan:</strong> {{ $purchase->plan?->name ?: 'Coverage renewal' }}</p>
    <p><strong>Reference:</strong> {{ $purchase->payment_reference }}</p>
    <p><strong>Payment status:</strong> <span class="status">Confirmed</span></p>
    <p><strong>Confirmed:</strong> {{ optional($purchase->confirmed_at)->format('d M Y, h:i A') }}</p>
    <table>
        <tr><td>Coverage price</td><td>₦{{ number_format((float) ($purchase->base_amount ?? $purchase->amount), 2) }}</td></tr>
        <tr><td>Processing fee</td><td>₦{{ number_format((float) ($purchase->processing_fee ?? 0), 2) }}</td></tr>
        <tr class="total"><td>Total paid</td><td>₦{{ number_format((float) ($purchase->customer_total ?? $purchase->amount), 2) }}</td></tr>
    </table>
</body>
</html>
