<?php

namespace App\Providers;

use App\Auth\JwtGuard;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\CrawlerNuLigaClient;
use App\Services\NuLigaClient;
use App\Services\NuLigaFeed;
use App\Services\Push;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Je Anfrage gesammelte Push-Mitteilungen
        $this->app->scoped(Push::class);
        $this->app->bind(NuLigaFeed::class, function () {
            $token = config('services.nuliga.token');
            if (is_string($token) && $token !== '') {
                return new CrawlerNuLigaClient(rtrim((string) config('services.nuliga.url'), '/'), $token);
            }

            return new NuLigaClient;
        });
    }

    public function boot(): void
    {
        foreach (array_keys(Permissions::MATRIX) as $permission) {
            Gate::define($permission, fn (User $user) => $user->active && Permissions::allows($user->role, $permission));
        }
        // Abgelehnte Aktionen werden protokolliert
        Gate::after(function (User $user, string $ability, ?bool $result) {
            if ($result === false && isset(Permissions::MATRIX[$ability])) {
                AuditLog::access("Aktion abgelehnt — Berechtigung fehlt ({$ability})", $user);
            }
        });

        Auth::extend('jwt', fn ($app, $name, $config) => new JwtGuard);
    }
}
