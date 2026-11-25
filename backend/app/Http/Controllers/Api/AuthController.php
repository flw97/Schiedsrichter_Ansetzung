<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Referee;
use App\Models\User;
use App\Services\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, Push $push): JsonResponse
    {
        $data = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        $user = User::firstWhere('username', mb_strtolower(trim($data['username'])));
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            abort(422, 'Benutzername oder Passwort ist falsch.');
        }
        if (! $user->active) {
            AuditLog::access('Anmeldung abgelehnt (Konto deaktiviert)', $user);
            abort(422, 'Dieses Konto ist deaktiviert.');
        }
        AuditLog::access('Anmeldung', $user);

        // Lizenz-Erinnerung: 1 Jahr vor Lizenzende Push an den Schiedsrichter (einmal je Lizenzzeitraum)
        Referee::whereNotNull('license_valid_until')->get()->filter->reminderDue()->each(function (Referee $r) use ($push) {
            $r->update(['license_reminded_for' => $r->license_valid_until]);
            $push->referee($r, "Deine Lizenz ({$r->license}) läuft am {$r->license_valid_until->format('d.m.Y')} ab. Bitte rechtzeitig einen Leistungsnachweis erbringen.");
        });

        return $this->done(['token' => $user->createToken('spa')->plainTextToken]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLog::access($request->boolean('timeout') ? 'Automatische Abmeldung nach 15 Minuten Inaktivität' : 'Abmeldung');
        $request->user()->currentAccessToken()->delete();

        return $this->done();
    }
}
