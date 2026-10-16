<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Collection of Enrolment Slips - Form C</title>
  <style>
    @page { margin: 9mm 10mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
    .form { width: 100%; border-collapse: collapse; table-layout: auto; font-size: 8pt; line-height: 1.1; }
    .new-facility { page-break-before: always; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th, td { border: 0.6pt solid #555; padding: 4pt 3pt; vertical-align: middle; overflow-wrap: break-word; }
    th { font-weight: normal; text-align: center; font-size: 8pt; }
    .heading { border: 0; text-align: center; padding: 0 0 6pt; }
    .logo { height: 9mm; width: 9mm; }
    .agency { font-size: 11pt; line-height: 1.2; }
    .title { font-size: 10.5pt; text-decoration: underline; line-height: 1.2; }
    .details { border: 0; border-top: 0.7pt solid #888; padding: 6pt 0 7pt; text-align: left; font-weight: bold; line-height: 1.25; }
    .batch { font-size: 7.5pt; font-weight: normal; }
    .sn, .nin, .identifier, .phone { text-align: center; }
    .sn { width: 3%; }
    .name { width: 21%; }
    .nin { width: 11%; }
    .identifier { width: 13%; }
    .phone { width: 11%; }
    .address { width: 23%; }
    .collected, .sign { width: 9%; }
    .signature { height: 22pt; }
  </style>
</head>
<body>
@php
  $logo = null;
  foreach ([[public_path('logo-slip.jpg'), 'image/jpeg'], [public_path('logo.png'), 'image/png']] as [$path, $mime]) {
      if (is_file($path) && ($bytes = @file_get_contents($path)) !== false) {
          $logo = 'data:'.$mime.';base64,'.base64_encode($bytes);
          break;
      }
  }
@endphp
@foreach($groups as $enrollees)
  @php($facility = $enrollees->first()->facility)
  <table class="form {{ $loop->first ? '' : 'new-facility' }}">
    <thead>
      <tr><td colspan="8" class="heading">
        @if($logo)<img class="logo" src="{{ $logo }}" alt="NiCare logo"><br>@endif
        <div class="agency">NIGER STATE CONTRIBUTORY HEALTH AGENCY (NiCare)</div>
        <div class="title">COLLECTION OF ENROLMENT SLIP BY ENROLEES - FORM C</div>
      </td></tr>
      <tr><td colspan="8" class="details">
        LGA: {{ $facility?->lga?->name ?? 'Not assigned' }}<br>
        WARD: {{ $facility?->ward?->name ?? 'Not assigned' }}<br>
        FACILITY: {{ $facility?->name ?? 'Not assigned' }}<br>
        Date Printed: {{ $printedAt->format('d/m/Y') }} &nbsp; | &nbsp; Total enrolments: {{ $enrollees->count() }}
        @if($batchId)<br><span class="batch">Printing exercise: {{ $batchId }}</span>@endif
      </td></tr>
      <tr>
        <th class="sn">SN</th><th class="name">NAME</th><th class="nin">NIN</th><th class="identifier">ENROLEE ID</th><th class="phone">PHONE</th><th class="address">ADDRESS</th><th class="collected">DATE<br>COLLECTED</th><th class="sign">SIGN</th>
      </tr>
    </thead>
    <tbody>
      @foreach($enrollees as $enrollee)
        <tr>
          <td class="sn">{{ $loop->iteration }}</td>
          <td class="name">{{ trim(implode(' ', array_filter([$enrollee->first_name, $enrollee->middle_name, $enrollee->last_name]))) }}</td>
          <td class="nin">{{ $enrollee->nin }}</td>
          <td class="identifier">{{ $enrollee->enrollee_id }}</td>
          <td class="phone">{{ $enrollee->phone }}</td>
          <td class="address">{{ $enrollee->address }}</td>
          <td class="signature collected"></td><td class="signature sign"></td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endforeach
</body>
</html>
