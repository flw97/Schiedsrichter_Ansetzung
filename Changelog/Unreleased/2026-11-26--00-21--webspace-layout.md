# Changed
- Webspace layout under `/ansetzung` (vhost document root):
  - Built frontend directly in `/ansetzung` (`index.html`, `assets/`), no `frontend/` folder
  - Laravel app in `/ansetzung/api`, including `/ansetzung/api/.env.production` and `api/storage/`
- `deploy/htaccess` sends `/api` and `/up` to `api/public/index.php` and falls back to `index.html` for the SPA. The deploy hook is called at `/api/public/<random>.php`.
- `backend/public/.htaccess` no longer serves the Vue build. That file is only the Laravel front controller; the SPA lives in the vhost root.
