import { Head, useForm, usePage } from '@inertiajs/react';
import { DistributionLine } from '@/components/staff/distribution-line';
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
import type { PriceSource, ProductOption, ResellerOption } from '@/types/ream';

type Props = {
    distribution: {
        id: number;
        date: string;
        reseller_id: number;
        product_id: number;
        qty: number;
        unit_price: number;
        price_source: PriceSource;
        override_reason: string | null;
        notes: string | null;
    };
    resellers: ResellerOption[];
    products: ProductOption[];
    today: string;
};

export default function EditDistribution({
    distribution,
    resellers,
    products,
    today,
}: Props) {
    const { auth } = usePage().props;
    const wasManual = distribution.price_source === 'manual';

    const form = useForm({
        date: distribution.date,
        reseller_id: distribution.reseller_id as number | null,
        notes: distribution.notes ?? '',
        line: {
            product_id: String(distribution.product_id),
            qty: String(distribution.qty),
            override_price: wasManual,
            unit_price: wasManual ? String(distribution.unit_price) : '',
            override_reason: distribution.override_reason ?? '',
        } as LineState,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            date: data.date,
            reseller_id: data.reseller_id,
            notes: data.notes,
            product_id: data.line.product_id,
            qty: data.line.qty,
            ...(auth.can.overridePrice
                ? {
                      override_price: data.line.override_price,
                      unit_price: data.line.unit_price || null,
                      override_reason: data.line.override_reason || null,
                  }
                : {}),
        }));
        form.put(`/distributions/${distribution.id}`, { preserveScroll: true });
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <StaffLayout>
            <Head title="Edit distribution" />
            <PageHeader
                title="Edit distribution"
                description="Changing the date, reseller or product re-checks the price. Changing only the qty keeps the saved price."
                back="/my-entries"
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
                                onChange={(id) =>
                                    form.setData('reseller_id', id)
                                }
                            />
                        </Field>
                    </div>
                </Panel>

                <DistributionLine
                    index={0}
                    line={form.data.line}
                    products={products}
                    resellerId={form.data.reseller_id}
                    date={form.data.date}
                    errors={errors}
                    canOverride={auth.can.overridePrice}
                    ignoreDistribution={distribution.id}
                    onChange={(patch) =>
                        form.setData('line', { ...form.data.line, ...patch })
                    }
                />

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

                <div className="flex justify-end">
                    <PrimaryButton type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save changes'}
                    </PrimaryButton>
                </div>
            </form>
        </StaffLayout>
    );
}
