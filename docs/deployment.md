# Deployment

Production: https://ansetzung.badminton-gomaringen.de — Netcup webspace, app directory `/ansetzung`. The vhost document root is that directory; `deploy/htaccess` rewrites every request into `public/`.

GitHub Actions (`.github/workflows/ci.yml`) tests and builds, then on the default branch runs `deploy/ftp-deploy.sh`.

## Release

- Backend: `composer install --no-dev` (PHP 8.4). `vendor/` and `bootstrap/cache` (package discovery) are the build artifacts.
- Frontend: Node 22, `npm ci`, `npm test`, `npm run build`. `frontend/dist/` is copied into `public/`.
- Frontend ESLint is allowed to fail until the existing errors are fixed.
- Upload is FTPS (`lftp`, SSL required). `storage/` is kept on the server (only missing directories are created). `backend/.env.production` is uploaded as `/ansetzung/.env.production` and is not part of the git mirror. A leftover `.env` from an older deploy is removed. Other files are mirrored with deletes.
- The app is in maintenance mode during the upload. `deploy/post-deploy.php` is uploaded under a random name, called once over HTTPS (migrate, config/route/event cache, `up`), then deleted. A failed hook leaves the site in maintenance until the next successful deploy.

## Secrets

Repository secrets (Settings → Secrets and variables → Actions):

- `FTP_USER`
- `FTP_PASSWORD`
- `PRODUCTION_ENV` — full text of `backend/.env.production`. The workflow writes it to a temp file; `deploy/ftp-deploy.sh` uploads that file to the server. It is not used for the frontend build.

`FTP_HOST`, `FTP_PATH` and `APP_URL` are workflow `env` values. Deploy runs only for a push (or manual run) of the default branch, after tests and builds. The `production` environment records the deployment; protection rules there are optional.

## Env files

`backend/.env.production` is gitignored. On the server Laravel loads it because `APP_ENV` is unset or `production` (`backend/bootstrap/app.php`, and `SetEnv APP_ENV production` in `deploy/htaccess`). Local Docker sets `APP_ENV=local`, so this file is not loaded there. Keep `APP_MAINTENANCE_DRIVER=file`; the deploy writes `storage/framework/down`.

`frontend/.env.production` stays in the repository. `npm run build` loads it (Vite production mode). Only variables whose names start with `VITE_` are included in the built JavaScript. It is not uploaded.
