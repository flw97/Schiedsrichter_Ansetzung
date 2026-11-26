# Local dev environment

- Start: `docker compose up --build`; frontend http://localhost:3000, backend http://localhost:8080
- Frontend calls the backend via relative `/api/...` URLs; Vite proxies them (`frontend/vite.config.mts`, target from `API_PROXY_TARGET`, `http://backend` in compose). No CORS needed in dev.
- Hot reload: both source dirs are bind-mounted. Vite HMR for the frontend; PHP is re-read per request.
- `frontend/node_modules` in the container is a named volume (`frontend_node_modules`), separate from the host's. After changing `package.json`, restart the `frontend` service (`npm ci` runs on start).
- Backend env vars: `backend/.env.development` (loaded via `env_file`, no `.env` file needed). DB credentials there must match the `db` service in `docker-compose.yaml`. `backend/.env.production` is gitignored and is not loaded while `APP_ENV=local`.
- Backend start command runs `composer install` and `php artisan migrate --force`, then Apache. `vendor/` lives in the named volume `backend_vendor`; after changing `composer.json` restart the `backend` service.
- Apache runs as `UID`/`GID` (default 1000) so Laravel can write to the bind-mounted `storage/`. Logs go to stderr (`docker compose logs backend`).
- Artisan/tests: `docker compose exec backend php artisan ...`, `docker compose exec backend php artisan test`, lint with `docker compose exec backend vendor/bin/pint`. The image installs the PHP `zip` extension; `ExtractReleaseTest` needs it. Rebuild the backend image after pulling that Dockerfile change.
- Tests use sqlite in-memory. `phpunit.xml` uses `<server force="true">` because Laravel reads `$_SERVER` first, where the container env (`DB_CONNECTION=mariadb`) would otherwise win and tests would hit the dev DB.
- MariaDB: `localhost:3306`, db/user/password `schiedsrichter`, root password `root`; data persisted in volume `db_data` (`docker compose down -v` resets it).
- Running the frontend without Docker (`npm run dev`) still works; the proxy then targets `localhost:8080`.
