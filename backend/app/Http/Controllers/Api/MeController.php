<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Licenses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Eigenes Konto, eigene Lizenz und eigener Standort. Die Übersichten bleiben in der Navigation links. */
class MeController extends Controller
{
    public function updateAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($request->exists('username')) {
            $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        }
        if ($request->input('email') === '') {
            $request->merge(['email' => null]);
        }
        if ($request->input('password') === '') {
            $request->merge(['password' => null]);
        }
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'username' => ['sometimes', 'required', 'string', 'max:60', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'sometimes|nullable|string|min:8',
            'current_password' => 'required_with:password|string',
        ]);
        $account = array_intersect_key($data, array_flip(['name', 'username', 'email']));
        if (! empty($data['password'])) {
            if (! Hash::check((string) $request->input('current_password'), $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Das aktuelle Passwort ist nicht korrekt.']);
            }
            $account['password'] = $data['password'];
            AuditLog::change('Eigenes Passwort geändert');
        }
        $user->update($account);
        $profile = array_intersect_key($account, array_flip(['name', 'email']));
        if ($profile !== [] && $user->referee) {
            $user->referee->update($profile);
        }

        return $this->done();
    }

    public function updateLicense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license' => ['required', Rule::in(Licenses::LEVELS)],
            'license_valid_until' => 'required|date',
        ]);
        $request->user()->ensureReferee()->update($data);

        return $this->done();
    }

    public function updateStandort(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address' => 'nullable|string|max:200',
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
        ]);
        $data['address'] = ($data['address'] ?? null) ?: null;
        if ($data['address'] === null) {
            $data['lat'] = null;
            $data['lon'] = null;
        }
        $request->user()->ensureReferee()->update($data);

        return $this->done();
    }
}
