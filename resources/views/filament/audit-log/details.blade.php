@php
    use App\Filament\Resources\AuditLogs\AuditLogResource;

    $new = $activity->properties['attributes'] ?? [];
    $old = $activity->properties['old'] ?? [];
    $keys = array_unique([...array_keys($old), ...array_keys($new)]);
    $rows = $activity->properties['rows'] ?? null;
@endphp

<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div><dt class="text-gray-500">When</dt><dd class="font-medium">{{ $activity->created_at->format('M d, Y g:i A') }}</dd></div>
        <div><dt class="text-gray-500">Who</dt><dd class="font-medium">{{ $activity->causer?->name ?? 'System / import' }}</dd></div>
        <div><dt class="text-gray-500">Action</dt><dd class="font-medium">{{ $activity->event ?? '—' }}</dd></div>
        <div><dt class="text-gray-500">Record</dt><dd class="font-medium">{{ $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—' }}</dd></div>
    </dl>

    @if (count($keys))
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5">
                    <tr><th class="px-3 py-2">Field</th><th class="px-3 py-2">Before</th><th class="px-3 py-2">After</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($keys as $key)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $key }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ array_key_exists($key, $old) ? AuditLogResource::display($old[$key]) : '' }}</td>
                            <td class="px-3 py-2">{{ array_key_exists($key, $new) ? AuditLogResource::display($new[$key]) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($rows)
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 tabular-nums dark:divide-white/10">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5">
                    <tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Reseller</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-right">Price</th><th class="px-3 py-2 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M d, Y') }}</td>
                            <td class="px-3 py-2">{{ $row['reseller'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['qty'] }}</td>
                            <td class="px-3 py-2 text-right">₱{{ number_format($row['old_price'], 2) }} → ₱{{ number_format($row['new_price'], 2) }}</td>
                            <td class="px-3 py-2 text-right">₱{{ number_format($row['old_total'], 2) }} → ₱{{ number_format($row['new_total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
