# Deployment

Production: https://ansetzung.badminton-gomaringen.de — Netcup webspace, directory `/ansetzung`, which is the vhost document root.

GitHub Actions (`.github/workflows/ci.yml`) tests and builds, then on the default branch runs `deploy/ftp-deploy.sh`.

## Release

- Backend: `composer install --no-dev` (PHP 8.4). `vendor/` and `bootstrap/cache` (package discovery) are the build artifacts. The Laravel tree is uploaded into `/ansetzung/api`. `deploy/htaccess` sends `/api` and `/up` to `api/public/index.php`; other URLs stay on the frontend. Files under `api/` besides `api/public/` are not served directly.
- Frontend: Node 22, `npm ci`, `npm test`, `npm run build`. `frontend/dist/` is uploaded into `/ansetzung` itself (no `frontend/` folder).
- Frontend ESLint must pass. `frontend/old/` is the prototype and is excluded.
- Upload is FTPS (`lftp`, SSL required). `api/storage/` and `api/.env.production` stay on the server; the deploy never writes or deletes that env file. A leftover root `.env` or `.env.production` is removed so it is not served as a static file. Other files are mirrored with deletes.
- The app is in maintenance mode during the upload. `deploy/post-deploy.php` is uploaded under a random name, called once over HTTPS (migrate, config/route/event cache, `up`), then deleted. A failed hook leaves the site in maintenance until the next successful deploy.

## Secrets

Repository secrets (Settings → Secrets and variables → Actions):

- `FTP_USER`
- `FTP_PASSWORD`

`FTP_HOST` is `hosting256340.ae92d.netcup.net`. Its certificate is `*.netcup.net`, so `deploy/ftp-deploy.sh` keeps certificate verification and turns off only the hostname check. `FTP_PATH` and `APP_URL` are workflow `env` values too. Deploy runs only for a push (or manual run) of the default branch, after tests and builds. The `production` environment records the deployment; protection rules there are optional.

## Env files

`/ansetzung/api/.env.production` is created and edited on the webspace. CI does not have a copy. Laravel loads it because `APP_ENV` is unset or `production` (`backend/bootstrap/app.php`, and `SetEnv APP_ENV production` in `deploy/htaccess`). The migrate hook fails until that file exists. Local Docker sets `APP_ENV=local` and uses `backend/.env.development`. Keep `APP_MAINTENANCE_DRIVER=file`; the deploy writes `api/storage/framework/down`.

`frontend/.env.production` stays in the repository. `npm run build` loads it (Vite production mode). Only variables whose names start with `VITE_` are included in the built JavaScript. It is not uploaded.
