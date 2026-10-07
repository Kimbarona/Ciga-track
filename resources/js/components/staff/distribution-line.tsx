import { AlertTriangle, Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import { usePricePreview } from '@/hooks/use-lookup';
import { peso, reams } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PriceSource, ProductOption } from '@/types/ream';
import { SourceBadge } from './status-badge';
import { Field, NativeSelect, TextInput, inputClass } from './ui';

export type LineState = {
    product_id: string;
    qty: string;
    override_price: boolean;
    unit_price: string;
    override_reason: string;
};

export const emptyLine = (): LineState => ({
    product_id: '',
    qty: '',
    override_price: false,
    unit_price: '',
    override_reason: '',
});

/**
 * One product line with a live preview of price, source, total and available stock.
 */
export function DistributionLine({
    index,
    line,
    products,
    resellerId,
    date,
    errors,
    canOverride,
    onChange,
    onRemove,
    reservedQty = 0,
    ignoreDistribution,
    onTotal,
}: {
    index: number;
    line: LineState;
    products: ProductOption[];
    resellerId: number | null;
    date: string;
    errors: Record<string, string | undefined>;
    canOverride: boolean;
    onChange: (patch: Partial<LineState>) => void;
    onRemove?: () => void;
    /** qty of the same product already used by earlier lines in this entry */
    reservedQty?: number;
    ignoreDistribution?: number;
    /** reports this line's computed total (for the entry summary) */
    onTotal?: (total: number) => void;
}) {
    const { data: preview, loading } = usePricePreview({
        resellerId,
        productId: line.product_id || null,
        date,
        ignoreDistribution,
    });
    const qty = Number(line.qty) || 0;
    const unitPrice = line.override_price
        ? Number(line.unit_price) || 0
        : (preview?.unit_price ?? 0);
    const lineTotal = line.product_id && resellerId ? qty * unitPrice : 0;

    useEffect(() => {
        onTotal?.(lineTotal);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [lineTotal]);
    const available = preview ? preview.available - reservedQty : null;
    const overStock = available !== null && qty > available;
    const source: PriceSource | null = line.override_price
        ? 'manual'
        : (preview?.source ?? null);
    const error = (key: string) =>
        errors[`lines.${index}.${key}`] ?? errors[key];

    return (
        <div className="rounded-2xl border border-border bg-card p-4 sm:p-5">
            <div className="mb-3 flex items-center justify-between">
                <span className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                    Line {index + 1}
                </span>
                {onRemove && (
                    <button
                        type="button"
                        onClick={onRemove}
                        className="-mr-2 rounded-lg p-2 text-muted-foreground hover:bg-danger-soft hover:text-danger"
                        title="Remove line"
                    >
                        <Trash2 className="size-4" />
                    </button>
                )}
            </div>

            <div className="grid gap-4 sm:grid-cols-[1fr_9rem]">
                <Field
                    label="Product"
                    htmlFor={`product-${index}`}
                    error={error('product_id')}
                >
                    <NativeSelect
                        id={`product-${index}`}
                        value={line.product_id}
                        aria-invalid={!!error('product_id')}
                        onChange={(e) =>
                            onChange({ product_id: e.target.value })
                        }
                    >
                        <option value="">Choose product…</option>
                        {products.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.code} — {p.name} ({p.stock} in stock)
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                <Field
                    label="Qty (reams)"
                    htmlFor={`qty-${index}`}
                    error={error('qty')}
                >
                    <TextInput
                        id={`qty-${index}`}
                        type="number"
                        inputMode="numeric"
                        min={1}
                        step={1}
                        placeholder="0"
                        value={line.qty}
                        aria-invalid={!!error('qty') || overStock}
                        className="text-right num text-lg font-semibold"
                        onChange={(e) => onChange({ qty: e.target.value })}
                    />
                </Field>
            </div>

            {/* Live preview */}
            {line.product_id && resellerId ? (
                <div
                    className={cn(
                        'mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl px-4 py-3 text-sm',
                        preview?.error && !line.override_price
                            ? 'bg-danger-soft text-danger'
                            : 'bg-muted',
                        loading && 'opacity-60',
                    )}
                >
                    {preview?.error && !line.override_price ? (
                        <span className="flex items-center gap-2 font-medium">
                            <AlertTriangle className="size-4" /> {preview.error}
                        </span>
                    ) : (
                        <>
                            <span className="flex items-center gap-2">
                                <span className="text-muted-foreground">
                                    Price
                                </span>
                                <span className="num font-semibold">
                                    {peso(unitPrice)}
                                </span>
                                {source && <SourceBadge source={source} />}
                            </span>
                            <span className="flex items-center gap-2">
                                <span className="text-muted-foreground">
                                    Total
                                </span>
                                <span className="num font-bold">
                                    {peso(qty * unitPrice)}
                                </span>
                            </span>
                            {available !== null && (
                                <span
                                    className={cn(
                                        'flex items-center gap-2',
                                        overStock &&
                                            'font-semibold text-danger',
                                    )}
                                >
                                    <span
                                        className={
                                            overStock
                                                ? ''
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        Available
                                    </span>
                                    <span className="num">
                                        {reams(Math.max(available, 0))}
                                    </span>
                                    {overStock && (
                                        <AlertTriangle className="size-4" />
                                    )}
                                </span>
                            )}
                        </>
                    )}
                </div>
            ) : (
                <p className="mt-3 text-sm text-muted-foreground">
                    Pick the reseller and product to see the price.
                </p>
            )}

            {canOverride && (
                <div className="mt-4 border-t border-dashed border-border pt-4">
                    <label className="flex cursor-pointer items-center gap-3 text-sm font-semibold">
                        <input
                            type="checkbox"
                            className="size-5 rounded accent-[var(--highlight)]"
                            checked={line.override_price}
                            onChange={(e) =>
                                onChange({ override_price: e.target.checked })
                            }
                        />
                        Override price (admin)
                    </label>
                    {line.override_price && (
                        <div className="mt-3 grid gap-4 sm:grid-cols-[10rem_1fr]">
                            <Field
                                label="Manual price"
                                htmlFor={`price-${index}`}
                                error={error('unit_price')}
                            >
                                <div className="relative">
                                    <span className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted-foreground">
                                        ₱
                                    </span>
                                    <input
                                        id={`price-${index}`}
                                        type="number"
                                        inputMode="decimal"
                                        min={0}
                                        step="0.01"
                                        value={line.unit_price}
                                        onChange={(e) =>
                                            onChange({
                                                unit_price: e.target.value,
                                            })
                                        }
                                        className={cn(
                                            inputClass,
                                            'pl-8 text-right num',
                                        )}
                                    />
                                </div>
                            </Field>
                            <Field
                                label="Reason"
                                htmlFor={`reason-${index}`}
                                error={error('override_reason')}
                            >
                                <TextInput
                                    id={`reason-${index}`}
                                    value={line.override_reason}
                                    placeholder="e.g. damaged ream, promo"
                                    onChange={(e) =>
                                        onChange({
                                            override_reason: e.target.value,
                                        })
                                    }
                                />
                            </Field>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
