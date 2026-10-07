import { Link, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    Boxes,
    ClipboardList,
    ExternalLink,
    FileStack,
    Home,
    LogOut,
    Plus,
    Receipt,
    ShoppingCart,
    Truck,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { Toaster, toast } from 'sonner';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import type { Flash } from '@/types/auth';

type NavLink = {
    title: string;
    href: string;
    icon: LucideIcon;
    match?: (url: string) => boolean;
};

export const entryLinks: (NavLink & { description: string; tone: string })[] = [
    {
        title: 'Distribution',
        description: 'Reams out to a reseller or retail',
        href: '/distributions/new',
        icon: Truck,
        tone: 'bg-primary text-primary-foreground',
    },
    {
        title: 'Remittance',
        description: 'Payment received from a reseller',
        href: '/remittances/new',
        icon: Banknote,
        tone: 'bg-highlight text-highlight-foreground',
    },
    {
        title: 'Purchase',
        description: 'Stock in from a supplier',
        href: '/purchases/new',
        icon: ShoppingCart,
        tone: 'bg-secondary text-secondary-foreground',
    },
    {
        title: 'Expense',
        description: 'Delivery, gas, packaging…',
        href: '/expenses/new',
        icon: Receipt,
        tone: 'bg-secondary text-secondary-foreground',
    },
];

const mainLinks: NavLink[] = [
    { title: 'Home', href: '/', icon: Home, match: (url) => url === '/' },
    { title: 'Stocks', href: '/stocks', icon: Boxes },
    { title: 'Resellers', href: '/resellers', icon: Users },
    { title: 'My entries', href: '/my-entries', icon: ClipboardList },
    {
        title: 'Profile',
        href: '/profile',
        icon: UserRound,
        match: (url) =>
            url.startsWith('/profile') || url.startsWith('/settings'),
    },
];

function isActive(link: NavLink, url: string): boolean {
    const path = url.split('?')[0];

    return link.match
        ? link.match(path)
        : path === link.href || path.startsWith(`${link.href}/`);
}

function showFlash(flash: Flash | undefined) {
    if (!flash) return;
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.warning) toast.warning(flash.warning);
}

export function AppMark({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm dark:bg-highlight dark:text-highlight-foreground',
                className,
            )}
        >
            <FileStack className="size-5" strokeWidth={2.25} />
        </div>
    );
}

