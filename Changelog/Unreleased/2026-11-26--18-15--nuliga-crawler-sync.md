# Added
- NuLiga sync reads the 1. Bundesliga, 2. Bundesliga and Regionalliga Südost from the crawler at `NULIGA_API_URL` (default `https://nuliga.badminton-gomaringen.de/api`)
  - `CrawlerNuLigaClient` sends `NULIGA_TOKEN` as a bearer token, loads the DBV and GrSO group lists, then each matching group
  - A home or away club is `BW` when its name is in `GET /api/clubs` (the four BW Bezirke). Other sides stay unset, so they still need an explicit import
  - Fixtures without a date are skipped. A missing hall becomes `noch keine Halle`
  - 2. Bundesliga titles keep the staffel (`2. Bundesliga Süd` / `Nord`). The stored competition stays `2. Bundesliga`, which is what the licence check uses
- Local Docker reads the token from `backend/.env.local` (gitignored). Production needs the same two variables in `api/.env.production`

# Changed
- `NuLigaSync` depends on `NuLigaFeed`. With a token the app binds `CrawlerNuLigaClient`; without one it keeps the demo `NuLigaClient`. PHPUnit forces an empty token
- The seeder always builds the demo schedule through `NuLigaClient`, so `migrate:fresh --seed` does not call the crawler
- The crawler publishes no referee licences. `CrawlerNuLigaClient::licenses()` is empty, and licence maintenance stays manual
