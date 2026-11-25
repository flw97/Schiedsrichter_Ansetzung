# Added
- Port of the React prototype `frontend/old/schiedsrichter-app-prototyp.html` to Vue/Vuetify (frontend) and Laravel (backend); all 17 screens for the roles Master-Admin, Admin, Lesend and Schiedsrichter
  - Admin: Abfragen (Ansetzung) incl. detail with weighted proposal, NuLiga-Import, Statistik, Karten & Notizen, Archiv, Saisonplanung, Lizenzen, Schiedsrichter, Beobachtung, Regelwerke, Unterkünfte, Standorte, Rollen
  - Schiedsrichter: Abfrage, Ansetzungen (incl. `.ics` export), Archiv, Regelwerke, Unterkünfte, Meine Daten
- Backend API (`backend/routes/api.php`) with Laravel Sanctum token auth; every action is checked by gates generated from `App\Support\Permissions::MATRIX` (single source for backend and the role matrix in the UI)
  ```php
  Gate::define($permission, fn (User $user) => $user->active && Permissions::allows($user->role, $permission));
  ```
  - `GET /api/state` returns the whole role-filtered state; contact data is masked server-side, `GET /api/referees/contacts` reveals it and is logged
  - Denied actions, logins, logouts and contact reveals go to `audit_logs`; tokens expire after 15 minutes of inactivity (`Sanctum::authenticateAccessTokensUsing`)
  - Push messages are stored per recipient (`push_messages`, bell in the app bar) and returned to the acting user as toasts; real mobile push is not connected yet
  - Fake NuLiga client `App\Services\NuLigaClient` (games relative to today, licence data) and `NuLigaSync` (staged games, pending schedule changes requiring reconfirmation, licence sync)
  - Rule books stored as PDF (`storage/app/private/rulebooks`), checked for the `%PDF-` signature, max. 20 MB
- Migration `2026_11_25_190000_create_domain_tables.php`, demo seeder (`php artisan migrate:fresh --seed`, password `demo1234`)
- Feature tests `AuthAndStateTest`, `AssignmentTest`, `RulebookTest`; frontend unit tests with Vitest (`npm test`) for rules, dates, ICS and season planning

# Changed
- `backend/Dockerfile`: passes the `Authorization` header to PHP (Apache stripped it, so API tokens never arrived) and raises upload limits to 20 MB
  ```dockerfile
  echo 'SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1' > /etc/apache2/conf-enabled/authorization.conf
  ```
- Frontend: Vuetify theme `court` with the prototype colours, fonts Fraunces/Inter/IBM Plex Mono via `@fontsource` (Roboto and `unplugin-fonts` removed), `tsconfig.app.json` lib `ES2024`

# Removed
- Vuetify starter content (`HelloWorld.vue`, logos, `styles/settings.scss`)
- From the prototype, deliberately not ported: in-app pdf.js viewer with comment markers (replaced by the browser's PDF viewer in a dialog), AES-encrypted offline PDF cache (no service worker yet, so it had no offline effect), demo role picker on the login page, `webcal://` subscription link (no server endpoint yet)
