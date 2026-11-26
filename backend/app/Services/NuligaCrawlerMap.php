<?php

namespace App\Services;

/**
 * Übersetzt Gruppen des nuLiga-Crawlers in die Spielform von {@see NuLigaSync}.
 * Übernommen werden 1. Bundesliga, 2. Bundesliga und die Regionalliga Südost.
 */
final class NuligaCrawlerMap
{
    public const VENUE_UNKNOWN = 'noch keine Halle';

    /** @param array<string, mixed> $group */
    public static function competition(array $group): ?string
    {
        $level = $group['league']['level'] ?? null;
        $code = $group['championship']['code'] ?? null;
        if ($code === 'GrSO' && $level === 'regionalliga') {
            return 'Regionalliga Südost';
        }

        return match ($level) {
            'bundesliga' => '1. Bundesliga',
            'bundesliga-2' => '2. Bundesliga',
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  array<string, true>  $bwClubs  Vereinsnamen aus dem Crawler; das sind die Vereine der vier BW-Bezirke
     * @return list<array<string, mixed>>
     */
    public static function games(array $groups, array $bwClubs): array
    {
        $games = [];
        foreach ($groups as $group) {
            $competition = self::competition($group);
            if ($competition === null) {
                continue;
            }
            $label = is_string($group['league']['label'] ?? null) ? $group['league']['label'] : $competition;
            $titleLeague = $competition === '2. Bundesliga' ? $label : $competition;
            foreach ($group['fixtures'] ?? [] as $fixture) {
                if (! is_array($fixture)) {
                    continue;
                }
                $game = self::game($fixture, $competition, $titleLeague, $bwClubs);
                if ($game !== null) {
                    $games[$game['nuliga_id']] = $game;
                }
            }
        }

        return array_values($games);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, true>  $bwClubs
     * @return array<string, mixed>|null
     */
    private static function game(array $fixture, string $competition, string $titleLeague, array $bwClubs): ?array
    {
        $id = $fixture['id'] ?? null;
        $date = $fixture['date'] ?? null;
        $home = $fixture['home']['name'] ?? null;
        $away = $fixture['away']['name'] ?? null;
        if (! is_string($id) || $id === '' || ! is_string($home) || $home === '' || ! is_string($away) || $away === '') {
            return null;
        }
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $time = $fixture['time'] ?? null;
        if (! is_string($time) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = null;
        }
        $venue = $fixture['venue']['name'] ?? null;

        return [
            'nuliga_id' => $id,
            'title' => "{$titleLeague} · {$home} vs. {$away}",
            'type' => 'match',
            'competition' => $competition,
            'home_team' => $home,
            'home_state' => self::state(is_array($fixture['home'] ?? null) ? $fixture['home'] : [], $bwClubs),
            'away_team' => $away,
            'away_state' => self::state(is_array($fixture['away'] ?? null) ? $fixture['away'] : [], $bwClubs),
            'date' => $date,
            'time' => $time,
            'venue' => is_string($venue) && $venue !== '' ? $venue : self::VENUE_UNKNOWN,
            'needed_count' => 2,
        ];
    }

    /** @param array<string, mixed> $team @param array<string, true> $bwClubs */
    private static function state(array $team, array $bwClubs): ?string
    {
        foreach (['club', 'name'] as $key) {
            $value = $team[$key] ?? null;
            if (is_string($value) && isset($bwClubs[$value])) {
                return 'BW';
            }
        }

        return null;
    }
}
