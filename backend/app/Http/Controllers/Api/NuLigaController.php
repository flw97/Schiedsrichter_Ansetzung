<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\NuLigaSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** NuLiga-Import: regulär übernommen werden nur Heimspiele in BW, alles andere nur auf Anforderung. */
class NuLigaController extends Controller
{
    public function __construct(private NuLigaSync $sync) {}

    public function sync(): JsonResponse
    {
        $this->sync->syncGames();

        return $this->done();
    }

    public function import(Request $request, Game $game): JsonResponse
    {
        $standard = $game->home_state === 'BW';
        abort_unless($standard || $request->boolean('request'), 422, 'Spiele außerhalb des BW-Fokus müssen ausdrücklich angefordert werden.');
        $this->sync->import($game, $standard ? null : $request->user()->name);

        return $this->done();
    }

    public function importAll(): JsonResponse
    {
        Game::whereNotNull('nuliga_change')->where('home_state', 'BW')->get()->each(fn (Game $g) => $this->sync->import($g->load('referees')));

        return $this->done();
    }
}
