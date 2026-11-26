<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Spielpläne von https://nuliga.badminton-gomaringen.de. Der Crawler hat keine Schiedsrichter-Lizenzen.
 */
final class CrawlerNuLigaClient implements NuLigaFeed
{
    public function __construct(private string $baseUrl, private string $token) {}

    public function licenses(): array
    {
        return [];
    }

    public function games(): array
    {
        $summaries = [...$this->groups('DBV'), ...$this->groups('GrSO')];
        $bw = [];
        foreach ($this->get('clubs')['clubs'] ?? [] as $club) {
            $name = is_array($club) ? ($club['name'] ?? null) : null;
            if (is_string($name) && $name !== '') {
                $bw[$name] = true;
            }
        }
        $groups = [];
        foreach ($summaries as $summary) {
            if (! is_array($summary) || NuligaCrawlerMap::competition($summary) === null) {
                continue;
            }
            $code = $summary['championship']['code'] ?? null;
            $season = $summary['championship']['season'] ?? null;
            $groupId = $summary['groupId'] ?? null;
            if (! is_string($code) || ! is_string($season) || $groupId === null || $groupId === '') {
                continue;
            }
            $groups[] = $this->get('group', [
                'championship' => $code,
                'season' => $season,
                'group' => $groupId,
            ]);
        }

        return NuligaCrawlerMap::games($groups, $bw);
    }

    /** @return list<mixed> */
    private function groups(string $championship): array
    {
        $groups = $this->get('groups', ['championship' => $championship])['groups'] ?? [];

        return is_array($groups) ? $groups : [];
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    private function get(string $path, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
        $json = Http::withToken($this->token)->acceptJson()->timeout(20)->get($url, $query)->throw()->json();
        if (! is_array($json)) {
            throw new RuntimeException('nuLiga-Crawler lieferte kein JSON.');
        }

        return $json;
    }
}
