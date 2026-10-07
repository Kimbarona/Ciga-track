import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Field,
    NativeSelect,
    PageHeader,
    Panel,
    PrimaryButton,
    TextArea,
    TextInput,
    inputClass,
} from '@/components/staff/ui';
import StaffLayout from '@/layouts/staff-layout';
import { peso } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ProductOption } from '@/types/ream';

type Props = {
    purchase: {
        id: number;
        date: string;
        product_id: number;
        qty: number;
        unit_cost?: number;
        supplier: string | null;
        notes: string | null;
    } | null;
    products: ProductOption[];
    suppliers: string[];
    today: string;
};

export default function PurchaseForm({
    purchase,
    products,
    suppliers,
    today,
}: Props) {
    const { auth } = usePage().props;
    const editing = purchase !== null;
    // Staff never get the saved cost back; on edit they may leave it blank to keep it.
    const costHidden = editing && purchase.unit_cost === undefined;

    const form = useForm({
        date: purchase?.date ?? today,
        product_id: purchase ? String(purchase.product_id) : '',
        qty: purchase ? String(purchase.qty) : '',
        unit_cost:
            purchase?.unit_cost != null ? String(purchase.unit_cost) : '',
        supplier: purchase?.supplier ?? '',
        notes: purchase?.notes ?? '',
    });

    const total =
        (Number(form.data.qty) || 0) * (Number(form.data.unit_cost) || 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            unit_cost: data.unit_cost === '' ? null : data.unit_cost,
        }));

        if (editing) {
            form.put(`/purchases/${purchase.id}`, { preserveScroll: true });
        } else {
            form.post('/purchases', { preserveScroll: true });
        }
    };

    return (
        <StaffLayout>
            <Head title={editing ? 'Edit purchase' : 'New purchase'} />
            <PageHeader
                title={editing ? 'Edit purchase' : 'New purchase'}
                description="Stock in — reams bought from a supplier."
                back={editing ? '/my-entries?tab=purchases' : '/'}
            />

            <form onSubmit={submit} className="space-y-5">
                <Panel>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            label="Date"
                            htmlFor="date"
                            error={form.errors.date}
                        >
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
                            label="Product"
                            htmlFor="product"
                            error={form.errors.product_id}
                        >
                            <NativeSelect
                                id="product"
                                value={form.data.product_id}
                                onChange={(e) =>
                                    form.setData('product_id', e.target.value)
                                }
                            >
                                <option value="">Choose product…</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.code} — {p.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field
                            label="Qty (reams)"
                            htmlFor="qty"
                            error={form.errors.qty}
                        >
                            <TextInput
                                id="qty"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                value={form.data.qty}
                                className="text-right num text-lg font-semibold"
                                onChange={(e) =>
                                    form.setData('qty', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Cost per ream"
                            htmlFor="unit_cost"
                            error={form.errors.unit_cost}
                            hint={
                                costHidden
                                    ? 'Leave blank to keep the cost you entered.'
                                    : undefined
                            }
                        >
                            <div className="relative">
                                <span className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted-foreground">
                                    ₱
                                </span>
                                <input
                                    id="unit_cost"
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step="0.01"
                                    placeholder={
                                        costHidden ? 'Unchanged' : '0.00'
                                    }
                                    value={form.data.unit_cost}
                                    onChange={(e) =>
                                        form.setData(
                                            'unit_cost',
                                            e.target.value,
                                        )
                                    }
                                    className={cn(
                                        inputClass,
                                        'pl-8 text-right num',
                                    )}
                                />
                            </div>
                        </Field>
                        <Field
                            label="Supplier"
                            htmlFor="supplier"
                            error={form.errors.supplier}
                        >
                            <TextInput
                                id="supplier"
                                list="suppliers"
                                value={form.data.supplier}
                                onChange={(e) =>
                                    form.setData('supplier', e.target.value)
                                }
                            />
                            <datalist id="suppliers">
                                {suppliers.map((s) => (
                                    <option key={s} value={s} />
                                ))}
                            </datalist>
                        </Field>
                        {(!editing ||
                            auth.can.viewFinancials ||
                            form.data.unit_cost !== '') && (
                            <div className="flex flex-col justify-end">
                                <div className="rounded-xl bg-muted px-4 py-3">
                                    <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Total cost
                                    </div>
                                    <div className="num text-xl font-bold">
                                        {peso(total)}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                    <Field
                        label="Notes (optional)"
                        htmlFor="notes"
                        error={form.errors.notes}
                        className="mt-5"
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
                        {form.processing
                            ? 'Saving…'
                            : editing
                              ? 'Save changes'
                              : 'Save purchase'}
                    </PrimaryButton>
                </div>
            </form>
        </StaffLayout>
    );
}
