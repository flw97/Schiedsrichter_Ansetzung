<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Setting;
use App\Services\CrawlerNuLigaClient;
use App\Services\NuLigaSync;
use App\Services\Push;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NuligaCrawlerSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $kickoff = '14:00';

    public function test_sync_reads_the_three_leagues_and_marks_bw_home_clubs(): void
    {
        Http::fake(fn (Request $request) => Http::response($this->payload($request->url())));
        $sync = new NuLigaSync(new CrawlerNuLigaClient('https://nuliga.test/api', 'operator-token'), app(Push::class));

        $sync->syncGames();

        $home = Game::firstWhere('nuliga_id', '2026-27:40055:tus-metzingen:osc-muenchen');
        $this->assertNotNull($home);
        $this->assertSame('Regionalliga Südost', $home->competition);
        $this->assertSame('Regionalliga Südost · TuS Metzingen vs. OSC München', $home->title);
        $this->assertSame('BW', $home->home_state);
        $this->assertNull($home->away_state);
        $this->assertSame('Schönbeinhalle', $home->venue);
        $this->assertSame('14:00', $home->time);
        $this->assertFalse($home->imported);
        $this->assertSame('neu', $home->nuliga_change);
        $this->assertSame(2, $home->needed_count);

        $south = Game::firstWhere('nuliga_id', '2026-27:39996:sg-schorndorf:bc-offenburg');
        $this->assertSame('2. Bundesliga', $south->competition);
        $this->assertSame('2. Bundesliga Süd · SG Schorndorf vs. BC Offenburg', $south->title);
        $this->assertSame('BW', $south->home_state);
        $this->assertSame('BW', $south->away_state);

        $bl = Game::firstWhere('nuliga_id', '2026-27:39995:tv-refrath:blau-weiss');
        $this->assertSame('1. Bundesliga', $bl->competition);
        $this->assertNull($bl->home_state);
        $this->assertSame('noch keine Halle', $bl->venue);

        $this->assertNull(Game::firstWhere('nuliga_id', '2026-27:40055:open'));
        $this->assertSame(0, Game::where('competition', 'Oberliga')->count());
        $this->assertNotNull(Setting::get('nuliga_last_sync'));
        $this->assertSame([], (new CrawlerNuLigaClient('https://nuliga.test/api', 'operator-token'))->licenses());

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer operator-token'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'group=99999'));

        $home->update(['imported' => true, 'nuliga_change' => null]);
        $this->kickoff = '16:00';
        $sync->syncGames();
        $home->refresh();
        $this->assertSame('14:00', $home->time);
        $this->assertSame('16:00', $home->pending_time);
        $this->assertStringStartsWith('verschoben', (string) $home->nuliga_change);
    }

    /** @return array<string, mixed> */
    private function payload(string $url): array
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (str_contains($url, '/groups')) {
            $championship = $query['championship'] ?? '';

            return ['groups' => $championship === 'DBV' ? [
                $this->summary('39995', 'DBV', 'bundesliga', '1. Bundesliga'),
                $this->summary('39996', 'DBV', 'bundesliga-2', '2. Bundesliga Süd'),
            ] : [
                $this->summary('40055', 'GrSO', 'regionalliga', 'Regionalliga SüdOst'),
                $this->summary('99999', 'GrSO', 'oberliga', 'Oberliga Süd'),
            ]];
        }
        if (str_contains($url, '/clubs')) {
            return ['clubs' => [
                ['name' => 'TuS Metzingen'],
                ['name' => 'SG Schorndorf'],
                ['name' => 'BC Offenburg'],
            ]];
        }
        $group = $query['group'] ?? '';
        if ($group === '40055') {
            return $this->group('GrSO', 'regionalliga', 'Regionalliga SüdOst', [
                $this->fixture('2026-27:40055:tus-metzingen:osc-muenchen', '2026-09-26', $this->kickoff, 'TuS Metzingen', 'OSC München', 'Schönbeinhalle'),
                $this->fixture('2026-27:40055:open', null, null, 'TuS Metzingen', 'SV Lohhof', 'Jahnhalle'),
            ]);
        }
        if ($group === '39996') {
            return $this->group('DBV', 'bundesliga-2', '2. Bundesliga Süd', [
                $this->fixture('2026-27:39996:sg-schorndorf:bc-offenburg', '2026-10-03', '15:00', 'SG Schorndorf', 'BC Offenburg', 'Schorndorfhalle'),
            ]);
        }

        return $this->group('DBV', 'bundesliga', '1. Bundesliga', [
            $this->fixture('2026-27:39995:tv-refrath:blau-weiss', '2026-09-13', '14:00', 'TV Refrath', 'Blau-Weiß Wittorf NMS', null),
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(string $id, string $code, string $level, string $label): array
    {
        return [
            'groupId' => $id,
            'championship' => ['code' => $code, 'name' => $code, 'season' => '2026/27'],
            'league' => ['level' => $level, 'levelName' => $label, 'name' => '', 'label' => $label],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fixtures
     * @return array<string, mixed>
     */
    private function group(string $code, string $level, string $label, array $fixtures): array
    {
        return [...$this->summary('0', $code, $level, $label), 'fixtures' => $fixtures];
    }

    /** @return array<string, mixed> */
    private function fixture(string $id, ?string $date, ?string $time, string $home, string $away, ?string $venue): array
    {
        return [
            'id' => $id,
            'date' => $date,
            'time' => $time,
            'venue' => $venue === null ? null : ['name' => $venue],
            'home' => ['name' => $home, 'club' => $home, 'squad' => 1],
            'away' => ['name' => $away, 'club' => $away, 'squad' => 1],
        ];
    }
}
