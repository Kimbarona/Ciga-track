import { Deferred, Head } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { Pill } from '@/components/staff/status-badge';
import { EmptyState, ListSkeleton, PageHeader } from '@/components/staff/ui';
import StaffLayout from '@/layouts/staff-layout';
import { peso } from '@/lib/format';
import { cn } from '@/lib/utils';

type ProductStock = {
    id: number;
    code: string;
    name: string;
    stock: number;
    reorder_level: number;
    is_low: boolean;
    avg_cost?: number;
    stock_value?: number;
};

type Props = {
    showCosts: boolean;
    products?: ProductStock[];
};

export default function Stocks({ showCosts, products }: Props) {
    return (
        <StaffLayout>
            <Head title="Stocks" />
            <PageHeader title="Stocks" description="Reams on hand right now." />

            <Deferred data="products" fallback={<ListSkeleton rows={4} />}>
                <StockList products={products ?? []} showCosts={showCosts} />
            </Deferred>
        </StaffLayout>
    );
}

function StockList({
    products,
    showCosts,
}: {
    products: ProductStock[];
    showCosts: boolean;
}) {
    if (products.length === 0) {
        return (
            <EmptyState
                icon={<Boxes className="size-6" />}
                title="No products yet"
            >
                An admin can add products in the admin panel.
            </EmptyState>
        );
    }

    const lowCount = products.filter((p) => p.is_low).length;
    const totalValue = products.reduce(
        (sum, p) => sum + (p.stock_value ?? 0),
        0,
    );

    return (
        <>
            {lowCount > 0 && (
                <div className="mb-4 rounded-2xl bg-danger-soft px-4 py-3 text-sm font-semibold text-danger">
                    {lowCount} product{lowCount > 1 ? 's are' : ' is'} running
                    low — time to reorder.
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2">
                {products.map((p) => (
                    <div
                        key={p.id}
                        className={cn(
                            'rounded-2xl border bg-card p-5',
                            p.is_low ? 'border-danger/40' : 'border-border',
                        )}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <div className="inline-flex rounded-lg bg-primary px-2.5 py-1 text-sm font-bold text-primary-foreground dark:bg-secondary dark:text-foreground">
                                    {p.code}
                                </div>
                                <div className="mt-1.5 text-sm text-muted-foreground">
                                    {p.name}
                                </div>
                            </div>
                            {p.is_low ? (
                                <Pill tone="danger">Low stock</Pill>
                            ) : (
                                <Pill tone="success">OK</Pill>
                            )}
                        </div>
                        <div className="mt-4 flex items-end justify-between">
                            <div>
                                <div
                                    className={cn(
                                        'num text-4xl font-extrabold',
                                        p.is_low
                                            ? 'text-danger'
                                            : 'text-foreground',
                                    )}
                                >
                                    {p.stock}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    reams · reorder at {p.reorder_level}
                                </div>
                            </div>
                            {showCosts && p.avg_cost !== undefined && (
                                <div className="text-right text-xs text-muted-foreground">
                                    <div>
                                        Avg cost{' '}
                                        <span className="num font-semibold text-foreground">
                                            {peso(p.avg_cost)}
                                        </span>
                                    </div>
                                    <div>
                                        Value{' '}
                                        <span className="num font-semibold text-foreground">
                                            {peso(p.stock_value ?? 0)}
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {showCosts && (
                <p className="mt-4 text-right text-sm text-muted-foreground">
                    Total stock value{' '}
                    <span className="num font-bold text-foreground">
                        {peso(totalValue)}
                    </span>
                </p>
            )}
        </>
    );
}
