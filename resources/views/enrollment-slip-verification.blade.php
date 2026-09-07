<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Verify Enrolment Slip - {{ $enrollee->enrollee_id }}</title>
    <style>
        :root { color-scheme: light; --blue: #1684dc; --ink: #20252b; --muted: #667085; --line: #d8dee6; --green: #15743c; --amber: #9a5b00; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f6f9; color: var(--ink); font-family: "DejaVu Sans", Arial, sans-serif; }
        .shell { width: min(720px, calc(100% - 28px)); margin: 28px auto; }
        .card { overflow: hidden; border: 1px solid var(--line); border-radius: 14px; background: #fff; box-shadow: 0 12px 32px rgba(25, 44, 70, .08); }
        .header { padding: 26px 24px 22px; text-align: center; border-bottom: 1px solid var(--line); }
        .logo { width: 72px; height: 72px; object-fit: contain; }
        .agency { margin: 8px 0 2px; color: var(--blue); font-size: 18px; font-weight: 700; }
        .scheme { margin: 0; color: var(--muted); font-size: 14px; }
        .verified { display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; padding: 8px 13px; border-radius: 999px; background: #e9f7ef; color: var(--green); font-size: 13px; font-weight: 700; }
        .verified-mark { display: grid; width: 20px; height: 20px; place-items: center; border-radius: 50%; background: var(--green); color: #fff; }
        .content { padding: 24px; }
        .identity { display: flex; align-items: center; gap: 18px; margin-bottom: 24px; }
        .photo, .photo-placeholder { width: 90px; height: 106px; flex: 0 0 auto; border: 1px solid var(--line); border-radius: 8px; object-fit: cover; }
        .photo-placeholder { display: grid; place-items: center; background: #f4f6f8; color: #98a1aa; font-size: 11px; }
        h1 { margin: 0 0 8px; font-size: 24px; line-height: 1.2; }
        .number { margin: 0; color: var(--muted); font-size: 14px; }
        .number strong { color: var(--ink); }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border-top: 1px solid var(--line); border-left: 1px solid var(--line); }
        .field { min-height: 76px; padding: 14px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .label { display: block; margin-bottom: 5px; color: var(--muted); font-size: 12px; }
        .value { font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
        .status { color: {{ (int) $enrollee->status === \App\Models\Enrollee::STATUS_ACTIVE ? 'var(--green)' : 'var(--amber)' }}; }
        .notice { margin: 20px 0 0; color: var(--muted); font-size: 12px; line-height: 1.55; text-align: center; }
        .footer { padding: 16px 24px; background: #f8fafc; color: var(--muted); font-size: 12px; text-align: center; }
        @media (max-width: 560px) {
            .shell { margin: 14px auto; }
            .content { padding: 18px; }
            .identity { align-items: flex-start; }
            .photo, .photo-placeholder { width: 76px; height: 90px; }
            h1 { font-size: 19px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@php
    $photoUrl = trim((string) $enrollee->preferredProfilePhotoUrl());
    if ($photoUrl !== '' && !preg_match('#^(?:https?://|data:|/)#i', $photoUrl)) {
        $photoUrl = asset(ltrim($photoUrl, '/'));
    }
    $sex = (int) $enrollee->sex === 1 ? 'Male' : ((int) $enrollee->sex === 2 ? 'Female' : 'N/A');
    $category = $enrollee->vulnerableGroup->name ?? $enrollee->enrolleeCategory->name ?? 'N/A';
    $plan = $enrollee->premiumPlan->name ?? $enrollee->benefitPackage->name ?? 'N/A';
    $statusLabels = [0 => 'Pending', 1 => 'Active', 2 => 'Rejected', 3 => 'Suspended', 4 => 'Expired'];
    $status = $statusLabels[(int) $enrollee->status] ?? 'Unknown';
@endphp
<main class="shell">
    <section class="card">
        <header class="header">
            <img class="logo" src="{{ asset('logo-slip.jpg') }}" alt="NGSCHA logo">
            <p class="agency">{{ $organization['agency_name'] ?? 'Niger State Contributory Health Agency' }}</p>
            <p class="scheme">{{ $organization['scheme_name'] ?? 'NiCare' }} Enrolment Verification</p>
            <div class="verified"><span class="verified-mark">&#10003;</span> Authentic agency record</div>
        </header>

        <div class="content">
            <div class="identity">
                @if($photoUrl !== '')
                    <img class="photo" src="{{ $photoUrl }}" alt="{{ $enrollee->full_name }}">
                @else
                    <div class="photo-placeholder">NO PHOTO</div>
                @endif
                <div>
                    <h1>{{ $enrollee->full_name ?: 'N/A' }}</h1>
                    <p class="number">NiCare Number: <strong>{{ $enrollee->enrollee_id ?: 'N/A' }}</strong></p>
                </div>
            </div>

            <div class="grid">
                <div class="field"><span class="label">Current status</span><span class="value status">{{ $status }}</span></div>
                <div class="field"><span class="label">Sex</span><span class="value">{{ $sex }}</span></div>
                <div class="field"><span class="label">Programme</span><span class="value">{{ $enrollee->insuranceProgramme->name ?? 'N/A' }}</span></div>
                <div class="field"><span class="label">Category</span><span class="value">{{ $category }}</span></div>
                <div class="field"><span class="label">Provider / Hospital</span><span class="value">{{ $enrollee->facility->name ?? 'N/A' }}</span></div>
                <div class="field"><span class="label">Plan</span><span class="value">{{ $plan }}</span></div>
                <div class="field"><span class="label">Coverage starts</span><span class="value">{{ optional($enrollee->coverage_start_date)->format('d M Y') ?: 'Pending' }}</span></div>
                <div class="field"><span class="label">Coverage ends</span><span class="value">{{ optional($enrollee->coverage_end_date)->format('d M Y') ?: 'No Expiry' }}</span></div>
            </div>

            <p class="notice">This page confirms that the QR code points to a record held by the Niger State Contributory Health Agency. Personal identifiers such as NIN, phone number and home address are intentionally not displayed.</p>
        </div>

        <footer class="footer">For enquiries: {{ $organization['hotline'] ?? '08162653801' }}</footer>
    </section>
</main>
</body>
</html>
