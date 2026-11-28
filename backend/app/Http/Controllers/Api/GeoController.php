<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Adresssuche für den eigenen Standort. Nominatim erlaubt höchstens eine Anfrage pro Sekunde. */
class GeoController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->validate(['q' => 'required|string|min:3|max:200'])['q']);

        return response()->json($this->lookup('search', [
            'q' => $q,
            'format' => 'jsonv2',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => 'de',
        ]));
    }

    public function reverse(Request $request): JsonResponse
    {
        $at = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        return response()->json($this->lookup('reverse', [
            'lat' => $at['lat'],
            'lon' => $at['lon'],
            'format' => 'jsonv2',
            'addressdetails' => 1,
        ]));
    }

    /** @param array<string, mixed> $query @return array{address: string, lat: float, lon: float}|null */
    private function lookup(string $path, array $query): ?array
    {
        $key = 'geo:'.md5($path.'|'.json_encode($query));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $res = Http::timeout(4)
                ->withHeaders([
                    'User-Agent' => 'Ansetzung/1.0 (https://ansetzung.badminton-gomaringen.de)',
                    'Accept-Language' => 'de',
                ])
                ->get("https://nominatim.openstreetmap.org/{$path}", $query);
        } catch (\Throwable) {
            return null;
        }
        $body = $res->ok() ? $res->json() : null;
        $row = is_array($body) ? (isset($body['lat']) ? $body : ($body[0] ?? null)) : null;
        if (! is_array($row) || ! isset($row['lat'], $row['lon'])) {
            return null;
        }
        $hit = [
            'address' => $this->label($row),
            'lat' => (float) $row['lat'],
            'lon' => (float) $row['lon'],
        ];
        Cache::put($key, $hit, now()->addDay());

        return $hit;
    }

    /** @param array<string, mixed> $row */
    private function label(array $row): string
    {
        $parts = is_array($row['address'] ?? null) ? $row['address'] : [];
        $street = trim(($parts['road'] ?? $parts['pedestrian'] ?? '').' '.($parts['house_number'] ?? ''));
        $place = $parts['city'] ?? $parts['town'] ?? $parts['village'] ?? $parts['municipality'] ?? $parts['hamlet'] ?? '';
        $locality = trim(($parts['postcode'] ?? '').' '.$place);
        $line = trim($street.($street !== '' && $locality !== '' ? ', ' : '').$locality);

        return mb_substr($line !== '' ? $line : (string) ($row['display_name'] ?? ''), 0, 200);
    }
}
