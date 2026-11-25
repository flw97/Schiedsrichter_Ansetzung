#!/bin/sh
# Assemble the release (Laravel backend + built frontend in public/) and upload it via FTPS.
# Expects the CI build artifacts: backend/vendor (no dev deps), backend/bootstrap/cache, frontend/dist.
# The app is in maintenance mode from upload until deploy/post-deploy.php has run migrations.
set -eu
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASSWORD:?}" "${FTP_PATH:?}" "${APP_URL:?}"
: "${PRODUCTION_ENV:?file variable with the production backend .env}"

root=$(cd "$(dirname "$0")/.." && pwd)
release=$(mktemp -d)
hook="deploy-$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n').php"

for item in app artisan bootstrap composer.json config database public routes vendor; do
  cp -R "$root/backend/$item" "$release/"
done
cp -R "$root/frontend/dist/." "$release/public/"
cp "$root/deploy/htaccess" "$release/.htaccess"
cp "$root/deploy/post-deploy.php" "$release/public/$hook"
# Storage is persistent on the server (uploaded files, logs); only missing folders get created
mkdir -p "$release/storage/app/private" "$release/storage/framework/cache/data" \
  "$release/storage/framework/sessions" "$release/storage/framework/views" "$release/storage/logs"
down=$(mktemp)
echo '{"status":503}' > "$down"

export LFTP_PASSWORD="$FTP_PASSWORD"
ftp() {
  lftp --env-password -u "$FTP_USER" "$FTP_HOST" <<EOF
set cmd:fail-exit yes
set ftp:ssl-force yes
set ftp:ssl-protect-data yes
set net:max-retries 3
$1
EOF
}

ftp "
mirror -R --only-missing --no-perms $release/storage $FTP_PATH/storage
put $down -o $FTP_PATH/storage/framework/down
put $PRODUCTION_ENV -o $FTP_PATH/.env
mirror -R --delete --no-perms --parallel=8 --exclude '^storage/' --exclude '^\.env\$' $release $FTP_PATH
"

status=0
curl -fsS --max-time 300 "$APP_URL/$hook" || status=$?
ftp "rm $FTP_PATH/public/$hook"
exit $status
