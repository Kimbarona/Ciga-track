@extends('reports.layout')

@php
    $peso = fn ($v) => ($v < 0 ? '-' : '').'₱'.number_format(abs((float) $v), 2);
@endphp

@section('content')
    <table class="summary">
        <tr>
            <td><div class="label">Sales</div><div class="value">{{ $peso($totals['sales']) }}</div></td>
            <td><div class="label">COGS</div><div class="value">{{ $peso($totals['cogs']) }}</div></td>
            <td><div class="label">Gross profit</div><div class="value {{ $totals['gross_profit'] < 0 ? 'neg' : '' }}">{{ $peso($totals['gross_profit']) }}</div></td>
            <td><div class="label">Expenses</div><div class="value">{{ $peso($totals['expenses']) }}</div></td>
            <td><div class="label">Net profit</div><div class="value {{ $totals['net_profit'] < 0 ? 'neg' : '' }}">{{ $peso($totals['net_profit']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Period</th>
                <th class="num">Reams</th>
                <th class="num">Sales</th>
                <th class="num">COGS</th>
                <th class="num">Gross profit</th>
                <th class="num">Expenses</th>
                <th class="num">Net profit</th>
                <th class="num">Collections</th>
                <th class="num">Cash position</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td class="num">{{ number_format($row['reams_sold']) }}</td>
                    <td class="num">{{ $peso($row['sales']) }}</td>
                    <td class="num">{{ $peso($row['cogs']) }}</td>
                    <td class="num {{ $row['gross_profit'] < 0 ? 'neg' : '' }}">{{ $peso($row['gross_profit']) }}</td>
                    <td class="num">{{ $peso($row['expenses']) }}</td>
                    <td class="num {{ $row['net_profit'] < 0 ? 'neg' : '' }}">{{ $peso($row['net_profit']) }}</td>
                    <td class="num">{{ $peso($row['collections']) }}</td>
                    <td class="num {{ $row['cash_position'] < 0 ? 'neg' : '' }}">{{ $peso($row['cash_position']) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted" style="text-align:center; padding: 18px">No transactions in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ number_format($totals['reams_sold']) }}</td>
                <td class="num">{{ $peso($totals['sales']) }}</td>
                <td class="num">{{ $peso($totals['cogs']) }}</td>
                <td class="num">{{ $peso($totals['gross_profit']) }}</td>
                <td class="num">{{ $peso($totals['expenses']) }}</td>
                <td class="num">{{ $peso($totals['net_profit']) }}</td>
                <td class="num">{{ $peso($totals['collections']) }}</td>
                <td class="num">{{ $peso($totals['cash_position']) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="foot">COGS = reams distributed × weighted average cost per product. Cash position = confirmed collections − purchases − expenses.</p>
@endsection
