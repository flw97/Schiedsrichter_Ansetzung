<?php

namespace App\Support;

use App\Models\Referee;
use Illuminate\Support\Collection;

/**
 * Lizenzmodell und Wettbewerbe (Spiegel von frontend/src/domain/rules.ts).
 */
final class Licenses
{
    public const LEVELS = ['Grundlizenz', 'National', 'International', 'BEC-Certified', 'BEC-Accredited', 'BWF-Certified', 'BWF-Accredited'];

    public const COMPETITIONS = ['Regionalliga Südost', '2. Bundesliga', '1. Bundesliga', 'BEC-Veranstaltung', 'BWF-Veranstaltung'];

    public const LEAGUES = ['Regionalliga Südost', '2. Bundesliga', '1. Bundesliga'];

    public static function rank(?string $level): int
    {
        $i = array_search($level, self::LEVELS, true);

        return $i === false ? -1 : $i;
    }

    public static function requiredLevel(string $competition): int
    {
        return match ($competition) {
            '2. Bundesliga', '1. Bundesliga' => 1,
            'BEC-Veranstaltung', 'BWF-Veranstaltung' => 2,
            default => 0,
        };
    }

    public static function isLeague(string $type, string $competition): bool
    {
        return $type === 'match' && in_array($competition, self::LEAGUES, true);
    }

    /** Ranghöher = höhere Lizenzstufe, bei Gleichstand mehr Einsätze in der Saison. */
    public static function leader(Collection $referees): ?Referee
    {
        return $referees->sort(fn (Referee $a, Referee $b) => (self::rank($b->license) <=> self::rank($a->license)) ?: ($b->season_count <=> $a->season_count))->first();
    }
}