export default function StaffLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const { auth, name, flash } = page.props;
    const url = page.url;
    const [entryOpen, setEntryOpen] = useState(false);
    const { resolvedAppearance } = useAppearance();

    // Toasts for server flash messages (initial load + every successful visit).
    useEffect(() => {
        showFlash(flash);

        return router.on('success', (event) =>
            showFlash(event.detail.page.props.flash as Flash),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const logout = () => router.post('/logout');

    return (
        <div className="min-h-svh bg-background">
            <Toaster
                position="top-center"
                richColors
                closeButton
                theme={resolvedAppearance}
            />

            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col border-r border-sidebar-border bg-sidebar lg:flex">
                <div className="flex items-center gap-3 px-6 pt-6 pb-5">
                    <AppMark />
                    <div className="min-w-0">
                        <div className="truncate text-base font-bold text-sidebar-foreground">
                            {name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Ream ledger
                        </div>
                    </div>
                </div>

                <nav className="flex-1 space-y-6 overflow-y-auto px-4 pb-4">
                    <div>
                        <div className="px-3 pb-2 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                            Encode
                        </div>
                        <div className="space-y-1">
                            {entryLinks.map((link) => (
                                <SidebarLink
                                    key={link.href}
                                    link={link}
                                    active={isActive(link, url)}
                                />
                            ))}
                        </div>
                    </div>
                    <div>
                        <div className="px-3 pb-2 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                            Look up
                        </div>
                        <div className="space-y-1">
                            {mainLinks.map((link) => (
                                <SidebarLink
                                    key={link.href}
                                    link={link}
                                    active={isActive(link, url)}
                                />
                            ))}
                        </div>
                    </div>
                </nav>

                <div className="space-y-2 border-t border-sidebar-border p-4">
                    {auth.can.accessAdmin && (
                        <a
                            href="/admin"
                            className="flex items-center justify-between rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground hover:brightness-110"
                        >
                            Admin panel <ExternalLink className="size-4" />
                        </a>
                    )}
                    <div className="flex items-center gap-3 rounded-xl px-2 py-2">
                        <div className="flex size-9 items-center justify-center rounded-full bg-highlight-soft text-sm font-bold text-highlight">
                            {auth.user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-sm font-semibold">
                                {auth.user.name}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {auth.user.role_label}
                            </div>
                        </div>
                        <button
                            onClick={logout}
                            className="rounded-lg p-2 text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
                            title="Log out"
                        >
                            <LogOut className="size-4" />
                        </button>
                    </div>
                </div>
            </aside>

            {/* Mobile top bar */}
            <header className="sticky top-0 z-20 flex items-center justify-between border-b border-border bg-background/90 px-4 py-3 backdrop-blur lg:hidden">
                <Link href="/" className="flex items-center gap-2.5">
                    <AppMark className="size-9" />
                    <span className="text-base font-bold">{name}</span>
                </Link>
                {auth.can.accessAdmin && (
                    <a
                        href="/admin"
                        className="rounded-full bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground"
                    >
                        Admin
                    </a>
                )}
            </header>

            <main className="px-4 pt-5 pb-28 sm:px-6 lg:ml-72 lg:px-10 lg:pt-10 lg:pb-12">
                <div className="mx-auto w-full max-w-4xl">{children}</div>
            </main>

            {/* Mobile bottom tabs */}
            <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
                <div className="mx-auto grid max-w-md grid-cols-5 items-end px-2">
                    <TabLink
                        link={mainLinks[0]}
                        active={isActive(mainLinks[0], url)}
                    />
                    <TabLink
                        link={mainLinks[1]}
                        active={isActive(mainLinks[1], url)}
                    />
                    <button
                        onClick={() => setEntryOpen(true)}
                        className="flex flex-col items-center gap-1 pt-1 pb-2 text-[11px] font-semibold text-foreground"
                    >
                        <span className="-mt-6 flex size-14 items-center justify-center rounded-2xl bg-highlight text-highlight-foreground shadow-lg ring-4 ring-background">
                            <Plus className="size-7" strokeWidth={2.5} />
                        </span>
                        New Entry
                    </button>
                    <TabLink
                        link={mainLinks[2]}
                        active={isActive(mainLinks[2], url)}
                    />
                    <TabLink
                        link={mainLinks[4]}
                        active={isActive(mainLinks[4], url)}
                    />
                </div>
            </nav>

            <Sheet open={entryOpen} onOpenChange={setEntryOpen}>
                <SheetContent
                    side="bottom"
                    className="rounded-t-3xl px-4 pb-[calc(env(safe-area-inset-bottom)+1.5rem)]"
                >
                    <SheetHeader className="px-1 pb-0">
                        <SheetTitle className="text-lg">New entry</SheetTitle>
                        <SheetDescription>
                            What do you want to record?
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-3">
                        {entryLinks.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                onClick={() => setEntryOpen(false)}
                                className="flex items-center gap-4 rounded-2xl border border-border bg-card p-4 active:scale-[0.99]"
                            >
                                <span
                                    className={cn(
                                        'flex size-12 items-center justify-center rounded-xl',
                                        link.tone,
                                    )}
                                >
                                    <link.icon className="size-6" />
                                </span>
                                <span>
                                    <span className="block text-base font-semibold">
                                        {link.title}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {link.description}
                                    </span>
                                </span>
                            </Link>
                        ))}
                        <Link
                            href="/my-entries"
                            onClick={() => setEntryOpen(false)}
                            className="py-2 text-center text-sm font-semibold text-primary"
                        >
                            View my entries
                        </Link>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}

function SidebarLink({ link, active }: { link: NavLink; active: boolean }) {
    return (
        <Link
            href={link.href}
            prefetch
            className={cn(
                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                active
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                    : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-foreground',
            )}
        >
            <link.icon className="size-[18px]" />
            {link.title}
        </Link>
    );
}

function TabLink({ link, active }: { link: NavLink; active: boolean }) {
    return (
        <Link
            href={link.href}
            prefetch
            className={cn(
                'flex flex-col items-center gap-1 pt-2.5 pb-2 text-[11px] font-semibold',
                active
                    ? 'text-primary dark:text-highlight'
                    : 'text-muted-foreground',
            )}
        >
            <link.icon className="size-6" strokeWidth={active ? 2.4 : 2} />
            {link.title}
        </Link>
    );
}
