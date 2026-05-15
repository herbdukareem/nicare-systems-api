@extends('reports.base')

@section('content')
<h2>Executive Summary</h2>

<div class="kpi-grid">
    @foreach ($data['kpis'] ?? [] as $kpi)
    <div class="kpi-card">
        <div class="kpi-label">{{ $kpi['label'] }}</div>
        <div class="kpi-value">{{ $kpi['value'] }}</div>
    </div>
    @endforeach
</div>

@if (!empty($data['claims_by_status']))
<h2>Claims by Status</h2>
<table>
    <thead>
        <tr>
            <th>Status</th>
            <th>Count</th>
            <th>Total Amount (NGN)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data['claims_by_status'] as $row)
        <tr>
            <td>{{ $row['status'] }}</td>
            <td>{{ number_format($row['count']) }}</td>
            <td>{{ number_format($row['total_amount'], 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@endsection
