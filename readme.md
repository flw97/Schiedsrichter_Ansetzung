# Schiedsrichter-Ansetzung

Ansetzung von Badminton-Schiedsrichtern für Ligen und Turniere. Heute liegt der Fokus auf Begegnungen mit baden-württembergischer Beteiligung (2. Bundesliga, Regionalliga Süd-Ost) und Turnieren in BW; dieselbe Anwendung soll bundesweit nutzbar sein. Fachliche Regeln: [docs/domain.md](docs/domain.md).

> **Status:** In Entwicklung.

---

## Wofür

Die Einteilung läuft bisher über Excel: Spiele, Termine und Mannschaften, dazu eine verbindliche Rückmeldung der Schiedsrichter. Nach dem Rückmeldezeitraum veröffentlichen Admins die Saisonplanung. Die Anwendung ersetzt diesen Ablauf.

- Zwei Schiedsrichter pro Ligaspiel; der lizenzhöhere ist verantwortlicher Leiter
- Turniere zusätzlich mit einem Referee (Oberschiedsrichter)
- Vorschlag nach Reisekosten, Lizenz, Verein und Einsatzzahl; insgesamt oder je Spiel übernehmbar
- Rollen Lesend, Schiedsrichter und Admin; Lizenz nur durch Admins, Telefon und Wohnort durch die Schiedsrichter
- Regelwerke, Unterkünfte, Statistik, Archiv, Filter, Lizenzen, Beobachtung, Push, Sperrzeiten

## Begriffe

| Begriff        | Bedeutung                                                              |
|----------------|------------------------------------------------------------------------|
| **Spieltag**   | Ein Datum bzw. Wochenende mit einem oder mehreren Spielen              |
| **Spiel**      | Eine Begegnung zweier Mannschaften mit Ort und Beginn                  |
| **Ansetzung**  | Die Zuordnung eines Schiedsrichters zu einem Spiel                     |
| **Verfügbarkeit** | Zeiträume, in denen ein Schiedsrichter eingesetzt werden kann bzw. nicht |

## Tech-Stack

- Frontend: Vue 3, Vite, Vuetify
- Backend: Laravel 13 (PHP 8.4)
- Datenbank: MariaDB

## Installation & Start

Lokale Entwicklungsumgebung mit Hot Reload (Docker erforderlich):

```bash
git clone <repository-url>
cd Schiedsrichter_Ansetzung
docker compose up --build
```

- Frontend (Vue/Vuetify): http://localhost:3000
- Backend (Laravel): http://localhost:8080 – im Frontend über `/api/...` erreichbar (Vite-Proxy)
- Datenbank (MariaDB): `localhost:3306`, Datenbank/Benutzer/Passwort `schiedsrichter`
- Produktion: https://ansetzung.badminton-gomaringen.de — Deploy über GitHub Actions, siehe [docs/deployment.md](docs/deployment.md)

Demo-Daten laden und anmelden (Passwort `demo1234`; Konten `m.hartmann` Master-Admin, `s.weber` Admin, `t.lang` Lesend, `k.rehberg` Schiedsrichter):

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

Tests: `docker compose exec backend php artisan test`, im Frontend `npm test`, `npm run lint`, `npm run build`.

## Roadmap

- [x] Tech-Stack festlegen
- [x] Datenmodell (Spieltag, Spiel, Schiedsrichter, Ansetzung)
- [x] Verwaltung von Schiedsrichtern
- [x] Verwaltung von Spieltagen und Spielen
- [x] Ansetzungen erstellen und bearbeiten
- [x] Konfliktprüfung
- [ ] Anbindung NuLiga (Spielpläne über den Crawler; Lizenzen noch manuell)
- [ ] Export der Ansetzungsübersicht
- [x] Benachrichtigungen per E-Mail (Cron-Endpunkt; in der App weiterhin die Glocke)
- [x] Passwort vergessen

## Mitwirken

Ideen, Fehlermeldungen und Verbesserungsvorschläge sind willkommen. Lege dafür einfach ein Issue an oder sprich das Projektteam direkt an.

## Lizenz

_Noch nicht festgelegt._
