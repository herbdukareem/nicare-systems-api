<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  @page { size: A4; margin: 12mm 12mm 12mm 12mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Helvetica, Arial, sans-serif; color: #111; font-size: 9pt; line-height: 1.4; }

  .letterhead { width: 100%; border-bottom: 2pt solid #0d3b6e; padding-bottom: 4mm; margin-bottom: 4mm; }
  .lh-tbl { width: 100%; border-collapse: collapse; }
  .lh-logo { width: 18mm; text-align: center; vertical-align: middle; }
  .lh-logo img { width: 16mm; height: 16mm; }
  .lh-center { vertical-align: middle; text-align: center; padding: 0 3mm; }
  .lh-state { font-size: 15pt; font-weight: bold; color: #0d3b6e; letter-spacing: 1px; text-transform: uppercase; line-height: 1.1; }
  .lh-agency { font-size: 8.5pt; color: #c62828; font-weight: bold; letter-spacing: 0.3px; margin-top: 1mm; }
  .lh-unit { font-size: 7pt; color: #555; margin-top: 0.5mm; }

  .cover-title-box {
    text-align: center;
    border: 2pt solid #0d3b6e;
    padding: 5mm 6mm;
    margin: 6mm 0;
    background: #eef2ff;
  }
  .cover-title { font-size: 13pt; font-weight: bold; color: #0d3b6e; text-transform: uppercase; letter-spacing: 0.8px; }
  .cover-subtitle { font-size: 8.5pt; color: #444; margin-top: 1.5mm; }

  .stats-tbl { width: 100%; border-collapse: collapse; margin: 5mm 0; }
  .stats-tbl td { border: 1pt solid #c8d3e8; padding: 4mm 3mm; text-align: center; }
  .stat-n { font-size: 18pt; font-weight: bold; color: #0d3b6e; line-height: 1; }
  .stat-n-green { color: #16a34a; }
  .stat-n-amber { color: #d97706; }
  .stat-l { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.3px; color: #666; margin-top: 1mm; }

  .meta-tbl { width: 100%; border-collapse: collapse; margin: 4mm 0; }
  .meta-tbl td { padding: 2.5mm 2mm; border-bottom: 0.5pt solid #d0d8e8; font-size: 8.5pt; }
  .meta-lbl { color: #555; width: 40%; text-transform: uppercase; font-size: 7.5pt; letter-spacing: 0.2px; }
  .meta-val { font-weight: bold; color: #0d3b6e; }

  .notice-box {
    border-left: 3pt solid #0d3b6e;
    background: #f4f6ff;
    padding: 3mm 4mm;
    font-size: 7.5pt;
    color: #333;
    margin: 5mm 0;
    line-height: 1.5;
  }

  .sign-tbl { width: 100%; border-collapse: collapse; margin-top: 12mm; }
  .sign-tbl td { padding: 0 4mm; }
  .sign-line { border-bottom: 1pt solid #444; height: 10mm; }
  .sign-lbl { font-size: 7pt; color: #444; margin-top: 1mm; }
  .sign-sub { font-size: 6pt; color: #888; margin-top: 0.5mm; }

  .cover-foot {
    border-top: 1pt solid #0d3b6e;
    margin-top: 8mm;
    padding-top: 2.5mm;
    font-size: 7pt;
    color: #555;
  }
  .cf-tbl { width: 100%; border-collapse: collapse; }

  .page-break { page-break-after: always; }

  .slip-grid { width: 100%; border-collapse: separate; border-spacing: 3mm 3mm; table-layout: fixed; }
  .slip-grid-cell { width: 50%; vertical-align: top; }
  .slip { border: 1pt solid #0d3b6e; page-break-inside: avoid; }

  .slip-hdr { background: #0d3b6e; color: white; padding: 1.6mm 2.2mm; }
  .slip-hdr-tbl { width: 100%; border-collapse: collapse; }
  .slip-hdr-logo-cell { width: 12mm; vertical-align: middle; }
  .slip-hdr-logo {
    width: 10mm;
    height: 10mm;
    display: block;
    object-fit: contain;
  }
  .slip-hdr-main { vertical-align: middle; padding: 0 1.4mm; }
  .slip-hdr-meta { width: 1mm; vertical-align: middle; text-align: right; }
  .slip-hdr-title { font-size: 6.8pt; font-weight: bold; letter-spacing: 0.15px; text-transform: uppercase; }
  .slip-hdr-sub { font-size: 5.3pt; color: rgba(255,255,255,0.78); margin-top: 0.3mm; }

  .slip-body-tbl { width: 100%; border-collapse: collapse; }
  .slip-fields-td { vertical-align: top; padding: 1.8mm 2.2mm 1.5mm; }
  .slip-photo-td { width: 22mm; vertical-align: top; padding: 1.8mm 2mm; border-left: 0.5pt solid #d8e0ec; text-align: center; }

  .photo-box { width: 17mm; height: 22mm; border: 1pt solid #b0bcc8; background: #edf1f7; display: block; margin: 0 auto; }
  .photo-lbl { font-size: 4.8pt; color: #888; margin-top: 1mm; text-align: center; }

  .nicare-badge {
    margin-top: 1.8mm;
    border: 1pt solid #0d3b6e;
    padding: 1.2mm;
    text-align: center;
  }
  .nicare-badge-lbl { font-size: 4.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.2px; }
  .nicare-badge-val { font-size: 6.6pt; font-weight: bold; color: #0d3b6e; margin-top: 0.35mm; }

  .field-tbl { width: 100%; border-collapse: collapse; }
  .field-tbl tr td { padding: 0.95mm 0.7mm; border-bottom: 0.5pt solid #e8ecf4; vertical-align: top; }
  .field-tbl tr:last-child td { border-bottom: none; }
  .f-lbl { font-size: 5.8pt; color: #555; text-transform: uppercase; letter-spacing: 0.1px; width: 37%; white-space: nowrap; }
  .f-val { font-size: 6.5pt; font-weight: bold; color: #111; }
  .f-val-id { font-size: 7.6pt; font-weight: bold; color: #0d3b6e; }
  .f-approved { color: #15803d; font-weight: bold; }
  .f-pending { color: #b45309; font-weight: bold; }

  .slip-cert {
    border-top: 0.5pt solid #d0d8e8;
    background: #f7f9ff;
    padding: 1.4mm 2.2mm;
    font-size: 5.7pt;
    color: #333;
    font-style: italic;
    line-height: 1.25;
  }

  .slip-foot { background: #0d3b6e; color: rgba(255,255,255,0.85); padding: 1mm 2.2mm; font-size: 5.2pt; }
  .sf-tbl { width: 100%; border-collapse: collapse; }
  .sf-right { text-align: right; }
</style>
</head>
<body>

@php
  $batchRef = 'NGSCHA-BATCH-' . $generatedAt->format('Ymd-His');
  $total = $enrollees->count();
  $approved = $enrollees->filter(fn ($e) => !empty($e->approval_date))->count();
  $pending = $total - $approved;
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
  $slipLogoPath = $agencyLogoPath;
@endphp

<div class="letterhead">
  <table class="lh-tbl">
    <tr>
      <td class="lh-logo"></td>
      <td class="lh-center">
        <div class="lh-state">Niger State</div>
        <div class="lh-agency">Contributory Health Agency (NGSCHA)</div>
        <div class="lh-unit">Health Insurance Management System - NiCare</div>
      </td>
      <td class="lh-logo">
        @if($agencyLogoPath)
          <img src="{{ $agencyLogoPath }}" alt="NGSCHA Logo">
        @endif
      </td>
    </tr>
  </table>
</div>

<div class="cover-title-box">
  <div class="cover-title">Health Insurance Enrollment Batch Slip</div>
  <div class="cover-subtitle">Official Record of Enrolled Beneficiaries - For Administrative Use Only</div>
</div>

<table class="stats-tbl">
  <tr>
    <td>
      <div class="stat-n">{{ $total }}</div>
      <div class="stat-l">Total Enrollees</div>
    </td>
    <td>
      <div class="stat-n stat-n-green">{{ $approved }}</div>
      <div class="stat-l">Approved</div>
    </td>
    <td>
      <div class="stat-n stat-n-amber">{{ $pending }}</div>
      <div class="stat-l">Pending</div>
    </td>
    <td>
      <div class="stat-n" style="font-size:11pt">{{ $generatedAt->format('d M Y') }}</div>
      <div class="stat-l">Date Generated</div>
    </td>
  </tr>
</table>

<table class="meta-tbl">
  <tr>
    <td class="meta-lbl">Batch Reference</td>
    <td class="meta-val">{{ $batchRef }}</td>
  </tr>
  <tr>
    <td class="meta-lbl">Date and Time Generated</td>
    <td class="meta-val">{{ $generatedAt->format('l, d F Y \a\t H:i') }}</td>
  </tr>
  <tr>
    <td class="meta-lbl">Prepared By</td>
    <td class="meta-val">{{ $generatedBy->name ?? 'System' }}</td>
  </tr>
  <tr>
    <td class="meta-lbl">Approval Status Filter</td>
    <td class="meta-val">{{ ucfirst($filters['approval_status'] ?? 'All') }}</td>
  </tr>
  @if(!empty($filters['date_from']) || !empty($filters['date_to']))
    <tr>
      <td class="meta-lbl">Date Range Applied</td>
      <td class="meta-val">{{ $filters['date_from'] ?? '-' }} to {{ $filters['date_to'] ?? '-' }}</td>
    </tr>
  @endif
</table>

<div class="notice-box">
  This document is an official record generated by the Niger State Contributory Health Agency (NGSCHA)
  Health Insurance Management System. It contains personally identifiable information and must be handled
  in accordance with the NGSCHA data protection policy. Unauthorised disclosure, reproduction, or
  alteration of this document is strictly prohibited and may attract legal sanctions.
</div>

<table class="sign-tbl">
  <tr>
    <td style="width:44%">
      <div class="sign-line"></div>
      <div class="sign-lbl">Authorised Officer - Signature and Designation</div>
      <div class="sign-sub">Niger State Contributory Health Agency</div>
    </td>
    <td style="width:12%"></td>
    <td style="width:44%">
      <div class="sign-line"></div>
      <div class="sign-lbl">Official Stamp and Date</div>
      <div class="sign-sub">NGSCHA Official Seal</div>
    </td>
  </tr>
</table>

<div class="cover-foot">
  <table class="cf-tbl">
    <tr>
      <td>Niger State Contributory Health Agency (NGSCHA) - NiCare Health Insurance System</td>
      <td style="text-align:right">Ref: {{ $batchRef }} | Total: {{ $total }} enrollees</td>
    </tr>
  </table>
</div>

<div class="page-break"></div>

@foreach($enrollees->chunk(4) as $pageChunk)
  <table class="slip-grid">
    @foreach($pageChunk->chunk(2) as $rowChunk)
      <tr>
        @foreach($rowChunk as $enrollee)
          @php
            $isApproved = !empty($enrollee->approval_date);
            $statusText = $isApproved
              ? 'APPROVED - ' . optional($enrollee->approval_date)->format('d M Y')
              : strtoupper($enrollee->status_label ?? 'PENDING');
            $statusClass = $isApproved ? 'f-approved' : 'f-pending';
            $coverageStart = optional($enrollee->coverage_start_date)->format('d M Y') ?: 'Pending';
            $coverageEnd = $enrollee->coverage_end_date
              ? optional($enrollee->coverage_end_date)->format('d M Y')
              : 'No Expiry';
            $photoSrc = $enrollee->pdf_photo_src ?? null;
          @endphp
          <td class="slip-grid-cell">
            <div class="slip">
              <div class="slip-hdr">
                <table class="slip-hdr-tbl">
                  <tr>
                    <td class="slip-hdr-logo-cell">
                      @if($slipLogoPath)
                        <img src="{{ $slipLogoPath }}" alt="NGSCHA Logo" class="slip-hdr-logo">
                      @endif
                    </td>
                    <td class="slip-hdr-main">
                      <div class="slip-hdr-title">Niger State Contributory Health Agency (NGSCHA)</div>
                      <div class="slip-hdr-sub">NiCare Health Insurance Enrollment Confirmation Slip</div>
                    </td>
                    <td class="slip-hdr-meta"></td>
                  </tr>
                </table>
              </div>

              <table class="slip-body-tbl">
                <tr>
                  <td class="slip-fields-td">
                    <table class="field-tbl">
                      <tr>
                        <td class="f-lbl">NiCare No.</td>
                        <td class="f-val-id">{{ $enrollee->enrollee_id ?: 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Full Name</td>
                        <td class="f-val">{{ strtoupper($enrollee->full_name ?: 'N/A') }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">NIN</td>
                        <td class="f-val">{{ $enrollee->nin ?: 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Sex / Date of Birth</td>
                        <td class="f-val">
                          {{ (int) $enrollee->sex === 1 ? 'Male' : ((int) $enrollee->sex === 2 ? 'Female' : 'N/A') }}
                          / {{ optional($enrollee->date_of_birth)->format('d M Y') ?: 'N/A' }}
                        </td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Phone</td>
                        <td class="f-val">{{ $enrollee->phone ?: 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Programme</td>
                        <td class="f-val">{{ $enrollee->insuranceProgramme->name ?? 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Category / Plan</td>
                        <td class="f-val">{{ $enrollee->enrolleeCategory->name ?? 'N/A' }} / {{ $enrollee->premiumPlan->name ?? 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Facility (HCP Code)</td>
                        <td class="f-val">{{ $enrollee->facility->name ?? 'N/A' }} ({{ $enrollee->facility->hcp_code ?? 'N/A' }})</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">LGA / Ward</td>
                        <td class="f-val">{{ $enrollee->lga->name ?? 'N/A' }} / {{ $enrollee->ward->name ?? 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Funding / Benefactor</td>
                        <td class="f-val">{{ $enrollee->fundingType->name ?? 'N/A' }} / {{ $enrollee->benefactor->name ?? 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Enrollment Phase</td>
                        <td class="f-val">{{ $enrollee->enrollmentPhase->name ?? 'N/A' }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Coverage Period</td>
                        <td class="f-val">{{ $coverageStart }} - {{ $coverageEnd }}</td>
                      </tr>
                      <tr>
                        <td class="f-lbl">Enrollment Status</td>
                        <td class="f-val {{ $statusClass }}">{{ $statusText }}</td>
                      </tr>
                    </table>
                  </td>

                  <td class="slip-photo-td">
                    @if($photoSrc)
                      <img src="{{ $photoSrc }}" alt="Photo" style="width:17mm; height:22mm; object-fit:cover; border:1pt solid #b0bcc8;">
                    @else
                      <div class="photo-box"></div>
                    @endif
                    <div class="photo-lbl">Passport Photo</div>

                    <div class="nicare-badge">
                      <div class="nicare-badge-lbl">NiCare No.</div>
                      <div class="nicare-badge-val">{{ $enrollee->enrollee_id ?: 'N/A' }}</div>
                    </div>
                  </td>
                </tr>
              </table>

              <div class="slip-cert">
                This certifies that the above-named individual has been duly enrolled under the NGSCHA Health Insurance Scheme.
              </div>

              <div class="slip-foot">
                <table class="sf-tbl">
                  <tr>
                    <td>Batch: {{ $batchRef }}</td>
                    <td class="sf-right">{{ $generatedAt->format('d M Y H:i') }} | {{ $generatedBy->name ?? 'System' }}</td>
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
