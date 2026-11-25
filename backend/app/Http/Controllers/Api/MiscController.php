<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Observation;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kleinere Verwaltungsaktionen: Abfragezeitraum und Beobachtungsbögen. */
class MiscController extends Controller
{
    public function setAbfrage(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        Setting::put('abfrage_from', $data['from'] ?? null);
        Setting::put('abfrage_to', $data['to'] ?? null);

        return $this->done();
    }

    public function addObservation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'referee_id' => 'required|exists:referees,id',
            'date' => 'required|date',
            'game' => 'required|string|max:200',
            'scores' => 'required|array',
            'scores.*' => 'integer|min:1|max:5',
            'strengths' => 'nullable|string|max:1000',
            'development' => 'nullable|string|max:1000',
            'recommendation' => 'required|in:Aufstufung empfohlen,Weiter beobachten,Noch nicht',
        ]);
        Observation::create([...$data, 'observer' => $request->user()->name]);

        return $this->done();
    }
}
