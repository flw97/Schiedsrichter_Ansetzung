<?php

namespace App\Services;

/**
 * Lesende Spiel- und Lizenzdaten. Der Demo-Client und der nuLiga-Crawler liefern dieselbe Form.
 */
interface NuLigaFeed
{
    /**
     * Lizenzdaten je NuLiga-Personen-ID. Ein leeres Array heißt: diese Quelle hat keine Lizenzen.
     *
     * @return array<string, array{license: ?string, valid_until: ?string, club?: ?string}>
     */
    public function licenses(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function games(): array;
}
