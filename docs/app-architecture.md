# App architecture (port of the prototype)

Source of truth for the UI was `frontend/old/schiedsrichter-app-prototyp.html` (React, in-memory). Domain: badminton referee assignment (Lizenzstufen Grundlizenz … BWF-Accredited, Ligen Regionalliga Südost / 2. / 1. Bundesliga, BW focus).

## Backend (`backend/`)
- Auth: Sanctum bearer tokens (`POST /api/login`, username + password, demo password `demo1234`). Idle timeout 15 min in `AppServiceProvider`.
- Permissions: `app/Support/Permissions.php` (`MATRIX` -> gates of the same name, used as `can:<perm>` route middleware). Gate denials are logged via `Gate::after`.
- State: `StateController` returns everything the SPA needs in one JSON; the SPA reloads it after every action. Fine for club-sized data; switch to granular endpoints if it grows.
- Games (`games` table) cover manual events, imported NuLiga games and NuLiga games not yet taken over (`imported = false`). `nuliga_change` + `pending_*` hold pending NuLiga changes. Crew lives in pivot `game_referee` with `kind` confirmed | planned | head | planned_head and `reconfirmed`.
- `Game::confirm/plan/reopen` contain the assignment state transitions; leader = `Licenses::leader` (higher licence, then `season_count`).
- Actions return `{ pushes: string[] }` via `Controller::done()`; `App\Services\Push` is request-scoped.
- NuLiga is faked by `App\Services\NuLigaClient`; replace it with the real nuPortalRS client.
- Referee statistics columns (`season_count`, `monthly_load`, `fairness`, `trend`, `last_game`) are seeded values, not yet derived from history.

## Frontend (`frontend/src/`)
- `stores/app.ts`: state + `act(method, path, body, successText)` (shows pushes/errors as toasts, reloads state).
- `domain/`: pure logic ported from the prototype — `rules.ts` (mirror of `Licenses.php`), `dates.ts`, `geo.ts` (place table, distances), `planning.ts` (season planner heuristic), `ics.ts`; unit tests in `domain/__tests__` (`npm test`).
- `router/index.ts`: one route per page with `meta.perm`/`nav`; `App.vue` builds the drawer from it and redirects to the first allowed page.
- Shared components: `GameSummary`, `GameFilter`, `ReportForm`/`ReportView`, `ConfirmAction`, `GeoMap` (Leaflet + OpenStreetMap tiles from `tile.openstreetmap.org`; OSM tile policy allows light use only, use an own tile server or provider for heavy traffic), `ScoreCompass`, `StatTile`, `ArchiveEdit`.

## Gotchas
- Apache strips `Authorization` unless `SetEnvIf` is configured (see `backend/Dockerfile`); after changing the Dockerfile run `docker compose up -d --build backend`.
- After adding Vuetify components, Vite re-optimizes deps once and reloads (504 "Outdated Optimize Dep" in the console is transient).
- Geo positions come from the town name in addresses (`GEO_PLACES`), unknown towns count as 20 km.
