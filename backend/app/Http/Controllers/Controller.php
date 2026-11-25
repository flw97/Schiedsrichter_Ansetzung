<?php

namespace App\Http\Controllers;

use App\Models\Referee;
use App\Services\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Antwort auf eine Aktion: enthält die dabei verschickten Push-Mitteilungen. */
    protected function done(array $extra = []): JsonResponse
    {
        return response()->json(['pushes' => app(Push::class)->sent(), ...$extra]);
    }

    /** Der zum angemeldeten Konto gehörende Schiedsrichter. */
    protected function me(Request $request): Referee
    {
        return $request->user()->referee ?? abort(403, 'Kein Schiedsrichter-Profil mit diesem Konto verknüpft.');
    }
}
