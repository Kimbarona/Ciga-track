import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    LogOut,
    Monitor,
    Moon,
    ShieldCheck,
    Sun,
} from 'lucide-react';
import {
    Field,
    PageHeader,
    Panel,
    PrimaryButton,
    TextInput,
} from '@/components/staff/ui';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';

export default function Profile() {
    const { auth } = usePage().props;
    const { appearance, updateAppearance } = useAppearance();

    const profile = useForm({ name: auth.user.name, email: auth.user.email });
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const saveProfile = (e: React.FormEvent) => {
        e.preventDefault();
        profile.patch('/settings/profile', { preserveScroll: true });
    };

    const savePassword = (e: React.FormEvent) => {
        e.preventDefault();
        password.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
            onError: () => password.reset('password', 'password_confirmation'),
        });
    };

    const themes: { value: Appearance; label: string; icon: typeof Sun }[] = [
        { value: 'light', label: 'Light', icon: Sun },
        { value: 'dark', label: 'Dark', icon: Moon },
        { value: 'system', label: 'System', icon: Monitor },
    ];

    return (
        <StaffLayout>
            <Head title="Profile" />
            <PageHeader
                title="Profile"
                description={`${auth.user.role_label} · ${auth.user.email}`}
            />

            <div className="space-y-5">
                <Panel>
                    <h2 className="mb-4 text-lg font-bold">Your details</h2>
                    <form
                        onSubmit={saveProfile}
                        className="grid gap-5 sm:grid-cols-2"
                    >
                        <Field
                            label="Name"
                            htmlFor="name"
                            error={profile.errors.name}
                        >
                            <TextInput
                                id="name"
                                value={profile.data.name}
                                autoComplete="name"
                                onChange={(e) =>
                                    profile.setData('name', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Email"
                            htmlFor="email"
                            error={profile.errors.email}
                        >
                            <TextInput
                                id="email"
                                type="email"
                                value={profile.data.email}
                                autoComplete="email"
                                onChange={(e) =>
                                    profile.setData('email', e.target.value)
                                }
                            />
                        </Field>
                        <div className="sm:col-span-2">
                            <PrimaryButton
                                type="submit"
                                disabled={
                                    profile.processing || !profile.isDirty
                                }
                            >
                                {profile.recentlySuccessful
                                    ? 'Saved'
                                    : 'Save details'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>

                <Panel>
                    <h2 className="mb-1 text-lg font-bold">Change password</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        Use at least 8 characters. Don’t share it with anyone.
                    </p>
                    <form
                        onSubmit={savePassword}
                        className="grid gap-5 sm:grid-cols-3"
                    >
                        <Field
                            label="Current password"
                            htmlFor="current_password"
                            error={password.errors.current_password}
                        >
                            <TextInput
                                id="current_password"
                                type="password"
                                autoComplete="current-password"
                                value={password.data.current_password}
                                onChange={(e) =>
                                    password.setData(
                                        'current_password',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="New password"
                            htmlFor="password"
                            error={password.errors.password}
                        >
                            <TextInput
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                value={password.data.password}
                                onChange={(e) =>
                                    password.setData('password', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Confirm new password"
                            htmlFor="password_confirmation"
                            error={password.errors.password_confirmation}
                        >
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={password.data.password_confirmation}
                                onChange={(e) =>
                                    password.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                        <div className="sm:col-span-3">
                            <PrimaryButton
                                type="submit"
                                disabled={password.processing}
                            >
                                {password.recentlySuccessful
                                    ? 'Password updated'
                                    : 'Update password'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>

                <Panel>
                    <h2 className="mb-4 text-lg font-bold">Appearance</h2>
                    <div className="grid grid-cols-3 gap-2">
                        {themes.map((t) => (
                            <button
                                key={t.value}
                                type="button"
                                onClick={() => updateAppearance(t.value)}
                                className={cn(
                                    'flex flex-col items-center gap-2 rounded-xl border px-3 py-4 text-sm font-semibold transition',
                                    appearance === t.value
                                        ? 'border-highlight bg-highlight-soft'
                                        : 'border-border hover:bg-muted',
                                )}
                            >
                                <t.icon className="size-5" />
                                {t.label}
                            </button>
                        ))}
                    </div>
                </Panel>

                <div className="grid gap-3 sm:grid-cols-3">
                    <Link
                        href="/settings/two-factor"
                        className="flex items-center gap-3 rounded-2xl border border-border bg-card px-5 py-4 text-sm font-semibold hover:bg-muted"
                    >
                        <ShieldCheck className="size-5 text-highlight" />{' '}
                        Two-factor login
                    </Link>
                    {auth.can.accessAdmin && (
                        <a
                            href="/admin"
                            className="flex items-center gap-3 rounded-2xl bg-primary px-5 py-4 text-sm font-semibold text-primary-foreground hover:brightness-110"
                        >
                            <ExternalLink className="size-5" /> Open admin panel
                        </a>
                    )}
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="flex items-center gap-3 rounded-2xl border border-border bg-card px-5 py-4 text-left text-sm font-semibold text-danger hover:bg-danger-soft"
                    >
                        <LogOut className="size-5" /> Log out
                    </button>
                </div>
            </div>
        </StaffLayout>
    );
}
