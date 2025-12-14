<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Batch Payment Receipt - {{ $batch->batch_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2e7d32; padding-bottom: 15px; }
        .header h1 { color: #2e7d32; margin: 0; font-size: 20px; }
        .header h2 { color: #666; margin: 5px 0; font-size: 14px; }
        .info-section { margin-bottom: 20px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 40%; padding: 5px; font-weight: bold; background: #f5f5f5; }
        .info-value { display: table-cell; width: 60%; padding: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #2e7d32; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; background-color: #e8f5e9 !important; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .status-paid { background: #c8e6c9; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-processing { background: #e3f2fd; color: #1565c0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NASARAWA STATE GOVERNMENT CONTRIBUTORY HEALTHCARE AGENCY</h1>
        <h2>BATCH PAYMENT RECEIPT</h2>
        <p>Generated: {{ $generated_at }}</p>
    </div>

    <div class="info-section">
        <h3>Batch Information</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Batch Number:</div>
                <div class="info-value">{{ $batch->batch_number }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Batch Month:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($batch->batch_month . '-01')->format('F Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Facility:</div>
                <div class="info-value">{{ $batch->facility?->name ?? 'All Facilities' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    <span class="status-badge status-{{ strtolower($batch->status) }}">{{ $batch->status }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Payment Reference:</div>
                <div class="info-value">{{ $batch->payment_reference ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Payment Date:</div>
                <div class="info-value">{{ $batch->paid_at ? $batch->paid_at->format('d M Y') : 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h3>Claims Summary</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Claim Number</th>
                    <th>Enrollee</th>
                    <th>UTN</th>
                    <th class="amount">Bundle Amount</th>
                    <th class="amount">FFS Amount</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($batch->claims as $index => $claim)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $claim->claim_number }}</td>
                    <td>{{ $claim->enrollee?->full_name }}</td>
                    <td>{{ $claim->referral?->utn }}</td>
                    <td class="amount">₦{{ number_format($claim->bundle_amount ?? 0, 2) }}</td>
                    <td class="amount">₦{{ number_format($claim->ffs_amount ?? 0, 2) }}</td>
                    <td class="amount">₦{{ number_format($claim->approved_amount ?? $claim->total_amount_claimed, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">TOTAL ({{ $batch->total_claims }} Claims)</td>
                    <td class="amount">₦{{ number_format($batch->total_bundle_amount, 2) }}</td>
                    <td class="amount">₦{{ number_format($batch->total_ffs_amount, 2) }}</td>
                    <td class="amount">₦{{ number_format($batch->total_amount, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($batch->notes)
    <div class="info-section">
        <h3>Notes</h3>
        <p>{{ $batch->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>This is a computer-generated document. No signature required.</p>
        <p>NGSCHA Claims Automation System</p>
    </div>
</body>
</html>

