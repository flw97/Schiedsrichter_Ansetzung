<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Jwt;

/** Kurzlebiges Zugangs-JWT und widerrufbares Refresh-Token. */
final class AuthTokens
{
    public const ACCESS_SECONDS = 900;

    public const REFRESH_DAYS = 7;

    /** @return array{token: string, refresh_token: string, expires_in: int} */
    public static function issue(User $user): array
    {
        $now = now()->getTimestamp();
        $token = Jwt::encode([
            'sub' => $user->id,
            'ver' => (int) $user->token_version,
            'typ' => 'access',
            'iat' => $now,
            'exp' => $now + self::ACCESS_SECONDS,
        ]);

        return [
            'token' => $token,
            'refresh_token' => self::storeRefresh($user),
            'expires_in' => self::ACCESS_SECONDS,
        ];
    }

    /** @return array{token: string, refresh_token: string, expires_in: int}|null */
    public static function refresh(string $plain): ?array
    {
        $row = RefreshToken::query()->where('token_hash', self::hash($plain))->first();
        if (! $row || $row->expires_at->lte(now())) {
            $row?->delete();

            return null;
        }
        $user = $row->user;
        if (! $user || ! $user->active) {
            $user?->refreshTokens()->delete();

            return null;
        }
        // Nur eine der parallelen Erneuerungen behält das Token.
        if (RefreshToken::query()->whereKey($row->id)->delete() === 0) {
            return null;
        }

        return self::issue($user);
    }

    public static function revoke(string $plain): ?User
    {
        $row = RefreshToken::query()->where('token_hash', self::hash($plain))->first();
        if (! $row) {
            return null;
        }
        $user = $row->user;
        $row->delete();

        return $user;
    }

    public static function userFromAccess(string $jwt): ?User
    {
        $claims = Jwt::decode($jwt);
        if ($claims === null || ($claims['typ'] ?? null) !== 'access') {
            return null;
        }
        $user = User::find($claims['sub'] ?? null);
        if (! $user || ! $user->active) {
            return null;
        }
        if ((int) $user->token_version !== (int) ($claims['ver'] ?? -1)) {
            return null;
        }

        return $user;
    }

    private static function storeRefresh(User $user): string
    {
        $plain = bin2hex(random_bytes(32));
        $user->refreshTokens()->create([
            'token_hash' => self::hash($plain),
            'expires_at' => now()->addDays(self::REFRESH_DAYS),
        ]);

        return $plain;
    }

    private static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
