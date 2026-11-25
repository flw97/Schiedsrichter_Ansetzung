<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class AuditLog extends Model
{
    /** Zugriffsprotokoll: Anmeldungen, Abmeldungen, abgelehnte Aktionen, angezeigte Kontaktdaten. */
    public static function access(string $text, ?User $user = null): void
    {
        $user ??= auth()->user();
        self::create(['kind' => 'access', 'text' => ($user ? "{$user->name} (".Permissions::label($user->role).')' : 'System').": {$text}"]);
    }

    /** Änderungsprotokoll der Rollenverwaltung. */
    public static function change(string $text): void
    {
        self::create(['kind' => 'change', 'text' => (auth()->user()->name ?? 'System').": {$text}"]);
    }
}
