<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Submission Slip - {{ $claim->claim_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; line-height: 1.4; color: #333; }
        .container { padding: 20px; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #273e60ff; padding-bottom: 15px; }
        .header h1 { font-size: 18px; color: #273e60ff; margin-bottom: 5px; }
        .header h2 { font-size: 14px; color: #666; font-weight: normal; }
        .logo { font-size: 24px; font-weight: bold; color: #273e60ff; margin-bottom: 5px; }
        .claim-number { background: #273e60ff; color: white; padding: 8px 15px; display: inline-block; font-size: 14px; font-weight: bold; border-radius: 4px; margin-top: 10px; }
        .section { margin-bottom: 15px; }
        .section-title { background: #f8f9fa; padding: 8px 10px; font-weight: bold; font-size: 12px; border-left: 3px solid #273e60ff; margin-bottom: 10px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; padding: 5px 10px; font-weight: bold; width: 35%; background: #f8f9fa; }
        .info-value { display: table-cell; padding: 5px 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        th { background: #e9ecef; font-weight: bold; font-size: 10px; }
        td { font-size: 10px; }
        .amount { text-align: right; }
        .total-row { background: #d4edda; font-weight: bold; }
        .total-row td { font-size: 12px; }
        .summary-box { background: #d4edda; padding: 15px; border-radius: 4px; text-align: center; margin-top: 20px; }
        .summary-box .amount { font-size: 24px; font-weight: bold; color: #198754; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #dee2e6; text-align: center; font-size: 9px; color: #666; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .status-submitted { background: #cfe2ff; color: #273e60ff; }
        .status-approved { background: #d4edda; color: #198754; }
        .two-col { display: table; width: 100%; }
        .two-col > div { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
        .two-col > div:last-child { padding-right: 0; padding-left: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">NiCare</div>
            <h1>NIGER STATE CONTRIBUTORY HEALTH AGENCY</h1>
            <h2>Claim Submission Slip</h2>
            <div class="claim-number">{{ $claim->claim_number }}</div>
        </div>

        <div class="two-col">
            <div>
                <div class="section">
                    <div class="section-title">Claim Information</div>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Claim Number:</div>
                            <div class="info-value">{{ $claim->claim_number }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">UTN:</div>
                            <div class="info-value">{{ $claim->utn }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value">
                                <span class="status-badge status-{{ strtolower($claim->status) }}">{{ $claim->status }}</span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Claim Date:</div>
                            <div class="info-value">{{ $claim->claim_date ? \Carbon\Carbon::parse($claim->claim_date)->format('d M Y') : 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Submitted:</div>
                            <div class="info-value">{{ $claim->submitted_at ? \Carbon\Carbon::parse($claim->submitted_at)->format('d M Y, H:i') : 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="section">
                    <div class="section-title">Enrollee Information</div>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Name:</div>
                            <div class="info-value">{{ $claim->enrollee?->first_name }} {{ $claim->enrollee?->last_name }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Enrollee ID:</div>
                            <div class="info-value">{{ $claim->enrollee?->enrollee_number ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone:</div>
                            <div class="info-value">{{ $claim->enrollee?->phone ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">LGA:</div>
                            <div class="info-value">{{ $claim->enrollee?->lga?->name ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Healthcare Facility</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Facility Name:</div>
                    <div class="info-value">{{ $claim->facility?->name ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Facility Code:</div>
                    <div class="info-value">{{ $claim->facility?->code ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Claim Line Items</div>
            <table>
                <thead>
                    <tr>
                        <th>Service Description</th>
                        <th style="width: 80px;">Type</th>
                        <th class="amount" style="width: 60px;">Qty</th>
                        <th class="amount" style="width: 100px;">Unit Price</th>
                        <th class="amount" style="width: 120px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $bundleTotal = 0;
                        $ffsTotal = 0;
                    @endphp

                    @if($bundle_items->count() > 0)
                        @foreach($bundle_items as $item)
                        <tr>
                            <td>{{ $item->service_description }}</td>
                            <td><span style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold;">BUNDLE</span></td>
                            <td class="amount">{{ $item->quantity }}</td>
                            <td class="amount">-</td>
                            <td class="amount">-</td>
                        </tr>
                        @endforeach
                        <tr style="background: #e3f2fd;">
                            <td  style="text-align: right; font-weight: bold;">Bundle Fixed Price:</td>
                            <td colspan="3" style="text-align: right; font-weight: bold;">Bundle Fixed Price:</td>
                            <td class="amount" style="font-weight: bold;">₦{{ number_format($claim->bundle_amount, 2) }}</td>
                        </tr>
                        @php $bundleTotal = $claim->bundle_amount; @endphp
                    @endif

                    @if($ffs_items->count() > 0)
                        @foreach($ffs_items as $item)
                        <tr>
                            <td>{{ $item->service_description }}</td>
                            <td><span style="background: #fff3e0; color: #f57c00; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold;">FFS</span></td>
                            <td class="amount">{{ $item->quantity }}</td>
                            <td class="amount">₦{{ number_format($item->unit_price, 2) }}</td>
                            <td class="amount">₦{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @php $ffsTotal += $item->line_total; @endphp
                        @endforeach
                        @if($ffs_items->count() > 0)
                        <tr style="background: #fff3e0;">
                            <td colspan="4" style="text-align: right; font-weight: bold;">FFS Subtotal:</td>
                            <td class="amount" style="font-weight: bold;">₦{{ number_format($ffsTotal, 2) }}</td>
                        </tr>
                        @endif
                    @endif
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">TOTAL CLAIM AMOUNT:</td>
                        <td class="amount">₦{{ number_format($bundleTotal + $ffsTotal, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer">
            <p>Generated on {{ $generated_at }} | NiCare Claims Management System</p>
            <p>This is a computer-generated document. No signature required.</p>
        </div>
    </div>
</body>
</html>

