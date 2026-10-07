@extends('reports.layout')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th>Code</th>
                <th>Product</th>
                <th class="num">Opening</th>
                <th class="num">In (purchases)</th>
                <th class="num">Out (distributions)</th>
                <th class="num">Closing</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td><strong>{{ $row['code'] }}</strong></td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format($row['opening']) }}</td>
                    <td class="num">{{ $row['in'] ? '+'.number_format($row['in']) : '0' }}</td>
                    <td class="num">{{ $row['out'] ? '−'.number_format($row['out']) : '0' }}</td>
                    <td class="num {{ $row['closing'] < 0 ? 'neg' : '' }}"><strong>{{ number_format($row['closing']) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td>Total reams</td>
                <td class="num">{{ number_format($totals['opening']) }}</td>
                <td class="num">+{{ number_format($totals['in']) }}</td>
                <td class="num">−{{ number_format($totals['out']) }}</td>
                <td class="num">{{ number_format($totals['closing']) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="foot">Opening = stock before the period. Voided purchases and distributions are excluded.</p>
@endsection
