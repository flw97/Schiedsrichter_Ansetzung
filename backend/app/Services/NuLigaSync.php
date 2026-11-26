<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Referee;
use App\Models\Setting;

/**
 * Abgleich mit NuLiga: Spiele werden bereitgestellt (noch nicht übernommen) oder als ausstehende
 * Terminänderung an bestehenden Spielen vermerkt; Lizenzen und Vereine werden je Schiedsrichter übernommen.
 */
class NuLigaSync
{
    public function __construct(private NuLigaFeed $client, private Push $push) {}

    public function syncGames(): void
    {
        foreach ($this->client->games() as $data) {
            $game = Game::firstWhere('nuliga_id', $data['nuliga_id']);
            if (! $game) {
                Game::create([...$data, 'imported' => false, 'nuliga_change' => 'neu']);
            } elseif (! $game->imported) {
                $game->update($data);
            } elseif ($game->date->toDateString() !== $data['date'] || $game->time !== $data['time'] || $game->venue !== $data['venue']) {
                $game->update([
                    'pending_date' => $data['date'], 'pending_time' => $data['time'], 'pending_venue' => $data['venue'],
                    'nuliga_change' => 'verschoben · bisher '.($game->date->toDateString() === $data['date'] ? ($game->time ?? 'ganztägig') : $game->dateLabel()),
                ]);
            }
        }
        Setting::put('nuliga_last_sync', now()->toIso8601String());
    }

    /** Spiel übernehmen bzw. Terminänderung anwenden; Angesetzte müssen den neuen Termin bestätigen. */
    public function import(Game $game, ?string $requestedBy = null): void
    {
        if (! $game->imported) {
            $game->update(['imported' => true, 'nuliga_change' => null, 'requested_by' => $requestedBy]);

            return;
        }
        if (! $game->pending_date) {
            return;
        }
        $old = $game->dateLabel();
        $new = Game::label($game->pending_date, $game->pending_time);
        $assigned = $game->status === 'confirmed' ? $game->assigned() : collect();
        $game->update([
            'date' => $game->pending_date, 'time' => $game->pending_time, 'venue' => $game->pending_venue ?? $game->venue,
            'pending_date' => null, 'pending_time' => null, 'pending_venue' => null, 'nuliga_change' => null,
            'schedule_change' => $assigned->isEmpty() ? null : ['old' => $old, 'new' => $new],
        ]);
        $game->referees()->newPivotQuery()->where('game_id', $game->id)->update(['reconfirmed' => false]);
        $assigned->each(fn (Referee $r) => $this->push->referee($r, "Termin geändert ({$old} → {$new}) — bitte neu bestätigen: {$game->title}"));
    }

    /** NuLiga-Lizenzdaten je Schiedsrichter-ID (null = keine Lizenzdaten in NuLiga). */
    public function licenses(): array
    {
        $data = $this->client->licenses();

        return Referee::all()->mapWithKeys(fn (Referee $r) => [
            $r->id => ($data[$r->nuliga_id]['license'] ?? null) ? ['license' => $data[$r->nuliga_id]['license'], 'valid_until' => $data[$r->nuliga_id]['valid_until']] : null,
        ])->all();
    }

    public function syncReferee(Referee $referee): void
    {
        $data = $this->client->licenses()[$referee->nuliga_id] ?? null;
        if (! $data) {
            return;
        }
        $changed = $data['license'] && ($data['license'] !== $referee->license || $data['valid_until'] !== $referee->license_valid_until?->toDateString());
        $referee->update([
            ...($data['license'] ? ['license' => $data['license'], 'license_valid_until' => $data['valid_until']] : []),
            'club' => $data['club'] ?? $referee->club,
            'license_synced_at' => today(),
        ]);
        // Push nur bei tatsächlicher Lizenzänderung; der Vereins-Abgleich löst keine Mitteilung aus
        if ($changed) {
            $this->push->referee($referee, "Lizenz geändert — {$data['license']}, gültig bis ".$referee->license_valid_until->format('d.m.Y'));
        }
    }
}
