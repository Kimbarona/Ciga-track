import { Head, useForm, usePage } from '@inertiajs/react';
import { Camera, ImageIcon, Info, X } from 'lucide-react';
import { useState } from 'react';
import { ResellerPicker } from '@/components/staff/reseller-picker';
import { BalanceBadge } from '@/components/staff/status-badge';
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
import { Skeleton } from '@/components/ui/skeleton';
import { useResellerBalance } from '@/hooks/use-lookup';
import StaffLayout from '@/layouts/staff-layout';
import { peso } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option, ResellerOption } from '@/types/ream';

type Props = {
    remittance: {
        id: number;
        date: string;
        reseller_id: number;
        amount: number;
        mode: string;
        reference_no: string | null;
        notes: string | null;
        status: string;
        proof_url: string | null;
    } | null;
    resellers: ResellerOption[];
    modes: Option[];
    today: string;
};

export default function RemittanceForm({
    remittance,
    resellers,
    modes,
    today,
}: Props) {
    const { auth } = usePage().props;
    const editing = remittance !== null;

    const form = useForm<{
        date: string;
        reseller_id: number | null;
        amount: string;
        mode: string;
        reference_no: string;
        notes: string;
        proof: File | null;
    }>({
        date: remittance?.date ?? today,
        reseller_id: remittance?.reseller_id ?? null,
        amount: remittance ? String(remittance.amount) : '',
        mode: remittance?.mode ?? 'cash',
        reference_no: remittance?.reference_no ?? '',
        notes: remittance?.notes ?? '',
        proof: null,
    });

    const { data: balance, loading } = useResellerBalance(
        form.data.reseller_id,
    );
    const [preview, setPreview] = useState<string | null>(
        remittance?.proof_url ?? null,
    );

    const pickProof = (file: File | null) => {
        if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview);
        form.setData('proof', file);
        setPreview(file ? URL.createObjectURL(file) : null);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editing) {
            // Files need multipart: send as POST with method spoofing.
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/remittances/${remittance.id}`, {
                forceFormData: true,
                preserveScroll: true,
            });
        } else {
            form.post('/remittances', {
                forceFormData: true,
                preserveScroll: true,
            });
        }
    };

    return (
        <StaffLayout>
            <Head title={editing ? 'Edit remittance' : 'New remittance'} />
            <PageHeader
                title={editing ? 'Edit remittance' : 'New remittance'}
                description={
                    auth.can.accessAdmin
                        ? 'Remittances you record are confirmed right away.'
                        : 'Your remittance will be marked pending until an admin confirms it.'
                }
                back={editing ? '/my-entries?tab=remittances' : '/'}
            />

            <form onSubmit={submit} className="space-y-5">
                <Panel>
                    <div className="grid gap-5">
                        <Field
                            label="Reseller"
                            htmlFor="reseller"
                            error={form.errors.reseller_id}
                        >
                            <ResellerPicker
                                id="reseller"
                                resellers={resellers}
                                value={form.data.reseller_id}
                                invalid={!!form.errors.reseller_id}
                                onChange={(id) =>
                                    form.setData('reseller_id', id)
                                }
                            />
                        </Field>

                        {form.data.reseller_id && (
                            <div className="rounded-xl bg-muted px-4 py-3">
                                {loading || !balance ? (
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-1/3" />
                                        <Skeleton className="h-6 w-1/2" />
                                    </div>
                                ) : (
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                Current balance
                                            </div>
                                            <div className="num text-2xl font-bold">
                                                {peso(balance.balance)}
                                            </div>
                                            {balance.pending_remitted > 0 && (
                                                <div className="num text-xs text-muted-foreground">
                                                    {peso(
                                                        balance.pending_remitted,
                                                    )}{' '}
                                                    pending confirmation
                                                </div>
                                            )}
                                        </div>
                                        <BalanceBadge status={balance.status} />
                                    </div>
                                )}
                            </div>
                        )}

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
                                        aria-invalid={!!form.errors.amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        className={cn(
                                            inputClass,
                                            'pl-9 text-right num text-lg font-semibold',
                                        )}
                                    />
                                </div>
                            </Field>
                            <Field
                                label="Mode"
                                htmlFor="mode"
                                error={form.errors.mode}
                            >
                                <NativeSelect
                                    id="mode"
                                    value={form.data.mode}
                                    onChange={(e) =>
                                        form.setData('mode', e.target.value)
                                    }
                                >
                                    {modes.map((m) => (
                                        <option key={m.value} value={m.value}>
                                            {m.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field
                                label="Reference no."
                                htmlFor="reference_no"
                                error={form.errors.reference_no}
                                hint="GCash / Maya / bank reference, if any"
                            >
                                <TextInput
                                    id="reference_no"
                                    value={form.data.reference_no}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference_no',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    </div>
                </Panel>

                <Panel>
                    <Field
                        label="Proof of payment (optional)"
                        error={form.errors.proof}
                    >
                        {preview ? (
                            <div className="relative overflow-hidden rounded-xl border border-border">
                                <img
                                    src={preview}
                                    alt="Proof"
                                    className="max-h-80 w-full bg-muted object-contain"
                                />
                                <button
                                    type="button"
                                    onClick={() => pickProof(null)}
                                    className="absolute top-2 right-2 rounded-full bg-black/60 p-2 text-white"
                                    title="Remove photo"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 gap-3">
                                <label className="flex h-24 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-border text-sm font-semibold hover:border-highlight hover:bg-highlight-soft">
                                    <Camera className="size-6 text-highlight" />
                                    Take photo
                                    <input
                                        type="file"
                                        accept="image/*"
                                        capture="environment"
                                        className="sr-only"
                                        onChange={(e) =>
                                            pickProof(
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </label>
                                <label className="flex h-24 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-border text-sm font-semibold hover:border-highlight hover:bg-highlight-soft">
                                    <ImageIcon className="size-6 text-highlight" />
                                    Upload screenshot
                                    <input
                                        type="file"
                                        accept="image/*"
                                        className="sr-only"
                                        onChange={(e) =>
                                            pickProof(
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </label>
                            </div>
                        )}
                    </Field>
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

                {!auth.can.accessAdmin && (
                    <p className="flex items-start gap-2 text-sm text-muted-foreground">
                        <Info className="mt-0.5 size-4 shrink-0" /> Pending
                        remittances do not reduce the reseller’s balance until
                        confirmed.
                    </p>
                )}

                <div className="flex justify-end">
                    <PrimaryButton type="submit" disabled={form.processing}>
                        {form.processing
                            ? 'Saving…'
                            : editing
                              ? 'Save changes'
                              : 'Save remittance'}
                    </PrimaryButton>
                </div>
            </form>
        </StaffLayout>
    );
}
