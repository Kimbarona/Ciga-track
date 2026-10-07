import { usePage } from '@inertiajs/react';
import { AppMark } from '@/layouts/staff-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10">
            {/* faint ledger lines */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 opacity-[0.35] dark:opacity-[0.08]"
                style={{
                    backgroundImage:
                        'repeating-linear-gradient(to bottom, transparent 0 31px, var(--border) 31px 32px)',
                }}
            />
            <div className="relative w-full max-w-sm">
                <div className="flex flex-col gap-8 rounded-3xl border border-border bg-card p-8 shadow-xl shadow-primary/5">
                    <div className="flex flex-col items-center gap-4">
                        <AppMark className="size-14 rounded-2xl" />
                        <div className="text-center">
                            <div className="text-sm font-semibold text-highlight">
                                {name}
                            </div>
                            <h1 className="mt-1 text-xl font-bold">{title}</h1>
                            <p className="mt-1 text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
                <p className="mt-6 text-center text-xs text-muted-foreground">
                    Internal tool · accounts are created by an admin
                </p>
            </div>
        </div>
    );
}
