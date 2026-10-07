<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Back-office for admins and super admins. Login happens on the shared /login page
 * (no separate Filament login), so the staff app and the admin feel like one product.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn () => $this->brandName())
            ->font('Plus Jakarta Sans')
            ->colors([
                'primary' => Color::hex('#1F3864'),
                'warning' => Color::hex('#E8A33D'),
                'gray' => Color::Stone,
                'success' => Color::Green,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->navigationGroups([
                NavigationGroup::make('Transactions'),
                NavigationGroup::make('Master Data'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Settings'),
            ])
            ->userMenuItems([
                Action::make('staff-app')
                    ->label('Open staff app')
                    ->url('/')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile),
            ])
            ->databaseNotifications(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureUserIsActive::class,
                ForcePasswordChange::class,
            ]);
    }

    private function brandName(): string
    {
        try {
            return Schema::hasTable('settings') ? (string) Setting::get('business_name') : config('app.name');
        } catch (\Throwable) {
            return config('app.name');
        }
    }
}
