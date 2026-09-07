<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  @page { size: A4 landscape; margin: 6mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    color: #20252b;
    font-size: 7.2pt;
    line-height: 1.28;
  }

  .slip-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    page-break-inside: avoid;
  }
  .slip-grid-cell {
    width: 50%;
    vertical-align: top;
  }
  .slip {
    width: auto;
    /* DomPDF adds padding to fixed heights even with border-box. */
    height: 86mm;
    margin: 1mm;
    border: 0.75pt solid #565c62;
    padding: 2mm 2.4mm 7.5mm;
    page-break-inside: avoid;
    overflow: hidden;
    position: relative;
  }

  .hero-tbl,
  .summary-tbl,
  .details-tbl,
  .foot-tbl { width: 100%; border-collapse: collapse; table-layout: fixed; }

  .hero-spacer { width: 20mm; }
  .hero-main { text-align: center; vertical-align: top; }
  .hero-photo { width: 22mm; text-align: right; vertical-align: top; }
  .agency-logo { width: 10mm; height: 10mm; object-fit: contain; display: inline-block; }
  .agency-name {
    color: #1684dc;
    font-size: 6.6pt;
    font-weight: bold;
    line-height: 1.15;
    margin-top: 0.4mm;
    white-space: nowrap;
  }
  .slip-title {
    display: inline-block;
    margin-top: 1mm;
    padding: 0.9mm 1.6mm 1mm;
    background: #238fe9;
    border-radius: 3pt;
    color: #fff;
    font-size: 6.3pt;
    font-weight: bold;
    line-height: 1;
    white-space: nowrap;
  }
  .passport,
  .passport-ph {
    width: 18mm;
    height: 20mm;
    border: 0.6pt solid #c7ccd1;
    display: inline-block;
  }
  .passport { object-fit: cover; }
  .passport-ph { background: #f1f3f5; text-align: center; color: #98a1aa; padding-top: 7.4mm; font-size: 4.5pt; }

  .summary-tbl { margin-top: 0.8mm; margin-bottom: 0.8mm; }
  .summary-info { width: 58%; vertical-align: middle; padding-left: 0.2mm; }
  .summary-qr { width: 19%; text-align: center; vertical-align: middle; }
  .summary-space { width: 23%; }
  .summary-line { margin-bottom: 0.45mm; }
  .summary-line:last-child { margin-bottom: 0; }
  .summary-label { font-weight: bold; color: #20252b; }
  .summary-value { font-weight: bold; color: #2f3439; }
  .qr-img { width: 14mm; height: 14mm; display: inline-block; }

  .details-tbl { border: 0.65pt solid #747b82; }
  .details-tbl td {
    border-right: 0.55pt solid #92989e;
    border-bottom: 0.55pt solid #92989e;
    padding: 0.65mm 1mm;
    height: 5.4mm;
    vertical-align: middle;
    font-size: 6.3pt;
    white-space: nowrap;
    overflow: hidden;
  }
  .details-tbl tr:last-child td { border-bottom: none; }
  .details-tbl td:last-child { border-right: none; }
  .details-label { font-weight: bold; color: #30353a; }
  .details-value { color: #343a40; }
  .two-col-left { width: 50%; }
  .two-col-right { width: 50%; }

  .coverage-line {
    position: absolute;
    right: 2.4mm;
    bottom: 4.7mm;
    left: 2.4mm;
    padding: 0;
    color: #4e555c;
    font-size: 5.5pt;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
  }
  .coverage-line strong { color: #30353a; }
  .status-approved { color: #15743c; font-weight: bold; }
  .status-pending { color: #a15c00; font-weight: bold; }

  .slip-foot {
    position: absolute;
    right: 2.4mm;
    bottom: 1.2mm;
    left: 2.4mm;
    border-top: 0.55pt solid #a8adb3;
    padding-top: 0.45mm;
    color: #555b61;
    font-size: 4.9pt;
    white-space: nowrap;
    overflow: hidden;
  }
  .foot-left { width: 27%; }
  .foot-right { width: 73%; text-align: right; }

  .page-break { page-break-after: always; }
</style>
</head>
<body>

@php
  $batchRef = 'NGSCHA-BATCH-' . $generatedAt->format('Ymd-His');
  $agencyLogoPath = null;
  $logoCandidates = [
    [public_path('logo-slip.jpg'), 'image/jpeg'],
    [public_path('logo.png'), 'image/png'],
  ];

  foreach ($logoCandidates as [$logoFilePath, $logoMimeType]) {
    if (!file_exists($logoFilePath)) {
      continue;
    }

    $logoBytes = @file_get_contents($logoFilePath);
    if ($logoBytes === false) {
      continue;
    }

    $agencyLogoPath = 'data:' . $logoMimeType . ';base64,' . base64_encode($logoBytes);
    break;
  }
@endphp

@foreach($enrollees->chunk(4) as $pageChunk)
  <table class="slip-grid">
    @foreach($pageChunk->chunk(2) as $rowChunk)
      <tr class="slip-grid-row">
      @foreach($rowChunk as $enrollee)
      @php
        $isApproved = !empty($enrollee->approval_date);
        $statusText = $isApproved
          ? 'Approved ' . optional($enrollee->approval_date)->format('d M Y')
          : ucfirst(strtolower($enrollee->status_label ?? 'Pending'));
        $statusClass = $isApproved ? 'status-approved' : 'status-pending';
        $coverageStart = optional($enrollee->coverage_start_date)->format('d M Y') ?: 'Pending';
        $coverageEnd = $enrollee->coverage_end_date
          ? optional($enrollee->coverage_end_date)->format('d M Y')
          : 'No Expiry';
        $photoSrc = $enrollee->pdf_photo_src ?? null;
        $sex = (int) $enrollee->sex === 1 ? 'Male' : ((int) $enrollee->sex === 2 ? 'Female' : 'N/A');
        $enrolledAt = $enrollee->enrollment_date ?: $enrollee->created_at;
        $programmeSearch = strtolower(trim(implode(' ', [
          $enrollee->fundingType->name ?? '',
          $enrollee->insuranceProgramme->code ?? '',
          $enrollee->insuranceProgramme->name ?? '',
        ])));
        $isBhcpf = str_contains($programmeSearch, 'bhcpf')
          || str_contains($programmeSearch, 'basic healthcare provision fund');
        $programmeLabel = $isBhcpf
          ? 'BHCPF'
          : ($enrollee->insuranceProgramme->name ?? 'NiCare');
        $schemeId = $enrollee->cno
          ?: (($enrollee->legacy_enrollee_id ?? null) !== ($enrollee->enrollee_id ?? null)
            ? $enrollee->legacy_enrollee_id
            : null);
        $category = $enrollee->vulnerableGroup->name
          ?? $enrollee->enrolleeCategory->name
          ?? 'N/A';
        $plan = $enrollee->premiumPlan->name
          ?? $enrollee->benefitPackage->name
          ?? 'N/A';
        $qrSrc = $enrollee->pdf_qr_src ?? null;
      @endphp

      <td class="slip-grid-cell">
      <div class="slip">
        <table class="hero-tbl">
          <tr>
            <td class="hero-spacer"></td>
            <td class="hero-main">
              @if($agencyLogoPath)
                <img src="{{ $agencyLogoPath }}" alt="NGSCHA Logo" class="agency-logo">
              @endif
              <div class="agency-name">Niger State Contributory Health Agency - NiCare</div>
              <div class="slip-title">{{ $programmeLabel }} - Enrolment Slip</div>
            </td>
            <td class="hero-photo">
              @if($photoSrc)
                <img src="{{ $photoSrc }}" alt="Passport photograph" class="passport">
              @else
                <div class="passport-ph">PASSPORT</div>
              @endif
            </td>
          </tr>
        </table>

        <table class="summary-tbl">
          <tr>
            <td class="summary-info">
              <div class="summary-line"><span class="summary-label">NiCare Number:</span> <span class="summary-value">{{ $enrollee->enrollee_id ?: 'N/A' }}</span></div>
              @if($isBhcpf && $schemeId)
                <div class="summary-line"><span class="summary-label">BHCPF ID:</span> <span class="summary-value">{{ $schemeId }}</span></div>
              @endif
              <div class="summary-line"><span class="summary-label">Date Enrolled:</span> <span class="summary-value">{{ optional($enrolledAt)->format('D d M, Y') ?: 'N/A' }}</span></div>
              <div class="summary-line"><span class="summary-label">Category:</span> <span class="summary-value">{{ $category }}</span></div>
            </td>
            <td class="summary-qr">
              @if($qrSrc)
                <img src="{{ $qrSrc }}" alt="" title="QR code for {{ $enrollee->enrollee_id }}" class="qr-img">
              @endif
            </td>
            <td class="summary-space"></td>
          </tr>
        </table>

        <table class="details-tbl">
          <tr>
            <td class="two-col-left"><span class="details-label">Name:</span> <span class="details-value">{{ $enrollee->full_name ?: 'N/A' }}</span></td>
            <td class="two-col-right"><span class="details-label">Sex:</span> <span class="details-value">{{ $sex }}</span></td>
          </tr>
          <tr>
            <td colspan="2"><span class="details-label">Provider/Hospital:</span> <span class="details-value">{{ $enrollee->facility->name ?? 'N/A' }}</span></td>
          </tr>
          <tr>
            <td><span class="details-label">Date of Birth:</span> <span class="details-value">{{ optional($enrollee->date_of_birth)->format('d/m/Y') ?: 'N/A' }}</span></td>
            <td><span class="details-label">Phone No.:</span> <span class="details-value">{{ $enrollee->phone ?: 'N/A' }}</span></td>
          </tr>
          <tr>
            <td><span class="details-label">LGA:</span> <span class="details-value">{{ strtoupper($enrollee->lga->name ?? 'N/A') }}</span></td>
            <td><span class="details-label">Ward:</span> <span class="details-value">{{ strtoupper($enrollee->ward->name ?? 'N/A') }}</span></td>
          </tr>
          <tr>
            <td colspan="2"><span class="details-label">Address:</span> <span class="details-value">{{ $enrollee->address ?: ($enrollee->village ?: 'N/A') }}</span></td>
          </tr>
          <tr>
            <td><span class="details-label">NOK Name:</span> <span class="details-value">{{ $enrollee->nok_name ?: 'N/A' }}</span></td>
            <td><span class="details-label">NOK Phone:</span> <span class="details-value">{{ $enrollee->nok_phone_number ?: 'N/A' }}</span></td>
          </tr>
        </table>

        <div class="coverage-line">
          <strong>Plan:</strong> {{ $plan }} &nbsp; | &nbsp;
          <strong>Coverage:</strong> {{ $coverageStart }} - {{ $coverageEnd }} &nbsp; | &nbsp;
          <strong>Status:</strong> <span class="{{ $statusClass }}">{{ $statusText }}</span>
        </div>

        <div class="slip-foot">
          <table class="foot-tbl">
            <tr>
              <td class="foot-left">For enquiries: call 08162653801</td>
              <td class="foot-right">Generated on {{ $generatedAt->format('d/m/Y h:i A') }} | By: {{ $generatedBy->name ?? 'NiCare Health ICT Dept.' }} | {{ $batchRef }}</td>
            </tr>
          </table>
        </div>
      </div>
      </td>
      @endforeach
      @if($rowChunk->count() === 1)
        <td class="slip-grid-cell"></td>
      @endif
      </tr>
    @endforeach
  </table>
  @if(!$loop->last)
    <div class="page-break"></div>
  @endif
@endforeach

</body>
</html>
