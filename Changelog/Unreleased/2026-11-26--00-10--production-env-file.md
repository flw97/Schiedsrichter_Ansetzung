# Changed
- Deploy uploads the production env to `/ansetzung/.env.production` and deletes a leftover `.env`. `deploy/htaccess` sets `APP_ENV=production`. `backend/bootstrap/app.php` loads `.env.production` when `APP_ENV` is unset or `production`, so local Docker (`APP_ENV=local`) and PHPUnit (`testing`) keep their own env.
- `PRODUCTION_ENV` is still the file contents passed into `deploy/ftp-deploy.sh`. The frontend build does not read it.

# Removed
- `backend/.env.production` from the git index. It stays on disk and remains ignored by `backend/.gitignore`. `frontend/.env.production` stays in the repo for `npm run build`.

# Security
- `backend/.env.production` was already pushed to `origin/main` (commit `bd324b3`). Removing it from the branch tip does not erase it from history. The database password in that file should be rotated, and the history rewritten, if the repository is shared.
