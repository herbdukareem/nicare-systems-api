<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  @page {
    size: 85.6mm 54mm;
    margin: 0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    color: #0f172a;
    background: #ffffff;
  }

  /* FRONT CARD */
  .front {
    width: 85.6mm;
    background: #ffffff;
    page-break-after: always;
  }

  .hdr { padding: 1.8mm 2.5mm 1.5mm; }
  .hdr-tbl { width: 100%; border-collapse: collapse; }
  .hdr-logo { width: 13mm; text-align: center; vertical-align: middle; }
  .hdr-logo img { width: 12mm; height: 12mm; }
  .hdr-txt { vertical-align: middle; text-align: center; padding: 0 1mm; }
  .hdr-state {
    font-size: 11.5pt;
    font-weight: bold;
    color: #1144aa;
    letter-spacing: 0.3px;
    line-height: 1.1;
  }
  .hdr-agency {
    font-size: 5pt;
    color: #cc0000;
    font-weight: bold;
    line-height: 1.3;
    margin-top: 0.5mm;
  }

  .badge-bar { background: #1565c0; text-align: center; padding: 0.8mm 0; }
  .badge {
    display: inline;
    background: #2196f3;
    color: #ffffff;
    font-size: 5pt;
    font-weight: bold;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    padding: 0.8mm 5mm;
    border-radius: 8pt;
  }

  .body { padding: 1.8mm 2.5mm 1mm; }
  .body-tbl { width: 100%; border-collapse: collapse; }
  .fields-td { vertical-align: top; }
  .photo-td { width: 21mm; vertical-align: top; text-align: right; }

  .fl { border-collapse: collapse; }
  .fl td { padding-bottom: 1.4mm; vertical-align: top; }
  .fl-lbl { font-size: 6pt; font-weight: bold; color: #1a237e; white-space: nowrap; padding-right: 0.8mm; }
  .fl-col { font-size: 6pt; font-weight: bold; color: #1a237e; padding-right: 1mm; }
  .fl-val { font-size: 6pt; color: #111111; }
  .fl-val-bold { font-size: 6pt; color: #111111; font-weight: bold; }

  .passport { width: 19mm; height: 23mm; object-fit: cover; border: 0.5mm solid #90caf9; }
  .passport-ph { width: 19mm; height: 23mm; background: #eceff1; border: 0.5mm solid #90caf9; }

  /* BACK CARD */
  .back {
    width: 85.6mm;
    background: #ffffff;
    page-break-after: avoid;
  }
  .back-inner { padding: 3mm 3mm 2.5mm; }
  .back-tbl { width: 100%; border-collapse: collapse; }
  .back-txt { vertical-align: top; padding-right: 2mm; padding-top: 2mm; }
  .back-qr  { width: 22mm; vertical-align: top; text-align: right; padding-top: 2mm; }

  .enq-line { font-size: 5.5pt; color: #333; line-height: 1.45; }
  .phone    { font-size: 9pt; font-weight: bold; color: #0d3b6e; margin-top: 1.5mm; }
  .web      { font-size: 5pt; color: #1565c0; margin-top: 1.8mm; }
  .qr-img   { width: 20mm; height: 20mm; }
</style>
</head>
<body>

{{-- ═══════════ FRONT ═══════════ --}}
<div class="front">

  <div class="hdr">
    <table class="hdr-tbl">
      <tr>
        <td class="hdr-logo">
          @if(file_exists(public_path('nigeria-coat-of-arms.jpg')))
            <img src="file://{{ public_path('state-logo.png') }}" alt="Coat of Arms">
          @endif
        </td>
        <td class="hdr-txt">
          <div class="hdr-state">NIGER STATE</div>
          <div class="hdr-agency">CONTRIBUTORY HEALTH AGENCY (NGSCHA)</div>
        </td>
        <td class="hdr-logo">
          @if(file_exists(public_path('logo.png')))
            <img src="file://{{ public_path('logo.png') }}" alt="NGSCHA">
          @endif
        </td>
      </tr>
    </table>
  </div>

  <div class="badge-bar">
    <span class="badge">NiCare ENROLEE ID CARD</span>
  </div>

  <div class="body">
    <table class="body-tbl">
      <tr>
        <td class="fields-td">
          <table class="fl">
            <tr>
              <td class="fl-lbl">NICARE NO.</td>
              <td class="fl-col">:</td>
              <td class="fl-val-bold">{{ $enrollee->enrollee_id ?: 'N/A' }}</td>
            </tr>
            <tr>
              <td class="fl-lbl">NAME</td>
              <td class="fl-col">:</td>
              <td class="fl-val">{{ $enrollee->full_name ?: 'N/A' }}</td>
            </tr>
            <tr>
              <td class="fl-lbl">SEX</td>
              <td class="fl-col">:</td>
              <td class="fl-val">{{ (int) $enrollee->sex === 1 ? 'M' : ((int) $enrollee->sex === 2 ? 'F' : 'N/A') }}</td>
            </tr>
            <tr>
              <td class="fl-lbl">PROVIDER</td>
              <td class="fl-col">:</td>
              <td class="fl-val">{{ $enrollee->facility->name ?? 'N/A' }}</td>
            </tr>
            <tr>
              <td class="fl-lbl">PLAN</td>
              <td class="fl-col">:</td>
              <td class="fl-val">{{ $enrollee->premiumPlan->name ?? ($enrollee->benefitPackage->name ?? 'N/A') }}</td>
            </tr>
          </table>
        </td>
          <td class="photo-td">
            @php
              $photoSrc = $enrollee->pdf_photo_src ?? null;
            @endphp
          @if($photoSrc)
            <img class="passport" src="{{ $photoSrc }}" alt="Photo">
          @else
            <div class="passport-ph"></div>
          @endif
        </td>
      </tr>
    </table>
  </div>

</div>

{{-- ═══════════ BACK ═══════════ --}}
<div class="back">
  <div class="back-inner">
    <table class="back-tbl">
      <tr>
        <td class="back-txt">
          <div class="enq-line">For Enquiries &amp; Complaints: please call NiCare agents through</div>
          <div class="phone">08162653801</div>
          <div class="web">Visit our website: nicare.nigerstate.gov.ng</div>
        </td>
        <td class="back-qr">
          @if($qrBase64)
            <img class="qr-img" src="{{ $qrBase64 }}" alt="QR Code">
          @endif
        </td>
      </tr>
    </table>
  </div>
</div>

</body>
</html>
