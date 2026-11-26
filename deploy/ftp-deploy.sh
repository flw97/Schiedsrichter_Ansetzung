#!/bin/sh
# Assemble the release and upload it via FTPS.
# FTP root is the vhost (SSH: /ansetzung). Frontend files go there, Laravel into api/.
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
# Site root, so the hook is https://<host>/<random>.php and does not depend on the /api rewrite.
cp "$root/deploy/post-deploy.php" "$release/$hook"
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
set ssl:verify-certificate yes
# Certificate is *.netcup.net; the account lives on hosting….ae92d.netcup.net.
set ssl:check-hostname no
set net:max-retries 3
$1
EOF
}

ftp "
mkdir -p $FTP_PATH/api
mirror -R --only-missing --no-perms $release/api/storage $FTP_PATH/api/storage
put $down -o $FTP_PATH/api/storage/framework/down
# api/.env.production is edited on the server and is never uploaded or deleted.
mirror -R --delete --no-perms --parallel=8 --exclude '^api/storage/' --exclude '^api/\\.env\\.production$' $release $FTP_PATH
"

status=0
curl -fsS --max-time 300 "$APP_URL/$hook" || status=$?
ftp "rm $FTP_PATH/$hook"
exit $status
