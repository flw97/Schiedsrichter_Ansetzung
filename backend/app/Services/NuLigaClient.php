<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Simulierte Schnittstelle für Demo-Daten und Tests.
 * Termine liegen relativ zum heutigen Datum: weeks = 0 -> kommendes Wochenende.
 * Der laufende Abgleich nutzt {@see CrawlerNuLigaClient}, sobald NULIGA_TOKEN gesetzt ist.
 */
class NuLigaClient implements NuLigaFeed
{
    /** Lizenzdaten je NuLiga-Personen-ID; null = NuLiga liefert (noch) keine Lizenzdaten. */
    public function licenses(): array
    {
        return [
            'p1' => ['license' => 'International', 'valid_until' => '2027-06-30', 'club' => 'SG Talheim'],
            'p2' => ['license' => 'National', 'valid_until' => '2027-12-31', 'club' => 'BC Fils'],
            'p3' => ['license' => 'National', 'valid_until' => '2028-03-01', 'club' => 'TV Rottenburg'],
            'p4' => ['license' => 'BEC-Certified', 'valid_until' => '2028-01-15', 'club' => 'TSV Waldkirch'],
            'p5' => ['license' => null, 'valid_until' => null, 'club' => 'SC Neckartal'],
        ];
    }

    /** Spiele der Regionalliga Südost, 2. und 1. Bundesliga (Spielort = Heimmannschaft). */
    public function games(): array
    {
        $g = fn (string $id, string $liga, string $home, string $hs, string $away, string $as, int $weeks, bool $sunday, string $time, string $venue) => [
            'nuliga_id' => $id, 'title' => "{$liga} · {$home} vs. {$away}", 'type' => 'match', 'competition' => $liga,
            'home_team' => $home, 'home_state' => $hs, 'away_team' => $away, 'away_state' => $as,
            'date' => self::weekend($weeks, $sunday)->toDateString(), 'time' => $time, 'venue' => $venue, 'needed_count' => 2,
        ];
        [$rl, $b2, $b1] = ['Regionalliga Südost', '2. Bundesliga', '1. Bundesliga'];

        return [
            $g('n1', $b2, 'BC Fils', 'BW', 'TV Rottenburg', 'BW', 0, false, '16:00', 'Sporthalle Fils'),
            $g('n2', $b1, 'SG Talheim', 'BW', 'SC Elbtal', 'SN', 0, true, '14:00', 'Halle am Steinbruch'),
            $g('n3', $rl, 'SG Talheim', 'BW', 'BV Fils', 'BW', 0, true, '11:30', 'Halle am Steinbruch'),
            $g('n4', $rl, 'TV Lechfeld', 'BY', 'SG Talheim', 'BW', 1, false, '14:00', 'Halle Lechfeld'),
            $g('n5', $b2, 'SC Elbtal', 'SN', 'BC Fils', 'BW', 1, true, '13:00', 'Elbtalhalle'),
            $g('n6', $b1, 'BV Isartal', 'BY', 'SV Saalburg', 'TH', 1, true, '15:00', 'Isarhalle'),
            $g('n7', $rl, 'SV Saalburg', 'TH', 'FC Pleißental', 'SN', 2, false, '15:00', 'Saalehalle'),
            $g('s1', $rl, 'TSG Hohenstaufen', 'BW', 'BV Fils', 'BW', 1, false, '15:00', 'Hohenstaufenhalle'),
            $g('s2', $b2, 'SC Neckartal', 'BW', 'TV Rottenburg', 'BW', 1, true, '14:00', 'Neckarhalle Neckartenzlingen'),
            $g('s3', $rl, 'FC Reutlingen', 'BW', 'TSV Waldkirch', 'BW', 2, false, '15:00', 'Albhalle Reutlingen'),
            $g('s4', $b1, 'BC Nordstadt', 'BW', 'SG Talheim', 'BW', 2, true, '13:00', 'Halle Nordstadt'),
            $g('s5', $b2, 'TSV Waldkirch', 'BW', 'BC Fils', 'BW', 3, false, '16:00', 'Sporthalle Waldkirch'),
            $g('s6', $rl, 'SV Tübingen', 'BW', 'BC Nordstadt', 'BW', 3, false, '14:00', 'Neckarhalle Tübingen'),
            $g('s7', $b1, 'BC Fils', 'BW', 'TSG Hohenstaufen', 'BW', 3, true, '15:00', 'Sporthalle Fils'),
            $g('s8', $rl, 'SG Talheim', 'BW', 'TV Rottenburg', 'BW', 4, false, '15:00', 'Halle am Steinbruch'),
            $g('s9', $b2, 'SV Tübingen', 'BW', 'TSV Waldkirch', 'BW', 4, true, '14:00', 'Neckarhalle Tübingen'),
            $g('s10', $rl, 'TSG Hohenstaufen II', 'BW', 'SV Tübingen', 'BW', 5, false, '12:30', 'Hohenstaufenhalle'),
            $g('s11', $b2, 'TSG Hohenstaufen', 'BW', 'BC Nordstadt', 'BW', 5, false, '15:30', 'Hohenstaufenhalle'),
        ];
    }

    public static function weekend(int $weeks, bool $sunday = false): CarbonImmutable
    {
        $today = CarbonImmutable::today();
        $toSaturday = ((6 - $today->dayOfWeek + 7) % 7) ?: 7;

        return $today->addDays($toSaturday + $weeks * 7 + ($sunday ? 1 : 0));
    }
}
