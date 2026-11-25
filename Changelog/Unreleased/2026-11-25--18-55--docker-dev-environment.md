# Added
- `docker-compose.yaml` for local development with hot reload
  - `frontend`: `node:22-alpine` running the Vite dev server on port 3000; `./frontend` bind-mounted, `node_modules` in a named volume so native modules (e.g. `sass-embedded`) are built for the container
  - `backend`: PHP 8.4 + Apache built from `backend/Dockerfile` on port 8080; `./backend` bind-mounted so PHP changes apply on the next request
- `backend/public/index.php` front controller with `GET /api/health`
- Vite dev proxy forwards `/api` to the backend (`API_PROXY_TARGET`, defaults to `http://localhost:8080`)
  ```ts
  proxy: { '/api': process.env.API_PROXY_TARGET ?? 'http://localhost:8080' }
  ```
