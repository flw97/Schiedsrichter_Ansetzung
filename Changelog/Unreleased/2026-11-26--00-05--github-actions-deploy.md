# Added
- GitHub Actions workflow `.github/workflows/ci.yml` (push to `main`, pull requests, manual run)
  - Backend: PHP 8.4, `vendor/bin/pint --test`, `php artisan test`; separate job builds `composer install --no-dev` and uploads `vendor/` plus `bootstrap/cache` as a tarball (those paths are gitignored)
  - Frontend: Node 22, `npm test`, `npm run build`; ESLint stays non-blocking (`continue-on-error`), same as GitLab `allow_failure`
  - Deploy job on the default branch only, environment `production`, concurrency group `production` with `cancel-in-progress: false` so a running FTPS upload is not aborted mid-maintenance
  - Reuses `deploy/ftp-deploy.sh`. GitLab's `PRODUCTION_ENV` file variable is a repository secret here; the workflow writes it to `$RUNNER_TEMP/production.env` because the script expects a path
- `docs/deployment.md` for the Netcup FTPS setup, GitHub secrets (`FTP_USER`, `FTP_PASSWORD`, `PRODUCTION_ENV`) and the production `.env` requirements. `.gitlab-ci.yml` already pointed at this file
