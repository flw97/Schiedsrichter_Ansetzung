<?php

namespace App\Support;

use RuntimeException;

/** HS256-JWT. Der Algorithmus im Header wird ignoriert, geprüft wird immer HMAC-SHA256. */
final class Jwt
{
    /** @param  array<string, int|string>  $claims */
    public static function encode(array $claims): string
    {
        $header = self::b64((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $payload = self::b64((string) json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = self::b64(hash_hmac('sha256', "{$header}.{$payload}", self::key(), true));

        return "{$header}.{$payload}.{$signature}";
    }

    /** @return array<string, mixed>|null */
    public static function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $expected = hash_hmac('sha256', "{$header}.{$payload}", self::key(), true);
        $given = self::b64d($signature);
        if ($given === null || strlen($given) !== strlen($expected) || ! hash_equals($expected, $given)) {
            return null;
        }
        $claims = json_decode(self::b64d($payload) ?? '', true);
        if (! is_array($claims) || ! isset($claims['exp'])) {
            return null;
        }
        if (now()->getTimestamp() >= (int) $claims['exp']) {
            return null;
        }

        return $claims;
    }

    private static function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (! is_string($decoded) || $decoded === '') {
                throw new RuntimeException('APP_KEY is invalid.');
            }

            return $decoded;
        }
        if ($key === '') {
            throw new RuntimeException('APP_KEY is missing.');
        }

        return $key;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64d(string $data): ?string
    {
        $pad = strlen($data) % 4;
        if ($pad !== 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode(strtr($data, '-_', '+/'), true);

        return $raw === false ? null : $raw;
    }
}
