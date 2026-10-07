import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import {
    DistributionLine,
    emptyLine,
} from '@/components/staff/distribution-line';
import type { LineState } from '@/components/staff/distribution-line';
import { ResellerPicker } from '@/components/staff/reseller-picker';
import {
    Field,
    PageHeader,
    Panel,
    PrimaryButton,
    TextArea,
    TextInput,
} from '@/components/staff/ui';
import StaffLayout from '@/layouts/staff-layout';
import { peso, reams } from '@/lib/format';
import type { ProductOption, ResellerOption } from '@/types/ream';

type Props = {
    resellers: ResellerOption[];
    products: ProductOption[];
    today: string;
};

type FormData = {
    date: string;
    reseller_id: number | null;
    notes: string;
    lines: LineState[];
};

export default function CreateDistribution({
    resellers,
    products,
    today,
}: Props) {
    const { auth } = usePage().props;
    const form = useForm<FormData>({
        date: today,
        reseller_id: null,
        notes: '',
        lines: [emptyLine()],
    });
    const [totals, setTotals] = useState<number[]>([0]);
    const errors = form.errors as Record<string, string | undefined>;

    const updateLine = (index: number, patch: Partial<LineState>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i === index ? { ...line, ...patch } : line,
            ),
        );

    const addLine = () => {
        form.setData('lines', [...form.data.lines, emptyLine()]);
        setTotals((t) => [...t, 0]);
    };

    const removeLine = (index: number) => {
        form.setData(
            'lines',
            form.data.lines.filter((_, i) => i !== index),
        );
        setTotals((t) => t.filter((_, i) => i !== index));
    };

    // Earlier lines of the same product reduce what is left for later lines.
    const reservedBefore = (index: number) =>
        form.data.lines
            .slice(0, index)
            .filter(
                (l) =>
                    l.product_id &&
                    l.product_id === form.data.lines[index].product_id,
            )
            .reduce((sum, l) => sum + (Number(l.qty) || 0), 0);

    const totalReams = form.data.lines.reduce(
        (sum, l) => sum + (Number(l.qty) || 0),
        0,
    );
    const grandTotal = totals.reduce((sum, t) => sum + t, 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            lines: data.lines.map((l) =>
                l.override_price
                    ? {
                          product_id: l.product_id,
                          qty: l.qty,
                          override_price: true,
                          unit_price: l.unit_price,
                          override_reason: l.override_reason,
                      }
                    : { product_id: l.product_id, qty: l.qty },
            ),
        }));
        form.post('/distributions', { preserveScroll: true });
    };

    return (
        <StaffLayout>
            <Head title="New distribution" />
            <PageHeader
                title="New distribution"
                description="Reams going out to a reseller or a walk-in (Retail) customer."
                back="/"
            />

            <form onSubmit={submit} className="space-y-5">
                <Panel>
                    <div className="grid gap-5 sm:grid-cols-[11rem_1fr]">
                        <Field label="Date" htmlFor="date" error={errors.date}>
                            <TextInput
                                id="date"
                                type="date"
                                max={today}
                                value={form.data.date}
                                onChange={(e) =>
                                    form.setData('date', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Reseller"
                            htmlFor="reseller"
                            error={errors.reseller_id}
                        >
                            <ResellerPicker
                                id="reseller"
                                resellers={resellers}
                                value={form.data.reseller_id}
                                invalid={!!errors.reseller_id}
                                onChange={(id) =>
                                    form.setData('reseller_id', id)
                                }
                            />
                        </Field>
                    </div>
                </Panel>

                {errors.lines && (
                    <p className="rounded-xl bg-danger-soft px-4 py-3 text-sm font-medium text-danger">
                        {errors.lines}
                    </p>
                )}

                <div className="space-y-4">
                    {form.data.lines.map((line, index) => (
                        <DistributionLine
                            key={index}
                            index={index}
                            line={line}
                            products={products}
                            resellerId={form.data.reseller_id}
                            date={form.data.date}
                            errors={errors}
                            canOverride={auth.can.overridePrice}
                            reservedQty={reservedBefore(index)}
                            onChange={(patch) => updateLine(index, patch)}
                            onRemove={
                                form.data.lines.length > 1
                                    ? () => removeLine(index)
                                    : undefined
                            }
                            onTotal={(total) =>
                                setTotals((t) =>
                                    t.map((v, i) => (i === index ? total : v)),
                                )
                            }
                        />
                    ))}

                    <button
                        type="button"
                        onClick={addLine}
                        className="flex h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-border text-sm font-semibold text-primary hover:border-highlight hover:bg-highlight-soft dark:text-highlight"
                    >
                        <Plus className="size-5" /> Add another product
                    </button>
                </div>

                <Panel>
                    <Field
                        label="Notes (optional)"
                        htmlFor="notes"
                        error={errors.notes}
                    >
                        <TextArea
                            id="notes"
                            rows={2}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </Field>
                </Panel>

                {/* Summary + save */}
                <div className="sticky bottom-24 z-10 lg:bottom-6">
                    <div className="flex items-center justify-between gap-3 rounded-2xl border border-primary/20 bg-primary px-4 py-3 text-primary-foreground shadow-xl sm:p-4 dark:border-border dark:bg-card dark:text-foreground">
                        <div className="flex items-baseline gap-4 sm:gap-6">
                            <div>
                                <div className="text-[10px] font-semibold tracking-wide uppercase opacity-70 sm:text-xs">
                                    Reams
                                </div>
                                <div className="num text-base font-bold sm:text-xl">
                                    {reams(totalReams)}
                                </div>
                            </div>
                            <div>
                                <div className="text-[10px] font-semibold tracking-wide uppercase opacity-70 sm:text-xs">
                                    Total
                                </div>
                                <div className="num text-base font-bold sm:text-xl">
                                    {peso(grandTotal)}
                                </div>
                            </div>
                        </div>
                        <PrimaryButton
                            type="submit"
                            disabled={form.processing}
                            className="h-11 w-auto px-5 sm:h-12"
                        >
                            {form.processing
                                ? 'Saving…'
                                : form.data.lines.length > 1
                                  ? `Save ${form.data.lines.length} lines`
                                  : 'Save'}
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </StaffLayout>
    );
}
