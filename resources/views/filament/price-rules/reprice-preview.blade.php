@php
    $money = fn ($v) => ($v < 0 ? '-' : '').'₱'.number_format(abs($v), 2);
@endphp

@if ($rows->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10">
        No existing distributions are affected by this rule.
    </div>
@else
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Reseller</th>
                    <th class="px-3 py-2 text-right">Qty</th>
                    <th class="px-3 py-2 text-right">Price</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2 text-right">Difference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 tabular-nums dark:divide-white/5">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['distribution']->date->format('M d, Y') }}</td>
                        <td class="px-3 py-2">{{ $row['distribution']->reseller->name }}</td>
                        <td class="px-3 py-2 text-right">{{ $row['distribution']->qty }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <span class="text-gray-500">{{ $money($row['old_price']) }}</span> → <strong>{{ $money($row['new_price']) }}</strong>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <span class="text-gray-500">{{ $money($row['old_total']) }}</span> → <strong>{{ $money($row['new_total']) }}</strong>
                        </td>
                        <td @class([
                            'px-3 py-2 text-right font-semibold whitespace-nowrap',
                            'text-success-600' => $row['difference'] > 0,
                            'text-danger-600' => $row['difference'] < 0,
                        ])>{{ $row['difference'] > 0 ? '+' : '' }}{{ $money($row['difference']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold tabular-nums dark:bg-white/5">
                <tr>
                    <td class="px-3 py-2" colspan="2">{{ $rows->count() }} distribution(s)</td>
                    <td class="px-3 py-2 text-right">{{ $rows->sum(fn ($r) => $r['distribution']->qty) }}</td>
                    <td></td>
                    <td class="px-3 py-2 text-right">{{ $money($rows->sum('old_total')) }} → {{ $money($rows->sum('new_total')) }}</td>
                    <td class="px-3 py-2 text-right">{{ $rows->sum('difference') > 0 ? '+' : '' }}{{ $money($rows->sum('difference')) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
