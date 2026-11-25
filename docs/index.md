# Project Notes Index

## Folder structure
- `frontend/` - Vue 3 + Vite + Vuetify SPA (TypeScript, Pinia, Vue Router); `frontend/old/` holds the original React prototype
- `backend/` - Laravel 13 API (PHP 8.4, Apache), routes in `backend/routes/api.php` (prefixed `/api`), MariaDB 11.8
- `docker-compose.yaml` - local dev environment
- `Changelog/` - Keep a Changelog entries, one file per change

## Notes
- [Local dev environment](./dev-environment.md): how the Docker dev setup, hot reload and API proxy work
- [App architecture](./app-architecture.md): data model, API/state flow, permissions, frontend domain modules and gotchas of the prototype port
