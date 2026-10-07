import { Head, useForm } from '@inertiajs/react';
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
import { cn } from '@/lib/utils';

type Props = {
    expense: {
        id: number;
        date: string;
        expense_category_id: number;
        description: string;
        amount: number;
        paid_via: string | null;
        notes: string | null;
    } | null;
    categories: { id: number; name: string }[];
    today: string;
};

export default function ExpenseForm({ expense, categories, today }: Props) {
    const editing = expense !== null;

    const form = useForm({
        date: expense?.date ?? today,
        expense_category_id: expense ? String(expense.expense_category_id) : '',
        description: expense?.description ?? '',
        amount: expense ? String(expense.amount) : '',
        paid_via: expense?.paid_via ?? 'Cash',
        notes: expense?.notes ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editing) {
            form.put(`/expenses/${expense.id}`, { preserveScroll: true });
        } else {
            form.post('/expenses', { preserveScroll: true });
        }
    };

    return (
        <StaffLayout>
            <Head title={editing ? 'Edit expense' : 'New expense'} />
            <PageHeader
                title={editing ? 'Edit expense' : 'New expense'}
                description="Delivery, gas, packaging and other costs."
                back={editing ? '/my-entries?tab=expenses' : '/'}
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
                            label="Category"
                            htmlFor="category"
                            error={form.errors.expense_category_id}
                        >
                            <NativeSelect
                                id="category"
                                value={form.data.expense_category_id}
                                onChange={(e) =>
                                    form.setData(
                                        'expense_category_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">Choose category…</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field
                            label="Description"
                            htmlFor="description"
                            error={form.errors.description}
                            className="sm:col-span-2"
                        >
                            <TextInput
                                id="description"
                                placeholder="e.g. Lalamove to San Juan"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Amount"
                            htmlFor="amount"
                            error={form.errors.amount}
                        >
                            <div className="relative">
                                <span className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-lg text-muted-foreground">
                                    ₱
                                </span>
                                <input
                                    id="amount"
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step="0.01"
                                    placeholder="0.00"
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                    className={cn(
                                        inputClass,
                                        'pl-9 text-right num text-lg font-semibold',
                                    )}
                                />
                            </div>
                        </Field>
                        <Field
                            label="Paid via"
                            htmlFor="paid_via"
                            error={form.errors.paid_via}
                        >
                            <TextInput
                                id="paid_via"
                                list="paid-via"
                                value={form.data.paid_via}
                                onChange={(e) =>
                                    form.setData('paid_via', e.target.value)
                                }
                            />
                            <datalist id="paid-via">
                                {['Cash', 'GCash', 'Maya', 'Bank Transfer'].map(
                                    (v) => (
                                        <option key={v} value={v} />
                                    ),
                                )}
                            </datalist>
                        </Field>
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
                              : 'Save expense'}
                    </PrimaryButton>
                </div>
            </form>
        </StaffLayout>
    );
}
