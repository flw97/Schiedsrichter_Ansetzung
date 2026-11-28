# Fachlicher Rahmen

Badminton-Schiedsrichteransetzung. Heute macht jeder Landesverband seine Spiele in Excel. Im Fokus stehen Begegnungen mit Beteiligung einer baden-württembergischen Mannschaft (2. Bundesliga, Regionalliga Süd-Ost) und Turniere in BW. Dieselbe Anwendung soll bundesweit für Bundesligen und Turniere nutzbar sein. Solange der Fokus BW bleibt, müssen auch Anfragen anderer Landesverbände importierbar sein, die einen BW-Schiedsrichter für ein Spiel in ihrem Bereich anfordern. Bei bundesweiter Nutzung setzt jeder Verband seine Spiele selbst an.

Ligen im Code: `Licenses::LEAGUES` / `LEAGUES` (Regionalliga Südost, 2. und 1. Bundesliga). BW-Bezug: `classify` in `frontend/src/domain/rules.ts` (regulär nur Heimspiele in BW). Import: NuLiga-Crawler, siehe [App architecture](./app-architecture.md).

## Saisonplanung

Ablauf, der die Excel-Tabelle ersetzt:

1. Spiele, Daten und Mannschaften sind sichtbar.
2. Schiedsrichter geben verbindlich Rückmeldung: wann sie Zeit haben und für welche Spiele sie eingeplant werden möchten. Gleiches gilt für Turniere in BW.
3. Nach dem Rückmeldezeitraum legen Admins die endgültige Einteilung fest und veröffentlichen sie.

Grundsätze für Ligaspiele:

- Zwei Schiedsrichter pro Begegnung.
- Der lizenzhöhere ist verantwortlicher Leiter. Bei gleicher Lizenz der erfahrenere (im Code: mehr Einsätze in der Saison, `Licenses::leader` / `pickLeader`).

Heuristik der Vorabverteilung: `frontend/src/domain/planning.ts` (`SLOTS = 2`). Oberfläche: `SaisonplanungPage.vue`. Der Vorschlag kann insgesamt oder je Spiel übernommen werden.

## Turniere

Neben Ligaspielen: Landesmeisterschaften, Gruppenmeisterschaften (z. B. Süd-Ost-Meisterschaft O19), Deutsche Meisterschaften, Ranglistenturniere auf Gruppen- oder nationaler Ebene.

Ein Turnier braucht Schiedsrichter und genau einen Referee (Oberschiedsrichter). Das ist eine Besetzung (`head` / `planned_head` an `game_referee`), keine eigene Benutzerrolle. Siehe `GameDetailPage.vue`.

## Eignung

Reisekosten sollen klein bleiben. Pflicht: erforderliche Lizenzstufe (`requiredLevel`) und nicht den eigenen Verein leiten (`clubConflict`; eigener Verein nur als Notlösung, wenn sonst nicht genug Personen da sind).

Für eine Begegnung entsteht eine Rangliste. Der Quotient gewichtet nach eigenen Maßgaben Entfernung, Lizenzstufe, anderen Verein und Gesamtzahl der Saisoneinsätze. Die vorläufige Planung ist ein Vorschlag.

Ist-Stand der Einzelansetzung (`weightedScore`, Gewichtung in `GameDetailPage.vue`): Entfernung, Verfügbarkeit, faire Verteilung. Lizenz und eigener Verein sind harte Filter bzw. Sortierstufen, keine Gewichtungsfaktoren. Die Saisonplanung optimiert Fahrtkosten (`KM_RATE`) mit festen Aufschlägen für Vereinskonflikt und Zusage.

## Rollen und Stammdaten

Rollen: Lesezugriff, Schiedsrichter, Referee, Admin. Sie sollen vergeben und aus NuLiga übernommen werden können.

Im Code (`Permissions::ROLES`): `lesend`, `schiri`, `admin`, `master`. NuLiga liefert heute weder Rollen noch Lizenzen; Lizenzen bleiben manuell.

Pflege geteilt:

- Jedes Konto pflegt sich selbst über das Menü am Namen: Benutzer (`PUT /me`), Lizenz (`PUT /me/license`), Standort (`PUT /me/standort`). Fehlt ein Schiedsrichter-Profil, legt das erste Speichern von Lizenz oder Standort eines an.
- Schiedsrichter zusätzlich: Telefon, Verein, Verfügbarkeit, Sperrzeiten (`updateMe`, `MeineDatenPage`).
- Admins in der Übersicht links: Lizenzen aller Personen und Perspektiv-Team (`updateLicense`, `updateTeams`). Standorte links ist die Karte aller Adressen.

## Weitere Funktionen

- Regelwerke als PDF, von Admins austauschbar, in der App als fortlaufendes Dokument (`RulebookController`).
- Unterkünfte bei Übernachtung: Schiedsrichter tragen ein und bewerten (Preis/Leistung, Ausstattung, Adresse, Entfernung zum Spielort).
- Statistik: Karten je Schiedsrichter und Liga, Anzahl der Einsätze.
- Archiv abgeschlossener Spiele.
- Filter über Liga, Art (Liga/Turnier), Schiedsrichter, Spielorte, Unterkünfte (`gameMatches`).
- Lizenzverwaltung, Leistungsnachweise, Verlängerung, Perspektivteam.
- Beobachtung und Bewertung als Grundlage für Weiterentwicklung.
- Push, wenn sich die eigene Ansetzung ändert oder eine Ansetzung wieder für alle frei wird (`App\Services\Push`).
- Sperrzeiten (z. B. Urlaub) schließen die Person aus (`isBlocked`). Abgelaufene Sperrzeiten sollen automatisch archiviert werden; gespeichert werden sie bisher nur am Schiedsrichter, ohne Archiv-Lauf.
