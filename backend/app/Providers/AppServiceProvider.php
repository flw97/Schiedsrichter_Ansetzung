<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\CrawlerNuLigaClient;
use App\Services\NuLigaClient;
use App\Services\NuLigaFeed;
use App\Services\Push;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /** Automatische Abmeldung nach Inaktivität. */
    public const IDLE_MINUTES = 15;

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

        Sanctum::authenticateAccessTokensUsing(fn (PersonalAccessToken $token, bool $valid) => $valid
            && ($token->last_used_at ?? $token->created_at)->gt(now()->subMinutes(self::IDLE_MINUTES)));
    }
}
