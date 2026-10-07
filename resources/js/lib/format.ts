const pesoFormatter = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** ₱1,234.00 (negative: -₱1,234.00) */
export function peso(value: number | string | null | undefined): string {
    const n = Number(value ?? 0);
    const formatted = `₱${pesoFormatter.format(Math.abs(n))}`;

    return n < 0 ? `-${formatted}` : formatted;
}

export function reams(qty: number): string {
    return `${qty.toLocaleString('en-PH')} ${qty === 1 ? 'ream' : 'reams'}`;
}

/** "2026-10-07" → "Oct 07, 2026" */
export function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    const date = new Date(y, m - 1, d);

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
}
