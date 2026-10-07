@extends('reports.layout')

@php
    $peso = fn ($v) => ($v < 0 ? '-' : '').'₱'.number_format(abs((float) $v), 2);
@endphp

@section('content')
    <table class="summary">
        <tr>
            <td>
                <div class="label">Reseller</div>
                <div class="value">{{ $reseller->name }}</div>
                <div class="muted">{{ collect([$reseller->location, $reseller->contact])->filter()->implode(' · ') }}</div>
            </td>
            <td><div class="label">Opening balance</div><div class="value">{{ $peso($opening) }}</div></td>
            <td><div class="label">Charges</div><div class="value">{{ $peso($total_debit) }}</div></td>
            <td><div class="label">Payments</div><div class="value">{{ $peso($total_credit) }}</div></td>
            <td><div class="label">Balance due</div><div class="value {{ $closing < 0 ? 'neg' : '' }}">{{ $peso($closing) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 90px">Date</th>
                <th>Description</th>
                <th class="num">Charges</th>
                <th class="num">Payments</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td></td>
                <td class="muted">Opening balance</td>
                <td></td>
                <td></td>
                <td class="num">{{ $peso($opening) }}</td>
            </tr>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="num">{{ $row['debit'] ? $peso($row['debit']) : '' }}</td>
                    <td class="num">{{ $row['credit'] ? $peso($row['credit']) : '' }}</td>
                    <td class="num">{{ $peso($row['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="text-align:center; padding: 18px">No transactions in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td>Totals · closing balance</td>
                <td class="num">{{ $peso($total_debit) }}</td>
                <td class="num">{{ $peso($total_credit) }}</td>
                <td class="num">{{ $peso($closing) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="foot">Only confirmed remittances are included. Voided entries are excluded.</p>
@endsection
