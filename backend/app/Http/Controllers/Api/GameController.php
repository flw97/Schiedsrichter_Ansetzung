<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Referee;
use App\Services\Push;
use App\Support\Licenses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** Ansetzungen durch Admins. */
class GameController extends Controller
{
    public function __construct(private Push $push) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:match,tournament',
            'title' => 'required|string|max:150',
            'date' => 'required|date',
            'time' => 'required_if:type,match|nullable|date_format:H:i',
            'venue' => 'required|string|max:150',
            'competition' => ['required', Rule::in(Licenses::COMPETITIONS)],
            'needed_count' => 'required|integer|min:1|max:20',
        ]);
        if ($data['type'] === 'tournament') {
            $data['time'] = null;
        }
        // Ligaspiele: immer 2 Schiedsrichter, der ranghöhere wird verantwortlicher Leiter
        if (Licenses::isLeague($data['type'], $data['competition'])) {
            $data['needed_count'] = 2;
        }

        return $this->done(['id' => Game::create($data)->id]);
    }

    /** Teilweise Auswahl speichern (ohne Benachrichtigung): Spiel bleibt offen. */
    public function selection(Request $request, Game $game): JsonResponse
    {
        [$refs, $head] = $this->crew($request, $game);
        $game->plan($refs, $head, 'open');

        return $this->done();
    }

    public function confirm(Request $request, Game $game): JsonResponse
    {
        [$refs, $head] = $this->crew($request, $game);
        $complete = $game->type === 'tournament' ? $refs->count() >= $game->needed_count && $head : $refs->count() === $game->needed_count;
        abort_unless($complete, 422, 'Die Besetzung ist noch nicht vollständig.');
        $game->confirm($refs, $head);
        $refs->each(fn (Referee $r) => $this->push->referee($r, "Neue Ansetzung bestätigt — {$game->title}", $game->leader_id === $r->id ? ' als verantwortlicher Leiter' : ''));
        if ($head) {
            $this->push->referee($head, "Neue Ansetzung bestätigt — {$game->title}", ' als Referee');
        }

        return $this->done();
    }

    public function undo(Game $game): JsonResponse
    {
        $assigned = $game->assigned();
        $game->reopen('Ansetzung vom Admin zurückgenommen');
        $assigned->each(fn (Referee $r) => $this->push->referee($r, "Ansetzung zurückgenommen — {$game->title}"));

        return $this->done();
    }

    /** Nachträgliche Korrektur eines archivierten Spiels: Termin, Halle, Besetzung. */
    public function update(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate(['date' => 'required|date', 'time' => 'nullable|date_format:H:i', 'venue' => 'required|string|max:150']);
        $game->update($data);
        [$refs, $head] = $this->crew($request, $game, false);
        if ($refs->isEmpty() && ! $head) {
            $game->plan(collect(), null, 'open');
        } else {
            $game->confirm($refs, $head);
        }

        return $this->done();
    }

    /** Ergebnis der Saisonplanung übernehmen: festlegen (bestätigt) oder als Vorschlag ablegen. */
    public function applySeasonPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:festlegen,vorschlag',
            'rows' => 'required|array',
            'rows.*.game_id' => 'required|exists:games,id',
            'rows.*.referee_ids' => 'array',
            'rows.*.referee_ids.*' => 'exists:referees,id',
        ]);
        $perRef = [];
        foreach ($data['rows'] as $row) {
            $game = Game::with('referees')->find($row['game_id']);
            if ($game->status === 'confirmed') {
                continue;
            }
            $refs = Referee::findMany($row['referee_ids'] ?? []);
            if ($refs->count() < 2) {
                $game->plan(collect(), null, 'open');
            } elseif ($data['mode'] === 'festlegen') {
                $game->confirm($refs);
                $refs->each(function (Referee $r) use (&$perRef, $game) {
                    $perRef[$r->id][] = $game;
                });
            } else {
                $game->plan($refs, null, 'proposed');
            }
        }
        // Eine Push-Mitteilung je Schiedsrichter (nicht je Spiel)
        foreach ($perRef as $id => $games) {
            $n = count($games);
            $this->push->referee(Referee::find($id), "{$n} neue Ansetzung".($n === 1 ? '' : 'en').' — erstes Spiel: '.$games[0]->title.' ('.$games[0]->date->format('d.m.').')');
        }

        return $this->done();
    }

    /** @return array{0: Collection<int, Referee>, 1: ?Referee} */
    private function crew(Request $request, Game $game, bool $checkLicense = true): array
    {
        $data = $request->validate([
            'referee_ids' => 'array',
            'referee_ids.*' => 'distinct|exists:referees,id',
            'head_referee_id' => 'nullable|exists:referees,id',
        ]);
        $refs = Referee::findMany($data['referee_ids'] ?? []);
        $head = isset($data['head_referee_id']) && $game->type === 'tournament' ? Referee::find($data['head_referee_id']) : null;
        $need = Licenses::requiredLevel($game->competition);
        abort_if($checkLicense && $refs->concat([$head])->filter()->contains(fn (Referee $r) => Licenses::rank($r->license) < $need), 422, 'Mindestens eine Person hat nicht die erforderliche Lizenz.');

        return [$refs->reject(fn ($r) => $head && $r->id === $head->id)->values(), $head];
    }
}
