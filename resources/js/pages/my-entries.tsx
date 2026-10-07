import { Deferred, Head, Link } from '@inertiajs/react';
import { ClipboardList, Pencil } from 'lucide-react';
import { useState } from 'react';
import { EntryBadge } from '@/components/staff/status-badge';
import { EmptyState, ListSkeleton, PageHeader } from '@/components/staff/ui';
import StaffLayout from '@/layouts/staff-layout';
import { peso } from '@/lib/format';
import { cn } from '@/lib/utils';

type Tab = 'distributions' | 'remittances' | 'purchases' | 'expenses';

type Entry = {
    id: number;
    date: string;
    title: string;
    subtitle: string;
    amount: number | null;
    badge: string | null;
    note: string | null;
    can_edit: boolean;
    edit_url: string;
};

type Props = { tab: Tab } & Partial<Record<Tab, Entry[]>>;

const tabs: { key: Tab; label: string; newUrl: string }[] = [
    {
        key: 'distributions',
        label: 'Distributions',
        newUrl: '/distributions/new',
    },
    { key: 'remittances', label: 'Remittances', newUrl: '/remittances/new' },
    { key: 'purchases', label: 'Purchases', newUrl: '/purchases/new' },
    { key: 'expenses', label: 'Expenses', newUrl: '/expenses/new' },
];

export default function MyEntries(props: Props) {
    // All four lists arrive together (one deferred group), so tabs switch instantly.
    const [active, setActive] = useState<Tab>(props.tab);
    const current = tabs.find((t) => t.key === active)!;

    const selectTab = (tab: Tab) => {
        setActive(tab);
        window.history.replaceState(
            window.history.state,
            '',
            `/my-entries?tab=${tab}`,
        );
    };

    return (
        <StaffLayout>
            <Head title="My entries" />
            <PageHeader
                title="My entries"
                description="You can edit your own entries on the day you encoded them, until they are confirmed or voided."
            />

            <div className="-mx-4 mb-5 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                <div className="inline-flex gap-1 rounded-2xl bg-muted p-1">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => selectTab(t.key)}
                            className={cn(
                                'rounded-xl px-4 py-2 text-sm font-semibold whitespace-nowrap transition',
                                active === t.key
                                    ? 'bg-card text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
            </div>

            <Deferred
                data={['distributions', 'remittances', 'purchases', 'expenses']}
                fallback={<ListSkeleton rows={5} />}
            >
                <EntryList
                    entries={props[active] ?? []}
                    newUrl={current.newUrl}
                    label={current.label.toLowerCase()}
                />
            </Deferred>
        </StaffLayout>
    );
}

function EntryList({
    entries,
    newUrl,
    label,
}: {
    entries: Entry[];
    newUrl: string;
    label: string;
}) {
    if (entries.length === 0) {
        return (
            <EmptyState
                icon={<ClipboardList className="size-6" />}
                title={`No ${label} yet`}
            >
                Entries you encode will show up here.{' '}
                <Link
                    href={newUrl}
                    className="font-semibold text-primary underline-offset-4 hover:underline dark:text-highlight"
                >
                    Add one now
                </Link>
                .
            </EmptyState>
        );
    }

    return (
        <ul className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
            {entries.map((e) => (
                <li
                    key={e.id}
                    className={cn(
                        'flex items-center gap-4 px-4 py-4 sm:px-5',
                        e.badge === 'voided' && 'opacity-60',
                    )}
                >
                    <div className="min-w-0 flex-1">
                        <div className="text-xs font-semibold text-muted-foreground">
                            {e.date}
                        </div>
                        <div
                            className={cn(
                                'truncate font-semibold',
                                e.badge === 'voided' && 'line-through',
                            )}
                        >
                            {e.title}
                        </div>
                        <div className="truncate text-sm text-muted-foreground">
                            {e.subtitle}
                        </div>
                        {e.note && (
                            <div className="mt-1 text-xs text-danger">
                                {e.note}
                            </div>
                        )}
                    </div>
                    <div className="flex flex-col items-end gap-1.5">
                        {e.amount !== null && (
                            <span className="num font-bold">
                                {peso(e.amount)}
                            </span>
                        )}
                        <EntryBadge value={e.badge} />
                    </div>
                    {e.can_edit && (
                        <Link
                            href={e.edit_url}
                            className="rounded-xl border border-border p-2.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Edit"
                        >
                            <Pencil className="size-4" />
                        </Link>
                    )}
                </li>
            ))}
        </ul>
    );
}
