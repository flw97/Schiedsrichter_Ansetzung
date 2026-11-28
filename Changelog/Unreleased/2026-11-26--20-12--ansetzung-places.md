# Changed
- Standorte and Unterkünfte take their places from the current Ansetzung (`venuesOf` on `activeGames`) and label them Ansetzung
- A hall name without a town uses the town in the home-club name; `mössingen` is in `GEO_PLACES` so it is not read as Singen
- `distKm` uses that home-club town when the hall name does not resolve
