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

_Wird noch festgelegt._

## Installation & Start

_Folgt, sobald die erste lauffähige Version vorliegt._

```bash
git clone <repository-url>
cd Schiedsrichter_Ansetzung
# weitere Schritte folgen
```

## Roadmap

- [ ] Tech-Stack festlegen
- [ ] Datenmodell (Spieltag, Spiel, Schiedsrichter, Ansetzung)
- [ ] Verwaltung von Schiedsrichtern
- [ ] Verwaltung von Spieltagen und Spielen
- [ ] Ansetzungen erstellen und bearbeiten
- [ ] Konfliktprüfung
- [ ] Export der Ansetzungsübersicht
- [ ] Benachrichtigungen

## Mitwirken

Ideen, Fehlermeldungen und Verbesserungsvorschläge sind willkommen. Lege dafür einfach ein Issue an oder sprich das Projektteam direkt an.

## Lizenz

_Noch nicht festgelegt._
