import { useEffect, useState } from 'react';
import type { Balance, PricePreview } from '@/types/ream';

/**
 * Fetches small JSON lookups for live previews; ignores stale responses.
 * The result is keyed by URL, so a changed URL immediately reads as "loading".
 */
function useJson<T>(url: string | null): { data: T | null; loading: boolean } {
    const [result, setResult] = useState<{
        url: string;
        data: T | null;
    } | null>(null);

    useEffect(() => {
        if (!url) return;

        const controller = new AbortController();

        fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
            credentials: 'same-origin',
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => setResult({ url, data: json as T | null }))
            .catch(() => {});

        return () => controller.abort();
    }, [url]);

    if (!url) return { data: null, loading: false };

    const fresh = result?.url === url;

    return { data: fresh ? result.data : null, loading: !fresh };
}

export function usePricePreview(params: {
    resellerId: number | null;
    productId: number | string | null;
    date: string;
    ignoreDistribution?: number;
}) {
    const { resellerId, productId, date, ignoreDistribution } = params;
    const url =
        resellerId && productId && date
            ? `/lookup/price?${new URLSearchParams({
                  reseller_id: String(resellerId),
                  product_id: String(productId),
                  date,
                  ...(ignoreDistribution
                      ? { ignore_distribution: String(ignoreDistribution) }
                      : {}),
              })}`
            : null;

    return useJson<PricePreview>(url);
}

export function useResellerBalance(resellerId: number | null) {
    return useJson<Balance>(
        resellerId ? `/lookup/resellers/${resellerId}/balance` : null,
    );
}
