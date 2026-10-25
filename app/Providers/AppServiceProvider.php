<?php

namespace App\Providers;

use App\Application\Staff\StaffCredentials;
use App\Enums\StaffPermission;
use App\Models\User;
use App\Support\LocalPostgres;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->configureDefaults();
        $this->configureStaffRateLimits();
        DevCommands::artisan('operations:publish --watch', 'operations');
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (app()->environment('local') && in_array($event->command, ['serve', 'dev'], true)
                && app(LocalPostgres::class)->startIfConfigured()) {
                DB::connection()->getPdo();
                $event->output->writeln('<info>PostgreSQL local disponível.</info>');
            }
        });
        RateLimiter::for('dining-public-read', fn (Request $request) => Limit::perMinute(120)->by('dining-read:'.$request->ip()));
        RateLimiter::for('dining-public-write', fn (Request $request) => Limit::perMinute(30)->by('dining-write:'.$request->ip()));
        foreach (StaffPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    private function configureStaffRateLimits(): void
    {
        RateLimiter::for('staff-login', function (Request $request): array {
            $input = $request->input('username');
            $username = is_string($input) ? StaffCredentials::normalizeUsername($input) : '';

            return [
                Limit::perMinute(5)->by('staff-account:'.hash('sha256', $username)),
                Limit::perMinute(30)->by('staff-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('staff-sensitive', fn (Request $request): array => [
            Limit::perMinute(10)->by('staff-sensitive-user:'.$request->user()?->id),
            Limit::perMinute(30)->by('staff-sensitive-ip:'.$request->ip()),
        ]);
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
}
