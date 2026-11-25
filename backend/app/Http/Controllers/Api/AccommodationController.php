<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Unterkünfte bei den Spielorten mit Bewertungen der Schiedsrichter. */
class AccommodationController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:80',
        'street' => 'nullable|string|max:80',
        'zip' => 'nullable|string|max:10',
        'city' => 'required|string|max:60',
        'venue' => 'required|string|max:150',
        'lat' => 'nullable|numeric|between:-90,90',
        'lon' => 'nullable|numeric|between:-180,180',
    ];

    public function store(Request $request): JsonResponse
    {
        $acc = Accommodation::create([...$request->validate(self::RULES), 'added_by' => $request->user()->name]);

        return $this->done(['id' => $acc->id]);
    }

    public function update(Request $request, Accommodation $accommodation): JsonResponse
    {
        $accommodation->update($request->validate(self::RULES));

        return $this->done();
    }

    public function destroy(Accommodation $accommodation): JsonResponse
    {
        $accommodation->delete();

        return $this->done();
    }

    /** Je Person eine Bewertung; erneutes Bewerten ersetzt die eigene. */
    public function review(Request $request, Accommodation $accommodation): JsonResponse
    {
        $data = $request->validate(['stars' => 'required|integer|min:1|max:5', 'text' => 'nullable|string|max:300']);
        $user = $request->user();
        $accommodation->reviews()->updateOrCreate(['user_id' => $user->id], [...$data, 'author' => $user->name]);

        return $this->done();
    }
}
