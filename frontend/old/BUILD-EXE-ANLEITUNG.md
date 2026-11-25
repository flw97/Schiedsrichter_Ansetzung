# Schiedsrichter-App als .exe bauen

Ich kann hier in der Sandbox keine Windows-.exe kompilieren (kein Internet,
kein Windows-Compiler). Diese drei Dateien reichen aber, um dir auf deinem
eigenen Windows-Rechner in ca. 1 Minute eine echte .exe zu bauen:

- `schiedsrichter_app_demo.py`
- `schiedsrichter-app-prototyp.html`

## Schritte (auf deinem Windows-PC, mit installiertem Python)

1. Beide Dateien in einen Ordner legen.
2. Terminal (PowerShell/cmd) in diesem Ordner öffnen.
3. PyInstaller installieren (einmalig):
   ```
   pip install pyinstaller
   ```
4. .exe bauen:
   ```
   pyinstaller --onefile --noconsole --name "Schiedsrichter-App" --add-data "schiedsrichter-app-prototyp.html;." schiedsrichter_app_demo.py
   ```
5. Die fertige Datei liegt danach unter `dist\Schiedsrichter-App.exe`.
   Diese .exe kannst du einfach weitergeben und per Doppelklick starten —
   sie öffnet den Prototyp in einem eigenen Fenster (Chrome/Edge-App-Modus,
   ohne Adressleiste), falls einer der beiden Browser installiert ist,
   sonst im Standardbrowser.

## Sofort-Alternative ohne Build

Falls es nur für eine schnelle Demo reicht: `Schiedsrichter-App-starten.bat`
zusammen mit `schiedsrichter-app-prototyp.html` in denselben Ordner legen
und die .bat per Doppelklick starten — keine Installation, kein Build nötig.
