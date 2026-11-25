# Schiedsrichter-Ansetzung

Ein Tool, mit dem du vor einem Spieltag die Schiedsrichter-Ansetzungen planst und verwaltest – entwickelt für den **TSV Gomaringen**.

> **Status:** 🚧 In Entwicklung – das Projekt steht noch ganz am Anfang.

---

## Motivation

Wer Schiedsrichter für einen Spieltag ansetzt, koordiniert oft per Excel-Liste, WhatsApp-Gruppe und Telefon. Dabei geht schnell der Überblick verloren:

- Welche Spiele haben noch keinen Schiedsrichter?
- Wer ist am Wochenende verfügbar?
- Wer hat in letzter Zeit viele oder wenige Spiele gepfiffen?
- Wurden alle Beteiligten über ihre Ansetzung informiert?

**Schiedsrichter-Ansetzung** bündelt diese Informationen an einem Ort und vereinfacht die Planung im Vorfeld eines Spieltags.

## Geplante Funktionen

### Spiele & Spieltage
- Spieltage mit Datum, Uhrzeit, Spielort und Mannschaften anlegen
- Spiele nach Altersklasse bzw. Liga kennzeichnen
- Übersicht über offene, also noch nicht besetzte Spiele

### Schiedsrichter
- Schiedsrichter mit Kontaktdaten und Qualifikation verwalten (z. B. für welche Altersklassen sie pfeifen dürfen)
- Verfügbarkeiten und Abwesenheiten erfassen
- Einsatzhistorie und Einsatzstatistik pro Schiedsrichter

### Ansetzung
- Schiedsrichter (und ggf. Assistenten) einem Spiel zuordnen
- Konflikte erkennen, z. B. Doppelbelegung, fehlende Qualifikation oder Nichtverfügbarkeit
- Vorschläge für eine faire Verteilung der Einsätze
- Status pro Ansetzung: *geplant*, *bestätigt*, *abgesagt*

### Kommunikation & Export
- Ansetzungsübersicht pro Spieltag als PDF oder Liste exportieren
- Benachrichtigung der angesetzten Schiedsrichter (z. B. per E-Mail)

## Begriffe

| Begriff        | Bedeutung                                                              |
|----------------|------------------------------------------------------------------------|
| **Spieltag**   | Ein Datum bzw. Wochenende mit einem oder mehreren Spielen              |
| **Spiel**      | Eine Begegnung zweier Mannschaften mit Ort und Anstoßzeit              |
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
- [ ] Anbindung NuLiga (aktuell simuliert)
- [ ] Export der Ansetzungsübersicht
- [ ] Benachrichtigungen (Push-Versand; aktuell nur in der App)

## Mitwirken

Ideen, Fehlermeldungen und Verbesserungsvorschläge sind willkommen. Lege dafür einfach ein Issue an oder sprich das Projektteam direkt an.

## Lizenz

_Noch nicht festgelegt._
