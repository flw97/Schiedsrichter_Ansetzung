# Added
- Laravel 13 backend in `backend/` (API only: no Blade views, no Vite/Node assets)
  - `GET /api/health` in `routes/api.php`, also checks the DB connection; `bootstrap/app.php` registers `api` routing instead of `web`
  - Feature test `tests/Feature/ExampleTest.php` covers `/api/health`
  - `.env.development` configures MariaDB (`DB_CONNECTION=mariadb`, host `db`), German locale and stderr logging; `.env.testing` holds a test-only `APP_KEY`
- `db` service in `docker-compose.yaml`: `mariadb:11.8` on port 3306 with healthcheck and `db_data` volume; `backend` waits for it to be healthy

# Changed
- Replaces the plain PHP front controller `backend/public/index.php` from `2026-11-25--18-55--docker-dev-environment.md` with Laravel's
- `backend/Dockerfile` installs `pdo_mysql`, `unzip` and Composer and runs `composer install`
- `backend` compose service runs `composer install && php artisan migrate --force` before Apache, keeps `vendor/` in the `backend_vendor` volume and runs Apache as the host user (`APACHE_RUN_USER: "#${UID:-1000}"`) so `storage/` is writable
- `phpunit.xml` uses `<server … force="true">` instead of `<env>`: Laravel reads `$_SERVER` first, so container env vars (`DB_CONNECTION=mariadb`) would otherwise make tests run against the dev database
