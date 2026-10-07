import { Check, ChevronDown, Search, Store } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { ResellerOption } from '@/types/ream';
import { Pill } from './status-badge';
import { inputClass } from './ui';

/**
 * Searchable reseller picker — a big tap target that opens a bottom sheet.
 */
export function ResellerPicker({
    resellers,
    value,
    onChange,
    invalid,
    id,
}: {
    resellers: ResellerOption[];
    value: number | null;
    onChange: (id: number) => void;
    invalid?: boolean;
    id?: string;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = resellers.find((r) => r.id === value) ?? null;

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return q === ''
            ? resellers
            : resellers.filter(
                  (r) =>
                      r.name.toLowerCase().includes(q) ||
                      (r.location ?? '').toLowerCase().includes(q),
              );
    }, [query, resellers]);

    return (
        <>
            <button
                id={id}
                type="button"
                aria-invalid={invalid}
                onClick={() => setOpen(true)}
                className={cn(
                    inputClass,
                    'flex items-center justify-between text-left',
                )}
            >
                {selected ? (
                    <span className="flex min-w-0 items-center gap-2">
                        <span className="truncate font-medium">
                            {selected.name}
                        </span>
                        {selected.type === 'retail' && (
                            <Pill tone="warning">Retail</Pill>
                        )}
                    </span>
                ) : (
                    <span className="text-muted-foreground/70">
                        Choose reseller…
                    </span>
                )}
                <ChevronDown className="size-5 shrink-0 text-muted-foreground" />
            </button>

            <Sheet
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) setQuery('');
                }}
            >
                <SheetContent
                    side="bottom"
                    className="mx-auto max-h-[85svh] w-full max-w-xl rounded-t-3xl px-4 pb-6"
                >
                    <SheetHeader className="px-1 pb-0">
                        <SheetTitle>Choose reseller</SheetTitle>
                        <SheetDescription>
                            Use “Retail” for walk-in customers.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted-foreground" />
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search name or location"
                            className={cn(inputClass, 'pl-11')}
                        />
                    </div>
                    <div className="-mx-1 flex-1 overflow-y-auto px-1">
                        {filtered.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                No reseller matches “{query}”.
                            </p>
                        ) : (
                            <ul className="space-y-1.5">
                                {filtered.map((r) => (
                                    <li key={r.id}>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                onChange(r.id);
                                                setOpen(false);
                                                setQuery('');
                                            }}
                                            className={cn(
                                                'flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition',
                                                r.id === value
                                                    ? 'border-highlight bg-highlight-soft'
                                                    : 'border-transparent hover:bg-muted',
                                            )}
                                        >
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-bold text-secondary-foreground">
                                                {r.type === 'retail' ? (
                                                    <Store className="size-4" />
                                                ) : (
                                                    r.name
                                                        .charAt(0)
                                                        .toUpperCase()
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-semibold">
                                                    {r.name}
                                                </span>
                                                {r.location && (
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {r.location}
                                                    </span>
                                                )}
                                            </span>
                                            {r.type === 'retail' && (
                                                <Pill tone="warning">
                                                    Retail
                                                </Pill>
                                            )}
                                            {r.id === value && (
                                                <Check className="size-5 text-highlight" />
                                            )}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
