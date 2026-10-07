import { Deferred, Head, Link } from '@inertiajs/react';
import { ChevronRight, Search, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BalanceBadge, Pill } from '@/components/staff/status-badge';
import {
    EmptyState,
    ListSkeleton,
    PageHeader,
    inputClass,
} from '@/components/staff/ui';
import StaffLayout from '@/layouts/staff-layout';
import { peso } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Balance } from '@/types/ream';

type ResellerRow = Balance & {
    id: number;
    name: string;
    type: 'reseller' | 'retail';
    location: string | null;
};

export default function Resellers({
    resellers,
}: {
    resellers?: ResellerRow[];
}) {
    return (
        <StaffLayout>
            <Head title="Resellers" />
            <PageHeader
                title="Resellers"
                description="Balances to remit, highest first. Pending remittances are not deducted yet."
            />

            <Deferred data="resellers" fallback={<ListSkeleton rows={6} />}>
                <ResellerList resellers={resellers ?? []} />
            </Deferred>
        </StaffLayout>
    );
}

function ResellerList({ resellers }: { resellers: ResellerRow[] }) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return q
            ? resellers.filter(
                  (r) =>
                      r.name.toLowerCase().includes(q) ||
                      (r.location ?? '').toLowerCase().includes(q),
              )
            : resellers;
    }, [query, resellers]);

    const receivables = resellers.reduce(
        (sum, r) => sum + Math.max(r.balance, 0),
        0,
    );

    if (resellers.length === 0) {
        return (
            <EmptyState
                icon={<Users className="size-6" />}
                title="No resellers yet"
            >
                Resellers are added by an admin.
            </EmptyState>
        );
    }

    return (
        <>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative sm:w-80">
                    <Search className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted-foreground" />
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search resellers"
                        className={cn(inputClass, 'pl-11')}
                    />
                </div>
                <div className="text-sm text-muted-foreground">
                    To collect{' '}
                    <span className="num font-bold text-foreground">
                        {peso(receivables)}
                    </span>
                </div>
            </div>

            {filtered.length === 0 ? (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    No reseller matches “{query}”.
                </p>
            ) : (
                <ul className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
                    {filtered.map((r) => (
                        <li key={r.id}>
                            <Link
                                href={`/resellers/${r.id}`}
                                prefetch
                                className="flex items-center gap-4 px-4 py-4 hover:bg-muted sm:px-5"
                            >
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-secondary font-bold text-secondary-foreground">
                                    {r.name.charAt(0).toUpperCase()}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="flex items-center gap-2">
                                        <span className="truncate font-semibold">
                                            {r.name}
                                        </span>
                                        {r.type === 'retail' && (
                                            <Pill tone="warning">Retail</Pill>
                                        )}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {r.reams_taken} reams taken
                                        {r.pending_remitted > 0 &&
                                            ` · ${peso(r.pending_remitted)} pending`}
                                    </span>
                                </span>
                                <span className="flex flex-col items-end gap-1">
                                    <span
                                        className={cn(
                                            'num font-bold',
                                            r.status === 'may_balance' &&
                                                'text-danger',
                                        )}
                                    >
                                        {peso(r.balance)}
                                    </span>
                                    <BalanceBadge status={r.status} />
                                </span>
                                <ChevronRight className="hidden size-5 text-muted-foreground sm:block" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
