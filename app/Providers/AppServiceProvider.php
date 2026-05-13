<?php

namespace App\Providers;

use App\Actions\Crm\ActivityTimelineObserver;
use App\Actions\Crm\TaskTimelineObserver;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Task;
use App\Policies\ActivityPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\DealPolicy;
use App\Policies\TaskPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);

        Activity::observe(ActivityTimelineObserver::class);
        Task::observe(TaskTimelineObserver::class);

        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(
            fn (): ?Password => app()->isProduction()
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
     * Configure request throttling.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('companies-read', function (Request $request): Limit {
            return Limit::perMinute(180)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('companies-write', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('contacts-read', function (Request $request): Limit {
            return Limit::perMinute(180)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('contacts-write', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('activities-read', function (Request $request): Limit {
            return Limit::perMinute(180)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('activities-write', function (
            Request $request,
        ): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('tasks-read', function (Request $request): Limit {
            return Limit::perMinute(180)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('tasks-write', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('deals-read', function (Request $request): Limit {
            return Limit::perMinute(180)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('deals-write', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });
    }
}
