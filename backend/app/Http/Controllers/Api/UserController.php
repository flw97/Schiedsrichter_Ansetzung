<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Referee;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rollenverwaltung (nur Master-Admin). Es muss immer mindestens ein aktiver Master-Admin bleiben. */
class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:60|unique:users,username',
            'email' => 'nullable|email|max:150',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(array_keys(Permissions::ROLES))],
        ]);
        $data['username'] = mb_strtolower(trim($data['username']));
        $user = User::create($data);
        $this->ensureRefereeProfile($user);
        AuditLog::change("Konto angelegt: {$data['name']} (".Permissions::label($data['role']).')');

        return $this->done();
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['role' => [Rule::in(array_keys(Permissions::ROLES))], 'active' => 'boolean']);
        $losesMaster = $user->role === 'master' && $user->active && (($data['role'] ?? 'master') !== 'master' || ($data['active'] ?? true) === false);
        abort_if($losesMaster && User::where('role', 'master')->where('active', true)->count() <= 1, 422, 'Es muss mindestens ein aktiver Master-Admin bleiben.');
        if (isset($data['role']) && $data['role'] !== $user->role) {
            AuditLog::change("Rolle von {$user->name} geändert: ".Permissions::label($user->role).' → '.Permissions::label($data['role']));
        }
        if (isset($data['active']) && $data['active'] !== $user->active) {
            AuditLog::change("Konto von {$user->name} ".($data['active'] ? 'aktiviert' : 'deaktiviert'));
            if (! $data['active']) {
                $user->tokens()->delete();
            }
        }
        $user->update($data);
        $this->ensureRefereeProfile($user);

        return $this->done();
    }

    /** Schiedsrichter-Konto ohne Vorbefüllung: Name und E-Mail kommen vom Konto, Standort trägt die Person selbst ein. */
    private function ensureRefereeProfile(User $user): void
    {
        if ($user->role !== 'schiri' || $user->referee_id) {
            return;
        }
        $referee = Referee::create([
            'name' => $user->name,
            'email' => $user->email,
            'available' => true,
        ]);
        $user->update(['referee_id' => $referee->id]);
    }
}
