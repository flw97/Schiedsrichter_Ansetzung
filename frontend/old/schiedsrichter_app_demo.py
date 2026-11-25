"""
Schiedsrichter-App — Demo-Starter
----------------------------------
Oeffnet den HTML-Prototyp lokal in einem eigenen Fenster (Chrome/Edge im
App-Modus, ohne Adressleiste) oder, falls kein Chromium-Browser gefunden
wird, im Standardbrowser. Nutzt nur die Python-Standardbibliothek, damit
sich das Skript ohne zusaetzliche Pakete mit PyInstaller zu einer .exe
packen laesst (siehe BUILD-EXE-ANLEITUNG.md).
"""
import os
import sys
import shutil
import subprocess
import webbrowser

APP_FILENAME = "schiedsrichter-app-prototyp.html"


def resource_path(filename):
    # Funktioniert sowohl beim Start als .py-Skript als auch gebuendelt als .exe
    base = getattr(sys, "_MEIPASS", os.path.dirname(os.path.abspath(__file__)))
    return os.path.join(base, filename)


def find_chromium_browser():
    candidates = ["chrome", "google-chrome", "msedge", "chromium", "chromium-browser"]
    for name in candidates:
        path = shutil.which(name)
        if path:
            return path
    # Typische Windows-Installationspfade
    win_paths = [
        r"C:\Program Files\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    ]
    for p in win_paths:
        if os.path.exists(p):
            return p
    return None


def main():
    html_path = resource_path(APP_FILENAME)
    if not os.path.exists(html_path):
        print(f"Konnte {APP_FILENAME} nicht finden neben dem Programm.")
        input("Enter zum Beenden...")
        return

    file_url = "file:///" + html_path.replace("\\", "/")
    browser = find_chromium_browser()

    if browser:
        subprocess.Popen([
            browser,
            f"--app={file_url}",
            "--window-size=1280,860",
        ])
    else:
        webbrowser.open(file_url)


if __name__ == "__main__":
    main()
