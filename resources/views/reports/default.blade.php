@extends('reports.base')

@section('content')
@if (!empty($data) && is_array($data) && count($data) > 0)
    @php $columns = array_keys((array) reset($data)); @endphp
    <table>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
            <tr>
                @foreach ($columns as $col)
                    <td>{{ is_array($row) ? ($row[$col] ?? '') : ($row->$col ?? '') }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p>No data available for the selected filters.</p>
@endif
@endsection
