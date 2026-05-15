<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; }
        .card { width: 252px; height: 396px; border: 1px solid #0e7490; border-radius: 12px; overflow: hidden; }
        .top { background: #0e7490; color: white; padding: 12px; text-align: center; }
        .brand { font-size: 13px; font-weight: 700; letter-spacing: .4px; }
        .sub { font-size: 9px; margin-top: 2px; opacity: .9; }
        .body { padding: 12px; }
        .photo { width: 82px; height: 82px; border: 1px solid #cbd5e1; border-radius: 8px; object-fit: cover; background: #f8fafc; }
        .name { font-size: 14px; font-weight: 700; margin: 8px 0 2px; }
        .id { font-size: 10px; color: #0e7490; font-weight: 700; }
        .grid { margin-top: 10px; }
        .row { padding: 5px 0; border-bottom: 1px solid #e2e8f0; }
        .label { font-size: 8px; color: #64748b; text-transform: uppercase; }
        .value { font-size: 10px; font-weight: 600; }
        .footer { position: absolute; left: 12px; right: 12px; bottom: 10px; font-size: 8px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
<div class="card">
    <div class="top">
        <div class="brand">NiCARE / NGSCHA</div>
        <div class="sub">Health Insurance Enrollee ID Card</div>
    </div>
    <div class="body">
        @if($enrollee->image_url)
            <img class="photo" src="{{ public_path(ltrim($enrollee->image_url, '/')) }}" alt="Passport">
        @else
            <div class="photo"></div>
        @endif

        <div class="name">{{ $enrollee->full_name ?: 'N/A' }}</div>
        <div class="id">{{ $enrollee->enrollee_id }}</div>

        <div class="grid">
            <div class="row">
                <div class="label">NIN</div>
                <div class="value">{{ $enrollee->nin ?: 'N/A' }}</div>
            </div>
            <div class="row">
                <div class="label">Sex / DOB</div>
                <div class="value">{{ (int) $enrollee->sex === 1 ? 'Male' : ((int) $enrollee->sex === 2 ? 'Female' : 'Other') }} / {{ optional($enrollee->date_of_birth)->format('d M Y') ?: 'N/A' }}</div>
            </div>
            <div class="row">
                <div class="label">Programme</div>
                <div class="value">{{ $enrollee->insuranceProgramme->name ?? 'N/A' }}</div>
            </div>
            <div class="row">
                <div class="label">Facility</div>
                <div class="value">{{ $enrollee->facility->name ?? 'N/A' }}</div>
            </div>
            <div class="row">
                <div class="label">Coverage</div>
                <div class="value">
                    {{ optional($enrollee->coverage_start_date)->format('d M Y') ?: 'Pending' }}
                    -
                    {{ $enrollee->coverage_end_date ? $enrollee->coverage_end_date->format('d M Y') : 'No Expiry' }}
                </div>
            </div>
        </div>
    </div>
    <div class="footer">Issued {{ $generatedAt->format('d M Y') }} | Verify with Enrollee ID</div>
</div>
</body>
</html>
