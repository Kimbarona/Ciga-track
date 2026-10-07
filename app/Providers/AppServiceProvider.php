<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ActivityPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Role gates from the access matrix (model policies live in app/Policies).
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Costs, profit, P&L and stock value.
        Gate::define('view-financials', fn (User $user) => $user->isAdmin());
        Gate::define('view-reports', fn (User $user) => $user->isAdmin());
        Gate::define('view-audit-log', fn (User $user) => $user->isAdmin());
        Gate::define('manage-settings', fn (User $user) => $user->isSuperAdmin());
    }
}
