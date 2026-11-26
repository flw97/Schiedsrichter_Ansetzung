<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Referee;
use App\Services\NuLigaSync;
use App\Services\Push;
use App\Support\Licenses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Schiedsrichter-Stammdaten, Lizenzen, Perspektiv-Teams und Leistungsnachweise. */
class RefereeController extends Controller
{
    public function __construct(private Push $push) {}

    /** Unmaskierte Kontaktdaten für die Schiedsrichter-Liste; das Anzeigen wird protokolliert. */
    public function contacts(): JsonResponse
    {
        AuditLog::access('Kontaktdaten in der Schiedsrichter-Liste angezeigt');

        return response()->json(Referee::get(['id', 'email', 'phone']));
    }

    /** Eigene Stammdaten, Verfügbarkeit und Sperrzeiten (Name, Lizenz und Verein kommen aus NuLiga). */
    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'club' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:200',
            'available' => 'boolean',
            'blackouts' => 'array',
            'blackouts.*.from' => 'required|date',
            'blackouts.*.to' => 'required|date|after_or_equal:blackouts.*.from',
            'blackouts.*.reason' => 'nullable|string|max:100',
        ]);
        $me = $this->me($request);
        $me->update(collect($data)->except('blackouts')->all());
        $account = array_intersect_key($data, array_flip(['name', 'email']));
        if ($account !== []) {
            $request->user()->update($account);
        }
        if (array_key_exists('blackouts', $data)) {
            $me->blackouts()->delete();
            $me->blackouts()->createMany($data['blackouts']);
        }

        return $this->done();
    }

    public function updateLicense(Request $request, Referee $referee): JsonResponse
    {
        $data = $request->validate(['license' => ['required', Rule::in(Licenses::LEVELS)], 'license_valid_until' => 'required|date']);
        $changed = $data['license'] !== $referee->license || $data['license_valid_until'] !== $referee->license_valid_until?->toDateString();
        $referee->update($data);
        if ($changed) {
            $this->push->referee($referee, "Lizenz geändert — {$referee->license}, gültig bis {$referee->license_valid_until->format('d.m.Y')}");
        }

        return $this->done();
    }

    public function sync(NuLigaSync $sync, ?Referee $referee = null): JsonResponse
    {
        ($referee ? collect([$referee]) : Referee::all())->each(fn (Referee $r) => $sync->syncReferee($r));

        return $this->done();
    }

    public function updateTeams(Request $request, Referee $referee): JsonResponse
    {
        $referee->update($request->validate(['persp_national' => 'boolean', 'persp_international' => 'boolean']));

        return $this->done();
    }

    /** Leistungsnachweis eintragen und Lizenz verlängern. */
    public function addProof(Request $request, Referee $referee): JsonResponse
    {
        $data = $request->validate(['text' => 'required|string|max:255', 'license_valid_until' => 'required|date']);
        $referee->proofs()->create(['date' => today(), 'text' => $data['text']]);
        $referee->update(['license_valid_until' => $data['license_valid_until']]);
        $this->push->referee($referee, "Lizenz verlängert bis {$referee->license_valid_until->format('d.m.Y')} (Leistungsnachweis eingetragen)");

        return $this->done();
    }
}
