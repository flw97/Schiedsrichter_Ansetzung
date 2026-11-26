#!/bin/sh
# Assemble the release and upload it via FTPS.
# FTP root is the vhost (SSH: /ansetzung). Frontend files go there, Laravel into api/.
#
# A per-file mirror re-uploads vendor/ on every deploy. That tree is thousands of files,
# and FTPS opens a data connection for each one, so the upload takes about 15 minutes
# and the site stays in maintenance the whole time. Two zip archives avoid that:
# vendor/ only when composer.lock changes, everything else only when those files change.
# backend/scripts/extract-release.php runs once on the server and is then deleted.
# Maintenance mode starts after the upload, for the extract and deploy/post-deploy.php.
set -eu
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASSWORD:?}" "${FTP_PATH:?}" "${APP_URL:?}"

root=$(cd "$(dirname "$0")/.." && pwd)
tmp=$(mktemp -d)
release=$(mktemp -d)
trap 'rm -rf "$tmp" "$release"' EXIT

die() {
  printf '%s\n' "$1" >&2
  exit 1
}

command -v zip >/dev/null || die "zip is not installed"
command -v lftp >/dev/null || die "lftp is not installed"
[ -f "$root/frontend/dist/index.html" ] || die "frontend/dist is missing"
[ -f "$root/backend/vendor/autoload.php" ] || die "backend/vendor is missing"
[ -f "$root/backend/artisan" ] || die "backend/artisan is missing"
[ -f "$root/backend/composer.lock" ] || die "backend/composer.lock is missing"

# vendor/ is its own archive. storage/ is persistent on the server and is not in the release.
cp -R "$root/frontend/dist/." "$release/"
mkdir -p "$release/api"
for item in app artisan bootstrap composer.json config database public routes; do
  cp -R "$root/backend/$item" "$release/api/"
done
cp "$root/deploy/htaccess" "$release/.htaccess"
[ ! -e "$release/api/vendor" ] || die "release must not contain api/vendor"
[ ! -e "$release/api/storage" ] || die "release must not contain api/storage"

tree_sha() {
  dir=$1
  (
    cd "$dir" || exit 1
    # LC_ALL=C keeps the hash stable across runners.
    find . -type f -print0 | LC_ALL=C sort -z | xargs -0 -r sha256sum -- | sha256sum | awk '{print $1; exit}'
  )
}

sha_in() {
  if [ ! -f "$1" ]; then
    return 0
  fi
  value=$(tr -d '[:space:]' < "$1" | tr 'A-F' 'a-f')
  if printf '%s' "$value" | grep -Eq '^[0-9a-f]{64}$'; then
    printf '%s\n' "$value"
  fi
}

rand() {
  head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n'
}

# Print the response body. The URL carries the one-off token, so it stays out of the log.
http_call() {
  label=$1
  timeout=$2
  url=$3
  code=$(curl -sS --max-time "$timeout" -o "$tmp/http-body" -w '%{http_code}' "$url" || true)
  if [ -s "$tmp/http-body" ]; then
    cat "$tmp/http-body"
    echo
  fi
  if [ "$code" != "200" ]; then
    echo "$label failed: HTTP $code" >&2
    return 1
  fi
}

app_sha=$(tree_sha "$release")
lock_sha=$(sha256sum "$root/backend/composer.lock" | awk '{print $1; exit}')
printf '%s' "$app_sha" | grep -Eq '^[0-9a-f]{64}$' || die "could not hash the release"
printf '%s' "$lock_sha" | grep -Eq '^[0-9a-f]{64}$' || die "could not hash composer.lock"
app_sha_file=$tmp/app.sha
lock_sha_file=$tmp/vendor.sha
: > "$app_sha_file"
: > "$lock_sha_file"

export LFTP_PASSWORD="$FTP_PASSWORD"

lftp_run() {
  # Settings live in the command file so a failed get does not print the password.
  lftp --env-password -u "$FTP_USER" "$FTP_HOST" < "$1"
}

base=$tmp/lftp-base
cat > "$base" <<EOF
set cmd:fail-exit yes
set ftp:ssl-force yes
set ftp:ssl-protect-data yes
set ssl:verify-certificate yes
# Certificate is *.netcup.net; the account lives on hosting….ae92d.netcup.net.
set ssl:check-hostname no
set net:max-retries 3
EOF

read_cmds=$tmp/lftp-read
cat "$base" > "$read_cmds"
# -o is only valid after the remote name. A missing stamp is not a failed connection.
cat >> "$read_cmds" <<EOF
set cmd:fail-exit no
get $FTP_PATH/api/app.sha -o $app_sha_file || pwd
get $FTP_PATH/api/vendor.sha -o $lock_sha_file || pwd
EOF
if ! lftp_run "$read_cmds"; then
  echo "Could not read deploy stamps; uploading the full release." >&2
fi

