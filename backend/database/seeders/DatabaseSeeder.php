<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\AvailabilityResponse;
use App\Models\Game;
use App\Models\Observation;
use App\Models\Referee;
use App\Models\Setting;
use App\Models\User;
use App\Services\NuLigaClient;
use App\Services\NuLigaSync;
use Illuminate\Database\Seeder;

/**
 * Demo-Daten aus dem Prototyp. Alle Demo-Konten haben das Passwort „demo1234“.
 */
class DatabaseSeeder extends Seeder
{
    public const PASSWORD = 'demo1234';

    public function run(NuLigaSync $nuliga): void
    {
        $w = fn (int $weeks, bool $sunday = false) => NuLigaClient::weekend($weeks, $sunday)->toDateString();
        $refs = collect([
            ['p1', 'Petra Holzmann', 'SG Talheim', 'International', '2027-06-30', 'petra.holzmann@web.de', '0151 2345678', 'Hauptstraße 4, 73728 Esslingen', 3, 0.8, true, 14, 'So, 13. Sep', '+2 ggü. Vormonat', false, false],
            ['p2', 'Jonas Krieger', 'BC Fils', 'National', '2026-12-31', 'j.krieger@gmx.de', '0162 9871234', 'Bahnhofstraße 11, 72654 Neckartenzlingen', 6, 0.35, true, 21, 'Sa, 12. Sep', '+5 ggü. Vormonat', false, true],
            ['p3', 'Elif Yildiz', 'TV Rottenburg', 'Grundlizenz', '2027-03-01', 'elif.yildiz@outlook.de', '0176 5544332', 'Fliederweg 2, 73230 Kirchheim', 1, 0.95, true, 6, 'So, 30. Aug', '± 0 ggü. Vormonat', true, false],
            ['p4', 'Malte Sondheim', 'TSV Waldkirch', 'BEC-Certified', '2028-01-15', 'malte.sondheim@t-online.de', '0171 3321456', 'Ringstraße 19, 72762 Reutlingen', 4, 0.6, false, 16, 'Sa, 5. Sep', '−1 ggü. Vormonat', false, false],
            ['p5', 'Katja Rehberg', 'SC Neckartal', 'National', '2026-11-30', 'katja.rehberg@web.de', '0160 7788990', 'Mühlweg 7, 72654 Neckartenzlingen', 2, 0.85, true, 9, 'So, 13. Sep', '+1 ggü. Vormonat', false, true],
        ])->mapWithKeys(fn ($r) => [$r[1] => Referee::create(array_combine(
            ['nuliga_id', 'name', 'club', 'license', 'license_valid_until', 'email', 'phone', 'address', 'monthly_load', 'fairness', 'available', 'season_count', 'last_game', 'trend', 'persp_national', 'persp_international'], $r,
        ) + ['license_synced_at' => $r[0] === 'p3' ? null : '2026-08-02'])]);
        $refs['Petra Holzmann']->proofs()->create(['date' => '2026-01-12', 'text' => 'Fortbildung Regelwerk, Landesverband']);
        $refs['Petra Holzmann']->blackouts()->create(['from' => $w(4), 'to' => $w(4, true), 'reason' => 'Fortbildung']);
        $refs['Katja Rehberg']->blackouts()->create(['from' => $w(7), 'to' => $w(8, true), 'reason' => 'Urlaub']);

        $user = fn (string $name, string $username, string $role, ?Referee $ref = null) => User::create([
            'name' => $name, 'username' => $username, 'email' => $ref?->email ?? "{$username}@verband-beispiel.de",
            'password' => self::PASSWORD, 'role' => $role, 'referee_id' => $ref?->id,
        ]);
        $user('Markus Hartmann', 'm.hartmann', 'master');
        $user('Sabine Weber', 's.weber', 'admin');
        $user('Thomas Lang', 't.lang', 'lesend');
        $refs->each(fn (Referee $r) => $user($r->name, mb_strtolower(mb_substr($r->name, 0, 1).'.'.explode(' ', $r->name)[1]), 'schiri', $r));

        $rl = 'Regionalliga Südost';
        $game = fn (array $a) => Game::create([...$a, 'type' => $a['type'] ?? 'match', 'competition' => $a['competition'] ?? $rl, 'needed_count' => 2]);
        $game(['title' => "{$rl} · TSV Waldkirch vs. BC Nordstadt", 'date' => $w(-2), 'time' => '15:00', 'venue' => 'Sporthalle Waldkirch', 'home_team' => 'TSV Waldkirch', 'away_team' => 'BC Nordstadt']);
        $game(['title' => 'Landesmeisterschaft U19', 'type' => 'tournament', 'competition' => '1. Bundesliga', 'date' => $w(1, true), 'venue' => 'Kreissporthalle Fils', 'status' => 'proposed']);
        $game(['nuliga_id' => 'n3', 'title' => "{$rl} · SG Talheim vs. BV Fils", 'date' => $w(0, true), 'time' => '11:00', 'venue' => 'Halle am Steinbruch', 'home_team' => 'SG Talheim', 'home_state' => 'BW', 'away_team' => 'BV Fils', 'away_state' => 'BW'])
            ->confirm(collect([$refs['Katja Rehberg'], $refs['Jonas Krieger']]));
        $game(['title' => "{$rl} · BC Nordstadt vs. TSV Waldkirch", 'date' => $w(-1), 'time' => '15:00', 'venue' => 'Halle Nordstadt', 'home_team' => 'BC Nordstadt', 'away_team' => 'TSV Waldkirch', 'report' => [
            'note' => 'Verzögerter Beginn wegen Hallenboden, sonst ruhiger Spielverlauf.', 'author' => 'Jonas Krieger', 'at' => $w(-1),
            'cards' => [
                ['color' => 'gelb', 'team' => 'TSV Waldkirch', 'person' => '', 'reason' => 'Zeitspiel', 'given_by' => 'Jonas Krieger'],
                ['color' => 'gelb', 'team' => 'BC Nordstadt', 'person' => '', 'reason' => 'Unsportliches Verhalten', 'given_by' => 'Katja Rehberg'],
                ['color' => 'rot', 'team' => 'TSV Waldkirch', 'person' => '', 'reason' => 'Wiederholte Verwarnung', 'given_by' => 'Jonas Krieger'],
            ],
        ]])->confirm(collect([$refs['Katja Rehberg'], $refs['Jonas Krieger']]));

        $nuliga->syncGames();

        // Rückmeldungen der Saisonabfrage: NuLiga-ID bzw. Titel => [Name => [Antwort, Kommentar]]
        $responses = [
            'n1' => ['Petra Holzmann' => ['yes'], 'Katja Rehberg' => ['yes', 'Gern, Anreise ab 12 Uhr möglich']],
            'n2' => ['Jonas Krieger' => ['yes'], 'Petra Holzmann' => ['yes']],
            'Landesmeisterschaft U19' => ['Jonas Krieger' => ['yes'], 'Elif Yildiz' => ['yes', 'Als Schiedsrichterin im Turnier gern'], 'Petra Holzmann' => ['yes', 'Gern auch als Referee']],
            's1' => ['Jonas Krieger' => ['no', 'Nur sonntags verfügbar'], 'Elif Yildiz' => ['yes'], 'Petra Holzmann' => ['yes']],
            's2' => ['Jonas Krieger' => ['yes'], 'Petra Holzmann' => ['yes']],
            's3' => ['Elif Yildiz' => ['yes', 'Nur Regionalliga'], 'Jonas Krieger' => ['yes']],
            's4' => ['Jonas Krieger' => ['yes'], 'Katja Rehberg' => ['yes']],
            's6' => ['Petra Holzmann' => ['no', 'Familienfeier'], 'Elif Yildiz' => ['yes'], 'Jonas Krieger' => ['yes']],
            's8' => ['Jonas Krieger' => ['yes']],
            's10' => ['Petra Holzmann' => ['yes', 'Kann beide Spiele hintereinander übernehmen']],
            's11' => ['Petra Holzmann' => ['yes', 'Kann beide Spiele hintereinander übernehmen'], 'Jonas Krieger' => ['yes']],
        ];
        foreach ($responses as $key => $answers) {
            $gameId = Game::where('nuliga_id', $key)->orWhere('title', $key)->value('id');
            foreach ($answers as $name => $a) {
                AvailabilityResponse::create(['game_id' => $gameId, 'referee_id' => $refs[$name]->id, 'answer' => $a[0], 'comment' => $a[1] ?? null]);
            }
        }

        $scores = fn (int ...$s) => array_combine(['regelkenntnis', 'auftreten', 'spielleitung', 'teamarbeit'], $s);
        Observation::create(['referee_id' => $refs['Jonas Krieger']->id, 'date' => '2026-09-13', 'game' => "{$rl} · SG Talheim vs. BV Fils", 'observer' => 'Sabine Weber', 'scores' => $scores(4, 4, 3, 4), 'strengths' => 'Sichere Regelanwendung, ruhige Kommunikation mit den Spielern.', 'development' => 'Bei Zeitspiel früher und konsequenter eingreifen.', 'recommendation' => 'Weiter beobachten']);
        Observation::create(['referee_id' => $refs['Elif Yildiz']->id, 'date' => '2026-09-06', 'game' => "{$rl} · TSV Waldkirch vs. BC Nordstadt", 'observer' => 'Sabine Weber', 'scores' => $scores(3, 4, 3, 5), 'strengths' => 'Sehr gute Zusammenarbeit mit dem Leiter, sympathisches Auftreten.', 'development' => 'Regelsicherheit bei Aufschlagfehlern vertiefen.', 'recommendation' => 'Weiter beobachten']);

        Accommodation::create(['name' => 'Hotel am Fluss', 'street' => 'Neckarstraße 12', 'zip' => '73728', 'city' => 'Esslingen', 'venue' => 'Halle am Steinbruch', 'added_by' => 'Sabine Weber'])
            ->reviews()->create(['user_id' => User::firstWhere('username', 'k.rehberg')->id, 'author' => 'Katja Rehberg', 'stars' => 4, 'text' => 'Ruhig, gutes Frühstück, 10 Minuten zur Halle.']);

        Setting::put('abfrage_from', today()->subDays(7)->toDateString());
        Setting::put('abfrage_to', today()->addDays(14)->toDateString());
    }
}
