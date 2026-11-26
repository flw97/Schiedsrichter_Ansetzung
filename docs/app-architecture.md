# App architecture (port of the prototype)

Source of truth for the UI was `frontend/old/schiedsrichter-app-prototyp.html` (React, in-memory). Domain: badminton referee assignment (Lizenzstufen Grundlizenz … BWF-Accredited, Ligen Regionalliga Südost / 2. / 1. Bundesliga, BW focus).

## Backend (`backend/`)
- Auth: Sanctum bearer tokens (`POST /api/login`, username + password, demo password `demo1234`). Idle timeout 15 min in `AppServiceProvider`.
- Permissions: `app/Support/Permissions.php` (`MATRIX` -> gates of the same name, used as `can:<perm>` route middleware). Gate denials are logged via `Gate::after`.
- State: `StateController` returns everything the SPA needs in one JSON; the SPA reloads it after every action. Fine for club-sized data; switch to granular endpoints if it grows.
- Games (`games` table) cover manual events, imported NuLiga games and NuLiga games not yet taken over (`imported = false`). `nuliga_change` + `pending_*` hold pending NuLiga changes. Crew lives in pivot `game_referee` with `kind` confirmed | planned | head | planned_head and `reconfirmed`.
- `Game::confirm/plan/reopen` contain the assignment state transitions; leader = `Licenses::leader` (higher licence, then `season_count`).
- Actions return `{ pushes: string[] }` via `Controller::done()`; `App\Services\Push` is request-scoped. `GET /api/notifications?after={id}` returns the signed-in user's newer `push_messages` (at most 50).
- NuLiga games come from the crawler at `https://nuliga.badminton-gomaringen.de/api` when `NULIGA_TOKEN` is set (`CrawlerNuLigaClient`: 1. Bundesliga, 2. Bundesliga, Regionalliga Südost). A side is `BW` when the club name is in `GET /api/clubs`. The crawler has no referee licences, so `licenses()` is empty and licences stay manual. Without a token, `NuLigaClient` still serves the demo schedule. The seeder always uses that demo client for tests and `migrate:fresh --seed`. Local Docker reads the token from `backend/.env.local`.
- A Schiedsrichter account creates an empty referee profile (`UserController`). The person sets name, club, phone, email and address (Standort, `MeineDatenPage`, town lookup in `domain/geo.ts`) themselves. Admins set licence and Perspektiv-Team. Migration `2026_11_26_183400_drop_demo_fixtures` removes the seeded prototype referees, their logins, the demo schedule and “Hotel am Fluss” from databases that already had them. The staff logins `m.hartmann`, `s.weber` and `t.lang` stay.
- Referee statistics columns (`season_count`, `monthly_load`, `fairness`, `trend`, `last_game`) are seeded values, not yet derived from history.

## Frontend (`frontend/src/`)
- `stores/app.ts`: state + `act(method, path, body, successText)` (reloads state). Success and error toasts go through vue3-toastify (`notify.ts`). Toast surfaces, text, icons and the progress bar use the active Vuetify colors (`styles/main.css`, `--surface`, `--ink`, success/error/info/warning). While a session is open the store polls `GET /api/notifications?after=` every second, toasts new push messages, and prepends them to the bell. The bearer token lives in `sessionStorage`. `App.vue` keeps the login form hidden while a stored token is restored via `GET /state`.
- `domain/`: pure logic ported from the prototype — `rules.ts` (mirror of `Licenses.php`), `dates.ts`, `geo.ts` (place table, distances), `planning.ts` (season planner heuristic), `ics.ts`; unit tests in `domain/__tests__` (`npm test`).
- `router/index.ts`: one route per page with `meta.perm`/`nav`; `App.vue` builds the drawer from it and redirects to the first allowed page.
- Shared components: `GameSummary`, `GameFilter`, `ReportForm`/`ReportView`, `ConfirmAction`, `GeoMap` (Leaflet + OpenStreetMap tiles from `tile.openstreetmap.org`; OSM tile policy allows light use only, use an own tile server or provider for heavy traffic), `ScoreCompass`, `StatTile`, `ArchiveEdit`.
- Visual system: green/white theme in `plugins/vuetify.ts` (`light`: background `#F3F7F4`, primary `#1B4336`; `dark`: background `#0C1612`, primary `#8FCBB0`). Default follows the system preference (`theme.ts`, `localStorage` key `ansetzung-theme`: `system` | `light` | `dark`). The drawer uses theme color `brand` (`#1B4336`) in both schemes. CSS tokens in `styles/main.css` (`--ink`, `--paper`, `--line`, …) read the active Vuetify variables. `ThemeToggle.vue` sits in the app bar and on the login page. One typeface, Inter (`.font-display` and `.font-mono` are Inter, the latter with tabular figures). Club mark: `frontend/public/logo.svg` (copy of the repo-root `logo.svg`). `favicon.ico`, `favicon.png` and `apple-touch-icon.png` are rasterized from that file.

## Gotchas
- Apache strips `Authorization` unless `SetEnvIf` is configured (see `backend/Dockerfile`); after changing the Dockerfile run `docker compose up -d --build backend`.
- After adding Vuetify components, Vite re-optimizes deps once and reloads (504 "Outdated Optimize Dep" in the console is transient).
- Geo positions come from the town name in addresses (`GEO_PLACES`), unknown towns count as 20 km.
