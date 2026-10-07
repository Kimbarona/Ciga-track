<div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">
    <div>
        <span class="text-gray-500">Price / ream</span>
        <span class="ml-1 font-semibold tabular-nums">₱{{ number_format((float) $price->unitPrice, 2) }}</span>
        <x-filament::badge :color="$price->source->getColor()" class="ml-1 inline-flex">
            {{ $price->source->getLabel() }}
        </x-filament::badge>
    </div>
    <div>
        <span class="text-gray-500">Total</span>
        <span class="ml-1 font-semibold tabular-nums">{{ $total }}</span>
    </div>
    <div class="{{ $stockClass }}">
        <span class="text-gray-500">Available</span>
        <span class="ml-1 tabular-nums">{{ $available }} {{ $available === 1 ? 'ream' : 'reams' }} of {{ $code }}</span>
    </div>
</div>
