# Project Notes Index

## Folder structure
- `frontend/` - Vue 3 + Vite + Vuetify SPA (TypeScript, Pinia, Vue Router); `frontend/old/` holds the original React prototype and is excluded from ESLint
- `backend/` - Laravel 13 API (PHP 8.4, Apache), routes in `backend/routes/api.php` (prefixed `/api`), MariaDB 11.8
- `docker-compose.yaml` - local dev environment
- `deploy/` - FTPS release: frontend files in `/ansetzung`, Laravel app in `/ansetzung/api` (`ftp-deploy.sh`, root `.htaccess`, post-deploy hook)
- `.github/workflows/ci.yml` - GitHub Actions test, build and deploy
- `Changelog/` - Keep a Changelog entries, one file per change

## Notes
- [Local dev environment](./dev-environment.md): how the Docker dev setup, hot reload and API proxy work
- [App architecture](./app-architecture.md): data model, API/state flow, permissions, frontend domain modules and gotchas of the prototype port
- [Deployment](./deployment.md): Netcup webspace via FTPS. Releases are zip archives (not a per-file mirror) plus the required GitHub Actions secrets
