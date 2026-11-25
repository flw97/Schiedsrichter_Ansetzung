@echo off
setlocal
set "HTMLFILE=%~dp0schiedsrichter-app-prototyp.html"

where chrome >nul 2>nul
if %errorlevel%==0 (
    start "" chrome --app="file:///%HTMLFILE%" --window-size=1280,860
    goto :eof
)

if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --app="file:///%HTMLFILE%" --window-size=1280,860
    goto :eof
)

if exist "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" (
    start "" "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --app="file:///%HTMLFILE%" --window-size=1280,860
    goto :eof
)

start "" "%HTMLFILE%"
