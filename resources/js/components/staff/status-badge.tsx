import { cn } from '@/lib/utils';
import type {
    BalanceStatus,
    PriceSource,
    RemittanceStatus,
} from '@/types/ream';

type Tone = 'danger' | 'success' | 'warning' | 'neutral' | 'info';

const tones: Record<Tone, string> = {
    danger: 'bg-danger-soft text-danger',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    neutral: 'bg-neutral-soft text-muted-foreground',
    info: 'bg-secondary text-secondary-foreground',
};

export function Pill({
    tone,
    children,
    className,
}: {
    tone: Tone;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap',
                tones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}

const balanceMeta: Record<BalanceStatus, { tone: Tone; label: string }> = {
    may_balance: { tone: 'danger', label: 'May Balance' },
    paid: { tone: 'success', label: 'Paid' },
    overpaid: { tone: 'warning', label: 'Overpaid' },
};

export function BalanceBadge({ status }: { status: BalanceStatus }) {
    const meta = balanceMeta[status];

    return <Pill tone={meta.tone}>{meta.label}</Pill>;
}

const sourceMeta: Record<PriceSource, { tone: Tone; label: string }> = {
    default: { tone: 'neutral', label: 'Default' },
    special: { tone: 'info', label: 'Special' },
    retail: { tone: 'warning', label: 'Retail' },
    manual: { tone: 'danger', label: 'Manual' },
};

export function SourceBadge({ source }: { source: PriceSource }) {
    const meta = sourceMeta[source];

    return <Pill tone={meta.tone}>{meta.label}</Pill>;
}

const remittanceMeta: Record<RemittanceStatus, { tone: Tone; label: string }> =
    {
        pending: { tone: 'neutral', label: 'Pending' },
        confirmed: { tone: 'success', label: 'Confirmed' },
        rejected: { tone: 'danger', label: 'Rejected' },
    };

export function RemittanceBadge({ status }: { status: RemittanceStatus }) {
    const meta = remittanceMeta[status];

    return <Pill tone={meta.tone}>{meta.label}</Pill>;
}

/** Badge for "my entries" rows (status, price source or voided). */
export function EntryBadge({ value }: { value: string | null }) {
    if (!value) return null;
    if (value === 'voided') return <Pill tone="danger">Voided</Pill>;
    if (value in remittanceMeta)
        return <RemittanceBadge status={value as RemittanceStatus} />;
    if (value in sourceMeta)
        return <SourceBadge source={value as PriceSource} />;

    return <Pill tone="neutral">{value}</Pill>;
}
