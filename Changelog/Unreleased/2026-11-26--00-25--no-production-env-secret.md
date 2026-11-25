# Changed
- Deploy no longer reads or uploads `PRODUCTION_ENV`. Supersedes the secret described in `Changelog/Unreleased/2026-11-26--00-05--github-actions-deploy.md` and `2026-11-26--00-10--production-env-file.md`.
- `deploy/ftp-deploy.sh` still excludes `api/.env.production` from the deleting mirror, so a file edited on the webspace is left in place. GitHub secrets for deploy are only `FTP_USER` and `FTP_PASSWORD`.
