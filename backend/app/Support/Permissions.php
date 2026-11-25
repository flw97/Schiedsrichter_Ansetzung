<?php

namespace App\Support;

/**
 * Rollen und Berechtigungen. Einzige Quelle für Backend-Gates und die Matrix in der Rollenverwaltung.
 */
final class Permissions
{
    public const ROLES = ['master' => 'Master-Admin', 'admin' => 'Admin', 'schiri' => 'Schiedsrichter', 'lesend' => 'Lesend'];

    public const MATRIX = [
        'einsehen' => ['Abfragen (Ansetzung), Statistik, Karten & Notizen, Archiv und Standorte ansehen', ['master', 'admin', 'lesend']],
        'ansetzen' => ['Ansetzen und zurücknehmen, NuLiga-Import, Veranstaltungen anlegen, Archiv bearbeiten', ['master', 'admin']],
        'saisonplanung' => ['Saisonplanung: Abfragezeitraum, Rückmeldungen und Vorschlag bearbeiten, Ansetzungen festlegen', ['master', 'admin']],
        'personen' => ['Schiedsrichter-Liste, Lizenzen, Perspektiv-Teams, Leistungsnachweise und Beobachtungen', ['master', 'admin']],
        'regel_lesen' => ['Regelwerke ansehen', ['master', 'admin', 'schiri', 'lesend']],
        'regel_verwalten' => ['Regelwerke hochladen, aktualisieren und löschen', ['master', 'admin']],
        'unterkunft_lesen' => ['Unterkünfte ansehen', ['master', 'admin', 'schiri', 'lesend']],
        'unterkunft_vorschlagen' => ['Unterkünfte eintragen, bewerten und kommentieren', ['master', 'admin', 'schiri']],
        'unterkunft_verwalten' => ['Unterkünfte bearbeiten und löschen', ['master', 'admin']],
        'eigene' => ['Eigene Abfrage, Ansetzungen, eigenes Archiv, Sperrzeiten, Stammdaten, Notizen & Karten, Lesebestätigung', ['schiri']],
        'rollen' => ['Rollenverwaltung (Konten anlegen, Rollen zuweisen, Konten deaktivieren)', ['master']],
    ];

    public static function allows(string $role, string $permission): bool
    {
        return in_array($role, self::MATRIX[$permission][1] ?? [], true);
    }

    /** @return list<string> */
    public static function of(string $role): array
    {
        return array_keys(array_filter(self::MATRIX, fn ($p) => in_array($role, $p[1], true)));
    }

    public static function label(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }
}
