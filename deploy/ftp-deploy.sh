#!/bin/sh
# Assemble the release and upload it via FTPS.
# /ansetzung          built frontend (no extra folder)
# /ansetzung/api      Laravel backend
# Expects the CI build artifacts: backend/vendor (no dev deps), backend/bootstrap/cache, frontend/dist.
# The app is in maintenance mode from upload until deploy/post-deploy.php has run migrations.
set -eu
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASSWORD:?}" "${FTP_PATH:?}" "${APP_URL:?}"

root=$(cd "$(dirname "$0")/.." && pwd)
release=$(mktemp -d)
hook="deploy-$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n').php"

mkdir -p "$release/api"
for item in app artisan bootstrap composer.json config database public routes vendor; do
  cp -R "$root/backend/$item" "$release/api/"
done
# Frontend files sit in the vhost root. Copy them first so .htaccess below wins if dist ever contains one.
cp -R "$root/frontend/dist/." "$release/"
cp "$root/deploy/htaccess" "$release/.htaccess"
cp "$root/deploy/post-deploy.php" "$release/api/public/$hook"
# Storage is persistent on the server (uploaded files, logs); only missing folders get created
mkdir -p "$release/api/storage/app/private" "$release/api/storage/framework/cache/data" \
  "$release/api/storage/framework/sessions" "$release/api/storage/framework/views" "$release/api/storage/logs"
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
mkdir -p $FTP_PATH/api
set cmd:fail-exit no
mv $FTP_PATH/storage $FTP_PATH/api/storage
rm $FTP_PATH/.env
rm $FTP_PATH/.env.production
set cmd:fail-exit yes
mirror -R --only-missing --no-perms $release/api/storage $FTP_PATH/api/storage
put $down -o $FTP_PATH/api/storage/framework/down
# api/.env.production is edited on the server and is never uploaded or deleted.
mirror -R --delete --no-perms --parallel=8 --exclude '^api/storage/' --exclude '^api/\\.env\\.production$' $release $FTP_PATH
"

status=0
curl -fsS --max-time 300 "$APP_URL/api/public/$hook" || status=$?
ftp "rm $FTP_PATH/api/public/$hook"
exit $status
