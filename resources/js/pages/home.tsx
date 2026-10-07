import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Clock } from 'lucide-react';
import { Stat } from '@/components/staff/ui';
import StaffLayout, { entryLinks } from '@/layouts/staff-layout';
import { peso, reams } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    today: {
        date: string;
        distributions: { count: number; reams: number; amount: number };
        remittances: { count: number; amount: number };
        pending: { count: number; amount: number; scope: 'all' | 'mine' };
    };
};

function greeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';

    return 'Good evening';
}

export default function Home({ today }: Props) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <StaffLayout>
            <Head title="Home" />

            <div className="mb-6">
                <p className="text-sm font-semibold text-highlight">
                    {today.date}
                </p>
                <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                    {greeting()}, {firstName}
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Here’s what you’ve encoded today.
                </p>
            </div>

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Stat
                    label="Distributions"
                    value={today.distributions.count}
                    hint={
                        today.distributions.count > 0
                            ? `${reams(today.distributions.reams)} · ${peso(today.distributions.amount)}`
                            : 'None yet today'
                    }
                />
                <Stat
                    label="Remittances"
                    value={today.remittances.count}
                    hint={
                        today.remittances.count > 0
                            ? peso(today.remittances.amount)
                            : 'None yet today'
                    }
                />
                <Link
                    href={
                        auth.can.accessAdmin
                            ? '/admin/remittances'
                            : '/my-entries?tab=remittances'
                    }
                    className="col-span-2 sm:col-span-1"
                >
                    <Stat
                        tone={today.pending.count > 0 ? 'highlight' : 'default'}
                        label={
                            today.pending.scope === 'all'
                                ? 'Waiting for confirmation'
                                : 'Your pending'
                        }
                        value={
                            <span className="flex items-center gap-2">
                                <Clock className="size-5 text-highlight" />
                                {today.pending.count}
                            </span>
                        }
                        hint={
                            today.pending.count > 0
                                ? `${peso(today.pending.amount)} pending confirmation`
                                : 'All caught up'
                        }
                    />
                </Link>
            </div>

            <h2 className="mt-8 mb-3 text-sm font-bold tracking-wider text-muted-foreground uppercase">
                Quick entry
            </h2>
            <div className="grid gap-3 sm:grid-cols-2">
                {entryLinks.map((link, i) => (
                    <Link
                        key={link.href}
                        href={link.href}
                        prefetch
                        className={cn(
                            'group flex items-center gap-4 rounded-2xl border border-border bg-card p-4 transition hover:-translate-y-0.5 hover:shadow-md active:scale-[0.99] sm:p-5',
                            i === 0 && 'sm:col-span-2',
                        )}
                    >
                        <span
                            className={cn(
                                'flex size-14 shrink-0 items-center justify-center rounded-2xl',
                                link.tone,
                            )}
                        >
                            <link.icon className="size-7" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-lg font-bold">
                                New {link.title.toLowerCase()}
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                {link.description}
                            </span>
                        </span>
                        <ArrowRight className="size-5 text-muted-foreground transition group-hover:translate-x-1 group-hover:text-foreground" />
                    </Link>
                ))}
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2">
                <Link
                    href="/my-entries"
                    className="rounded-2xl border border-border bg-card px-5 py-4 text-sm font-semibold hover:bg-muted"
                >
                    My entries →
                </Link>
                <Link
                    href="/resellers"
                    className="rounded-2xl border border-border bg-card px-5 py-4 text-sm font-semibold hover:bg-muted"
                >
                    Reseller balances →
                </Link>
            </div>
        </StaffLayout>
    );
}
