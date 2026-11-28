<?php

namespace App\Auth;

use App\Services\AuthTokens;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

/** Bearer-JWT. Ein per actingAs gesetzter Benutzer gilt nur, wenn die Anfrage kein Token mitschickt. */
class JwtGuard implements Guard
{
    private ?Authenticatable $user = null;

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        $bearer = request()->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return AuthTokens::userFromAccess($bearer);
        }

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user() !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }
}
