<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\AuditLog;
use App\Models\Referee;
use App\Models\User;
use App\Services\AuthTokens;
use App\Services\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

class AuthController extends Controller
{
    /** Immer dieselbe Antwort, damit die Abfrage keine Konten verrät. */
    private const RESET_HINT = 'Wenn ein Konto mit dieser Angabe und einer hinterlegten E-Mail existiert, haben wir einen Link zum Zurücksetzen geschickt.';

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

        return $this->done(AuthTokens::issue($user));
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => 'required|string|size:64']);
        $issued = AuthTokens::refresh($data['refresh_token']);
        abort_if($issued === null, 401);

        return response()->json($issued);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['login' => 'required|string|max:150'],
            ['login.required' => 'Bitte Benutzername oder E-Mail eingeben.'],
        );
        $user = $this->userByLogin($data['login']);
        if ($user && $user->active && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            try {
                $status = Password::broker()->sendResetLink(['email' => $user->email], function (User $user, string $token) {
                    try {
                        Mail::to($user->email)->send(new PasswordResetMail($user, $token));
                    } catch (Throwable $e) {
                        Password::broker()->deleteToken($user);
                        throw $e;
                    }
                });
            } catch (Throwable $e) {
                report($e);
                abort(503, 'Die E-Mail konnte nicht verschickt werden. Bitte versuch es gleich noch einmal.');
            }
            if ($status === Password::RESET_LINK_SENT) {
                AuditLog::access('Link zum Zurücksetzen des Passworts verschickt', $user);
            }
        }

        return response()->json(['message' => self::RESET_HINT]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => 'Der Link ist unvollständig.',
            'email.email' => 'Der Link ist unvollständig.',
            'token.required' => 'Der Link ist unvollständig.',
            'password.required' => 'Bitte ein neues Passwort eingeben.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen haben.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
        ]);
        $status = Password::reset($data, function (User $user, string $password) {
            if (! $user->active) {
                Password::broker()->deleteToken($user);
                abort(422, 'Dieses Konto ist deaktiviert.');
            }
            $user->forceFill(['password' => $password])->save();
            $user->revokeSessions();
            AuditLog::access('Passwort zurückgesetzt', $user);
        });
        abort_unless($status === Password::PASSWORD_RESET, 422, 'Der Link ist ungültig oder abgelaufen.');

        return response()->json(['message' => 'Das Passwort wurde gespeichert. Du kannst dich jetzt anmelden.']);
    }

    private function userByLogin(string $login): ?User
    {
        $login = mb_strtolower(trim($login));

        return User::firstWhere('username', $login)
            ?? User::query()->whereRaw('lower(email) = ?', [$login])->first();
    }

    public function logout(Request $request): JsonResponse
    {
        $plain = trim((string) $request->input('refresh_token', ''));
        $user = $plain === '' ? null : AuthTokens::revoke($plain);
        if ($user) {
            AuditLog::access('Abmeldung', $user);
        }

        return response()->json(['pushes' => []]);
    }
}