remote_app=$(sha_in "$app_sha_file")
remote_lock=$(sha_in "$lock_sha_file")
app_zip=
vendor_zip=
if [ "$remote_app" != "$app_sha" ]; then
  app_zip=.upload-app.zip
  (cd "$release" && zip -qr "$tmp/$app_zip" .)
  echo "Uploading app ($(du -h "$tmp/$app_zip" | awk '{print $1; exit}'))"
else
  echo "App unchanged"
fi
if [ "$remote_lock" != "$lock_sha" ]; then
  vendor_zip=.upload-vendor.zip
  (cd "$root/backend" && zip -qr "$tmp/$vendor_zip" vendor)
  echo "Uploading vendor ($(du -h "$tmp/$vendor_zip" | awk '{print $1; exit}'))"
else
  echo "Dependencies unchanged"
fi

token=$(rand)
hook="deploy-$(rand).php"
extract=
if [ -n "$app_zip" ] || [ -n "$vendor_zip" ]; then
  extract="extract-$(rand).php"
  sed \
    -e "s|__TOKEN__|$token|" \
    -e "s|__APP_SHA__|$app_sha|" \
    -e "s|__LOCK_SHA__|$lock_sha|" \
    -e "s|__APP_ZIP__|$app_zip|" \
    -e "s|__VENDOR_ZIP__|$vendor_zip|" \
    "$root/backend/scripts/extract-release.php" > "$tmp/extract.php"
  if grep -Eq '__TOKEN__|__APP_SHA__|__LOCK_SHA__|__APP_ZIP__|__VENDOR_ZIP__' "$tmp/extract.php"; then
    die "extractor placeholders were not filled"
  fi
fi

down=$tmp/down.json
printf '%s\n' '{"status":503}' > "$down"

upload=$tmp/lftp-upload
cat "$base" > "$upload"
# mkdir fails when the directory already exists. A missed directory fails the later put
# of the maintenance file, which is after the archives, so the current site stays up.
cat >> "$upload" <<EOF
set cmd:fail-exit no
mkdir -p $FTP_PATH/api/storage/app/private
mkdir -p $FTP_PATH/api/storage/framework/cache/data
mkdir -p $FTP_PATH/api/storage/framework/sessions
mkdir -p $FTP_PATH/api/storage/framework/views
mkdir -p $FTP_PATH/api/storage/logs
set cmd:fail-exit yes
EOF
if [ -n "$app_zip" ]; then
  printf 'put %s -o %s/api/%s\n' "$tmp/$app_zip" "$FTP_PATH" "$app_zip" >> "$upload"
fi
if [ -n "$vendor_zip" ]; then
  printf 'put %s -o %s/api/%s\n' "$tmp/$vendor_zip" "$FTP_PATH" "$vendor_zip" >> "$upload"
fi
if [ -n "$extract" ]; then
  printf 'put %s -o %s/%s\n' "$tmp/extract.php" "$FTP_PATH" "$extract" >> "$upload"
fi

status=0
lftp_run "$upload" || status=$?
# Probe before maintenance mode: a missing zip extension or a bad URL must leave the site up.
if [ "$status" -eq 0 ] && [ -n "$extract" ]; then
  http_call "Extract probe" 60 "$APP_URL/$extract?token=$token&probe=1" || status=$?
fi
if [ "$status" -eq 0 ]; then
  down_cmds=$tmp/lftp-down
  cat "$base" > "$down_cmds"
  printf 'put %s -o %s/api/storage/framework/down\n' "$down" "$FTP_PATH" >> "$down_cmds"
  lftp_run "$down_cmds" || status=$?
fi
if [ "$status" -eq 0 ] && [ -n "$extract" ]; then
  http_call "Extract" 300 "$APP_URL/$extract?token=$token" || status=$?
fi
if [ "$status" -eq 0 ]; then
  hook_cmds=$tmp/lftp-hook
  cat "$base" > "$hook_cmds"
  printf 'put %s -o %s/%s\n' "$root/deploy/post-deploy.php" "$FTP_PATH" "$hook" >> "$hook_cmds"
  lftp_run "$hook_cmds" || status=$?
fi
if [ "$status" -eq 0 ]; then
  http_call "Deploy hook" 300 "$APP_URL/$hook" || status=$?
fi

cleanup=$tmp/lftp-cleanup
cat "$base" > "$cleanup"
printf 'set cmd:fail-exit no\n' >> "$cleanup"
printf 'rm -f %s/%s\n' "$FTP_PATH" "$hook" >> "$cleanup"
if [ -n "$extract" ]; then
  printf 'rm -f %s/%s\n' "$FTP_PATH" "$extract" >> "$cleanup"
fi
if [ -n "$app_zip" ]; then
  printf 'rm -f %s/api/%s\n' "$FTP_PATH" "$app_zip" >> "$cleanup"
fi
if [ -n "$vendor_zip" ]; then
  printf 'rm -f %s/api/%s\n' "$FTP_PATH" "$vendor_zip" >> "$cleanup"
fi
lftp_run "$cleanup" || true

exit "$status"
