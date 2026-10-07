import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export function PageHeader({
    title,
    description,
    back,
    action,
}: {
    title: string;
    description?: ReactNode;
    back?: string;
    action?: ReactNode;
}) {
    return (
        <div className="mb-6 flex items-start justify-between gap-4">
            <div className="min-w-0">
                {back && (
                    <Link
                        href={back}
                        className="mb-2 -ml-1 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        <ChevronLeft className="size-4" /> Back
                    </Link>
                )}
                <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}

export function Panel({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            className={cn(
                'rounded-2xl border border-border bg-card p-5 shadow-[0_1px_0_rgba(31,56,100,0.04)] sm:p-6',
                className,
            )}
        >
            {children}
        </section>
    );
}

export function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <label
                htmlFor={htmlFor}
                className="text-sm font-semibold text-foreground"
            >
                {label}
            </label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

/** Large, touch-friendly input styles shared by native inputs/selects. */
export const inputClass =
    'h-12 w-full rounded-xl border border-input bg-card px-3.5 text-base text-foreground shadow-xs outline-none transition placeholder:text-muted-foreground/70 focus-visible:border-highlight focus-visible:ring-4 focus-visible:ring-highlight/20 disabled:opacity-60 aria-invalid:border-danger';

export function NativeSelect({
    className,
    children,
    ...props
}: React.ComponentProps<'select'>) {
    return (
        <div className="relative">
            <select
                className={cn(inputClass, 'appearance-none pr-10', className)}
                {...props}
            >
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-muted-foreground" />
        </div>
    );
}

export function TextInput({
    className,
    ...props
}: React.ComponentProps<'input'>) {
    return <input className={cn(inputClass, className)} {...props} />;
}

export function TextArea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(inputClass, 'h-auto min-h-20 py-3', className)}
            {...props}
        />
    );
}

export function PrimaryButton({
    className,
    children,
    ...props
}: React.ComponentProps<'button'>) {
    return (
        <button
            className={cn(
                'inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-highlight px-6 text-base font-bold text-highlight-foreground shadow-sm transition hover:brightness-95 focus-visible:ring-4 focus-visible:ring-highlight/30 focus-visible:outline-none disabled:opacity-60 sm:w-auto',
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}

export function EmptyState({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card/60 px-6 py-12 text-center">
            <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-highlight-soft text-highlight">
                {icon}
            </div>
            <h3 className="text-base font-semibold">{title}</h3>
            {children && (
                <div className="mt-1 max-w-sm text-sm text-muted-foreground">
                    {children}
                </div>
            )}
        </div>
    );
}

export function ListSkeleton({ rows = 5 }: { rows?: number }) {
    return (
        <div className="space-y-3">
            {Array.from({ length: rows }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-4 rounded-2xl border border-border bg-card p-4"
                >
                    <Skeleton className="size-10 rounded-full" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-4 w-2/5" />
                        <Skeleton className="h-3 w-1/4" />
                    </div>
                    <Skeleton className="h-5 w-20" />
                </div>
            ))}
        </div>
    );
}

export function Stat({
    label,
    value,
    hint,
    tone = 'default',
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    tone?: 'default' | 'highlight';
}) {
    return (
        <div
            className={cn(
                'rounded-2xl border p-4',
                tone === 'highlight'
                    ? 'border-highlight/40 bg-highlight-soft'
                    : 'border-border bg-card',
            )}
        >
            <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-1 num text-2xl font-bold text-foreground">
                {value}
            </div>
            {hint && (
                <div className="mt-0.5 text-xs text-muted-foreground">
                    {hint}
                </div>
            )}
        </div>
    );
}
