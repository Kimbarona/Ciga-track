<x-filament-panels::page>
    {{ $this->form }}

    @php($url = $this->reportUrl())

    @if ($url)
        <iframe
            src="{{ $url }}"
            wire:key="{{ md5($url) }}"
            class="w-full rounded-xl border border-gray-200 bg-white dark:border-white/10"
            style="height: 75vh"
            title="Report preview"
        ></iframe>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-white/10">
            Choose a reseller to see the statement.
        </div>
    @endif
</x-filament-panels::page>
