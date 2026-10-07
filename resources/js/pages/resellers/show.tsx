import { Deferred, Head } from '@inertiajs/react';
import { Banknote, Truck } from 'lucide-react';
import {
    BalanceBadge,
    RemittanceBadge,
    SourceBadge,
} from '@/components/staff/status-badge';
import {
    EmptyState,
    ListSkeleton,
    PageHeader,
    Stat,
} from '@/components/staff/ui';
import StaffLayout from '@/layouts/staff-layout';
import { peso } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Balance, PriceSource, RemittanceStatus } from '@/types/ream';

type Entry = {
    type: 'distribution' | 'remittance';
    id: number;
    date: string;
    date_label: string;
    description: string;
    price_source: PriceSource | null;
    debit: number;
    credit: number;
    status: RemittanceStatus | null;
    counts: boolean;
    balance: number;
};

type Props = {
    reseller: {
        id: number;
        name: string;
        type: string;
        location: string | null;
        contact: string | null;
    };
    balance: Balance;
    ledger?: { opening: number; closing: number; entries: Entry[] };
};

export default function ResellerShow({ reseller, balance, ledger }: Props) {
    return (
        <StaffLayout>
            <Head title={reseller.name} />
            <PageHeader
                title={reseller.name}
                description={
                    [reseller.location, reseller.contact]
                        .filter(Boolean)
                        .join(' · ') || undefined
                }
                back="/resellers"
                action={<BalanceBadge status={balance.status} />}
            />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat
                    label="Balance"
                    value={peso(balance.balance)}
                    tone="highlight"
                    hint={balance.status_label}
                />
                <Stat
                    label="Total taken"
                    value={peso(balance.total_taken)}
                    hint={`${balance.reams_taken} reams`}
                />
                <Stat
                    label="Remitted"
                    value={peso(balance.confirmed_remitted)}
                    hint="Confirmed only"
                />
                <Stat
                    label="Pending"
                    value={peso(balance.pending_remitted)}
                    hint="Not yet deducted"
                />
            </div>

            <h2 className="mt-8 mb-3 text-sm font-bold tracking-wider text-muted-foreground uppercase">
                Timeline
            </h2>
            <Deferred data="ledger" fallback={<ListSkeleton rows={6} />}>
                <Timeline entries={ledger?.entries ?? []} />
            </Deferred>
        </StaffLayout>
    );
}

function Timeline({ entries }: { entries: Entry[] }) {
    if (entries.length === 0) {
        return (
            <EmptyState
                icon={<Truck className="size-6" />}
                title="Nothing here yet"
            >
                Distributions and remittances for this reseller will show up
                here.
            </EmptyState>
        );
    }

    return (
        <ol className="relative space-y-3 before:absolute before:top-2 before:bottom-2 before:left-5 before:w-px before:bg-border">
            {entries.map((e) => {
                const isOut = e.type === 'distribution';

                return (
                    <li
                        key={`${e.type}-${e.id}`}
                        className="relative flex gap-4"
                    >
                        <span
                            className={cn(
                                'z-10 flex size-10 shrink-0 items-center justify-center rounded-full ring-4 ring-background',
                                isOut
                                    ? 'bg-primary text-primary-foreground dark:bg-secondary dark:text-foreground'
                                    : 'bg-highlight text-highlight-foreground',
                            )}
                        >
                            {isOut ? (
                                <Truck className="size-4" />
                            ) : (
                                <Banknote className="size-4" />
                            )}
                        </span>
                        <div
                            className={cn(
                                'flex-1 rounded-2xl border border-border bg-card p-4',
                                !e.counts && 'opacity-70',
                            )}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <div className="text-xs font-semibold text-muted-foreground">
                                        {e.date_label}
                                    </div>
                                    <div className="font-semibold">
                                        {e.description}
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div
                                        className={cn(
                                            'num font-bold',
                                            isOut
                                                ? 'text-foreground'
                                                : 'text-success',
                                        )}
                                    >
                                        {isOut
                                            ? `+${peso(e.debit)}`
                                            : `−${peso(e.credit)}`}
                                    </div>
                                    <div className="num text-xs text-muted-foreground">
                                        Balance {peso(e.balance)}
                                    </div>
                                </div>
                            </div>
                            <div className="mt-2 flex gap-2">
                                {e.price_source && (
                                    <SourceBadge source={e.price_source} />
                                )}
                                {e.status && (
                                    <RemittanceBadge status={e.status} />
                                )}
                                {!e.counts && (
                                    <span className="text-xs text-muted-foreground">
                                        Not counted in the balance
                                    </span>
                                )}
                            </div>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
