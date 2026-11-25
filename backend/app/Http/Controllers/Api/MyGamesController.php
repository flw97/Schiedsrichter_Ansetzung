<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityResponse;
use App\Models\Game;
use App\Models\Referee;
use App\Models\Setting;
use App\Services\Push;
use App\Support\Licenses;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Aktionen der Schiedsrichter zu Spielen (Abfrage, eigene Ansetzungen) sowie Spielberichte. */
class MyGamesController extends Controller
{
    public function __construct(private Push $push) {}

    /** Rückmeldung in der Abfrage: zusagen, absagen oder zurückziehen (answer = null). */
    public function respond(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate(['answer' => 'nullable|in:yes,no', 'comment' => 'nullable|string|max:500']);
        $me = $this->me($request);
        abort_unless(self::abfrageOpen(), 422, 'Die Abfrage ist nicht geöffnet.');
        abort_if($game->status === 'confirmed' || Licenses::rank($me->license) < Licenses::requiredLevel($game->competition), 422, 'Für dieses Spiel ist keine Rückmeldung möglich.');
        $key = ['game_id' => $game->id, 'referee_id' => $me->id];
        $data['answer']
            ? AvailabilityResponse::updateOrCreate($key, ['answer' => $data['answer'], 'comment' => $data['comment'] ?? null])
            : AvailabilityResponse::where($key)->delete();

        return $this->done();
    }

    /** Ansetzung zurückgeben: Turniere mit mehr als der Mindestbesetzung bleiben bestehen, sonst ist das Spiel wieder offen. */
    public function withdraw(Request $request, Game $game): JsonResponse
    {
        $reason = $request->validate(['reason' => 'nullable|string|max:200'])['reason'] ?? '';
        $me = $this->assignedMe($request, $game);
        $remaining = $game->crew('confirmed')->reject(fn ($r) => $r->id === $me->id);
        $head = $game->crew('head')->first();
        $this->push->admins("{$me->name} hat die Ansetzung zurückgegeben — {$game->title}".($reason ? " („{$reason}“)" : ''));
        if ($remaining->count() >= $game->needed_count && ($game->type !== 'tournament' || ($head && $head->id !== $me->id))) {
            $game->referees()->detach($me->id);
            AvailabilityResponse::where(['game_id' => $game->id, 'referee_id' => $me->id])->delete();

            return $this->done();
        }
        $others = $game->assigned()->reject(fn ($r) => $r->id === $me->id);
        $game->reopen("Von {$me->name} zurückgegeben".($reason ? " („{$reason}“)" : ''));
        $others->each(fn (Referee $r) => $this->push->referee($r, "Ansetzung zurückgenommen ({$me->name} hat zurückgegeben) — {$game->title}"));

        return $this->done();
    }

    /** Neuer Termin passt: sobald alle Angesetzten bestätigt haben, entfällt der Hinweis. */
    public function reconfirm(Request $request, Game $game): JsonResponse
    {
        $me = $this->assignedMe($request, $game);
        $game->referees()->newPivotQuery()->where(['game_id' => $game->id, 'referee_id' => $me->id])->update(['reconfirmed' => true]);
        if ($game->load('referees')->assigned()->every(fn ($r) => $r->pivot->reconfirmed)) {
            $game->update(['schedule_change' => null]);
            $game->referees()->newPivotQuery()->where('game_id', $game->id)->update(['reconfirmed' => false]);
        }

        return $this->done();
    }

    /** Anmerkungen und Karten erfassen (angesetzte Schiedsrichter oder Admins). */
    public function report(Request $request, Game $game): JsonResponse
    {
        $user = $request->user();
        if (! Permissions::allows($user->role, 'ansetzen')) {
            $this->assignedMe($request, $game);
        }
        $data = $request->validate([
            'note' => 'nullable|string|max:1000',
            'cards' => 'array',
            'cards.*.color' => 'required|in:gelb,rot,schwarz',
            'cards.*.team' => 'required|string|max:100',
            'cards.*.person' => 'nullable|string|max:100',
            'cards.*.reason' => 'nullable|string|max:100',
            'cards.*.given_by' => 'nullable|string|max:100',
        ]);
        $has = trim($data['note'] ?? '') !== '' || ! empty($data['cards']);
        $game->update(['report' => $has ? ['note' => trim($data['note'] ?? ''), 'cards' => $data['cards'] ?? [], 'author' => $user->name, 'at' => today()->toDateString()] : null]);

        return $this->done();
    }

    public static function abfrageOpen(): bool
    {
        [$from, $to] = [Setting::get('abfrage_from'), Setting::get('abfrage_to')];
        $today = today()->toDateString();

        return ! $from || ! $to || ($from <= $today && $today <= $to);
    }

    private function assignedMe(Request $request, Game $game): Referee
    {
        $me = $this->me($request);
        abort_unless($game->status === 'confirmed' && $game->assigned()->contains('id', $me->id), 403, 'Du bist für dieses Spiel nicht angesetzt.');

        return $me;
    }
}
