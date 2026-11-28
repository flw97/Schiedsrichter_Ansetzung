# Deployment

Production: https://ansetzung.badminton-gomaringen.de — Netcup vhost. Over SSH that directory is `/ansetzung`. The FTPS login starts inside it, so the deploy path is `/`.

GitHub Actions (`.github/workflows/ci.yml`) tests and builds, then on the default branch runs `deploy/ftp-deploy.sh`.

## Release

- Backend: `composer install --no-dev` (PHP 8.4). `vendor/` and `bootstrap/cache` (package discovery) are the build artifacts. The Laravel tree is uploaded into `api/` inside the vhost. `deploy/htaccess` sends `/api` and `/up` to `api/public/index.php`; other URLs stay on the frontend. Files under `api/` besides `api/public/` are not served directly.
- Frontend: Node 22, `npm ci`, `npm test`, `npm run build`. `frontend/dist/` is uploaded into the vhost root (no `frontend/` folder).
- Frontend ESLint must pass. `frontend/old/` is the prototype and is excluded.
- Upload is FTPS (`lftp`, SSL required) of at most two zip archives, extracted on the server by `backend/scripts/extract-release.php` (uploaded under a random name, called once, deleted). A per-file mirror re-uploads every file in `vendor/`; that takes about a quarter hour. `vendor/` is sent only when `backend/composer.lock` changes. The app archive (frontend build and Laravel source, without `vendor/`) is sent only when its contents change. The server stores those two hashes in `api/vendor.sha` and `api/app.sha`.
- `api/storage/` and `api/.env.production` stay on the server. The deploy never writes or deletes that env file. After an app upload, files that are no longer in the archive are deleted, except `api/storage/`, `api/vendor/` and the two stamp files. A stray `.env` in the vhost root goes away with that delete, so it is not served as a static file.
- The site stays up while the archives transfer. Maintenance mode starts after the upload, for the extract and for `deploy/post-deploy.php`. That hook is uploaded to the vhost root under a random name and called once as `https://ansetzung.badminton-gomaringen.de/<that name>` (migrate, config/route/event cache, `up`), then deleted. A failed hook leaves the site in maintenance until the next successful deploy. The server PHP needs the `zip` extension.

## Secrets

Repository secrets (Settings → Secrets and variables → Actions):

- `FTP_USER`
- `FTP_PASSWORD`

`FTP_HOST` is `hosting256340.ae92d.netcup.net`. Its certificate is `*.netcup.net`, so `deploy/ftp-deploy.sh` keeps certificate verification and turns off only the hostname check. `FTP_PATH` and `APP_URL` are workflow `env` values too. Deploy runs only for a push (or manual run) of the default branch, after tests and builds. The `production` environment records the deployment; protection rules there are optional.

## Env files

`api/.env.production` in the vhost (SSH: `/ansetzung/api/.env.production`) is created and edited on the webspace. CI does not have a copy. Laravel loads it because `APP_ENV` is unset or `production` (`backend/bootstrap/app.php`, and `SetEnv APP_ENV production` in `deploy/htaccess`). The migrate hook fails until that file exists. Local Docker sets `APP_ENV=local` and uses `backend/.env.development`. Keep `APP_MAINTENANCE_DRIVER=file`; the deploy writes `api/storage/framework/down`.

NuLiga sync needs `NULIGA_API_URL` (default `https://nuliga.badminton-gomaringen.de/api`) and `NULIGA_TOKEN` (operator bearer token) in that file. Without the token the demo schedule is used. The same pair lives in gitignored `backend/.env.local` for Docker.

Notification and password-reset mail use Laravel mail: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. The default mailer is `log`, which does not deliver.

`CRON_TOKEN` is a long random string. The hoster cron should call `https://ansetzung.badminton-gomaringen.de/api/cron?token=…` every five minutes (`X-Cron-Token` is the same secret without putting it in the query string). An empty token closes the endpoint. The response body is empty when nothing was sent. Password-reset mail is sent in the request, not by this cron.

`FRONTEND_URL` is the origin in those links (the reset page `/passwort-zuruecksetzen`). It defaults to `APP_URL`; set `APP_URL=https://ansetzung.badminton-gomaringen.de`.

`config:cache` during deploy reads `.env.production`. A value added after that stays invisible until the next deploy.

`frontend/.env.production` stays in the repository. `npm run build` loads it (Vite production mode). Only variables whose names start with `VITE_` are included in the built JavaScript. It is not uploaded.
