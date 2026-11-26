<?php

// Uploaded under a random name by deploy/ftp-deploy.sh, called once, then deleted.
// Replaces a per-file FTPS mirror: vendor/ is thousands of files and each one costs a round trip.
// Not part of the Laravel app. The test suite loads these functions directly.

declare(strict_types=1);

const RELEASE_TOKEN = '__TOKEN__';
const RELEASE_APP_SHA = '__APP_SHA__';
const RELEASE_LOCK_SHA = '__LOCK_SHA__';
const RELEASE_APP_ZIP = '__APP_ZIP__';
const RELEASE_VENDOR_ZIP = '__VENDOR_ZIP__';

function release_normalize(string $name): string
{
    $name = str_replace('\\', '/', $name);
    if (str_starts_with($name, '/')) {
        throw new RuntimeException('zip entry is absolute');
    }
    while (str_starts_with($name, './')) {
        $name = substr($name, 2);
    }
    if ($name === '' || str_ends_with($name, '/')) {
        return $name;
    }
    foreach (explode('/', $name) as $part) {
        if ($part === '' || $part === '..') {
            throw new RuntimeException('zip entry is unsafe');
        }
    }

    return $name;
}

/** @return array<string, true> */
function release_entries(ZipArchive $zip): array
{
    $expected = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $raw = $zip->getNameIndex($i);
        if ($raw === false) {
            throw new RuntimeException('zip entry failed');
        }
        $name = release_normalize($raw);
        if ($name === '' || str_ends_with($name, '/')) {
            continue;
        }
        $expected[$name] = true;
    }

    return $expected;
}

function release_open(string $path): ZipArchive
{
    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException('PHP zip extension is not enabled');
    }
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        throw new RuntimeException('zip open failed');
    }

    return $zip;
}

/** @param  array<string, true>  $expected */
function release_assert_app_entries(array $expected): void
{
    foreach (['index.html', '.htaccess', 'api/artisan', 'api/public/index.php', 'api/bootstrap/app.php'] as $required) {
        if (! isset($expected[$required])) {
            throw new RuntimeException('app zip is not a full release');
        }
    }
    foreach (array_keys($expected) as $name) {
        $protected = $name === 'api/vendor'
            || str_starts_with($name, 'api/vendor/')
            || $name === 'api/storage'
            || str_starts_with($name, 'api/storage/')
            || $name === 'api/.env.production'
            || $name === 'api/app.sha'
            || $name === 'api/vendor.sha';
        if ($protected) {
            throw new RuntimeException('app zip contains a protected path');
        }
    }
}

function release_rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        if ($item->isLink() || ! $item->isDir()) {
            unlink($item->getPathname());
        } else {
            rmdir($item->getPathname());
        }
    }
    rmdir($dir);
}

function release_replace_vendor(string $root, string $zipPath): void
{
    $dest = $root.'/api/vendor';
    $backup = $root.'/api/.vendor-old';
    if (! is_dir($dest) && is_dir($backup)) {
        rename($backup, $dest);
    }

    $zip = release_open($zipPath);
    $staging = $root.'/api/.vendor-new';
    try {
        $entries = release_entries($zip);
        if (! isset($entries['vendor/autoload.php'])) {
            throw new RuntimeException('vendor zip is missing vendor/autoload.php');
        }
        release_rrmdir($staging);
        if (! mkdir($staging, 0755, true) && ! is_dir($staging)) {
            throw new RuntimeException('vendor staging failed');
        }
        if (! $zip->extractTo($staging)) {
            throw new RuntimeException('vendor zip extract failed');
        }
    } finally {
        $zip->close();
    }

    if (! is_dir($staging.'/vendor')) {
        release_rrmdir($staging);
        throw new RuntimeException('vendor zip missing vendor/');
    }

    release_rrmdir($backup);
    if (is_dir($dest) && ! rename($dest, $backup)) {
        release_rrmdir($staging);
        throw new RuntimeException('vendor backup failed');
    }
    if (! rename($staging.'/vendor', $dest)) {
        if (is_dir($backup) && ! is_dir($dest)) {
            rename($backup, $dest);
        }
        release_rrmdir($staging);
        throw new RuntimeException('vendor swap failed');
    }
    release_rrmdir($backup);
    release_rrmdir($staging);
}

function release_extract_app(string $root, string $zipPath): array
{
    $zip = release_open($zipPath);
    try {
        $expected = release_entries($zip);
        release_assert_app_entries($expected);
        if (! $zip->extractTo($root)) {
            throw new RuntimeException('app zip extract failed');
        }
    } finally {
        $zip->close();
    }

    return $expected;
}

function release_keep(string $rel, string $self): bool
{
    if ($rel === $self || $rel === 'api/.env.production' || $rel === 'api/app.sha' || $rel === 'api/vendor.sha') {
        return true;
    }

    return $rel === 'api/storage'
        || str_starts_with($rel, 'api/storage/')
        || $rel === 'api/vendor'
        || str_starts_with($rel, 'api/vendor/');
}

/** @param  array<string, true>  $expected */
function release_prune(string $root, array $expected, string $self): void
{
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $rel = substr($item->getPathname(), strlen($root) + 1);
        $rel = str_replace('\\', '/', $rel);
        if (release_keep($rel, $self)) {
            continue;
        }
        if ($item->isLink() || ! $item->isDir()) {
            if (! isset($expected[$rel])) {
                unlink($item->getPathname());
            }

            continue;
        }
        // Expected files still live here. rmdir then warns "Directory not empty";
        // that warning is not a failed deploy.
        @rmdir($item->getPathname());
    }
}

function release_error_handler(int $severity, string $message): bool
{
    // The @ operator sets error_reporting to 0 for the call. Those warnings are deliberate.
    if (error_reporting() === 0 || ($severity & error_reporting()) === 0) {
        return true;
    }
    throw new RuntimeException($message);
}

function release_write_sha(string $path, ?string $sha): void
{
    if ($sha === null || preg_match('/^[0-9a-f]{64}$/', $sha) !== 1) {
        throw new RuntimeException('stamp is not a sha256');
    }
    $dir = dirname($path);
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        throw new RuntimeException('stamp directory missing');
    }
    if (file_put_contents($path, $sha."\n", LOCK_EX) === false) {
        throw new RuntimeException('stamp write failed');
    }
}

/**
 * @param  ?string  $appSha  stamp for the app archive; required when $appZip is set
 * @param  ?string  $lockSha  composer.lock stamp; required when $vendorZip is set
 */
function release_apply(string $root, string $self, ?string $appZip, ?string $vendorZip, ?string $appSha, ?string $lockSha): void
{
    if ($vendorZip !== null) {
        if (! is_file($vendorZip)) {
            throw new RuntimeException('vendor zip missing');
        }
        release_replace_vendor($root, $vendorZip);
        @unlink($vendorZip);
        // Stamp before the app extract. A later failure must not upload vendor/ again.
        release_write_sha($root.'/api/vendor.sha', $lockSha);
    }

    $expected = null;
    if ($appZip !== null) {
        if (! is_file($appZip)) {
            throw new RuntimeException('app zip missing');
        }
        $expected = release_extract_app($root, $appZip);
        @unlink($appZip);
        release_prune($root, $expected, $self);
        release_write_sha($root.'/api/app.sha', $appSha);
    }
}

function release_zip_path(string $name): ?string
{
    if ($name === '') {
        return null;
    }
    if (str_contains($name, '/') || str_contains($name, '\\')) {
        throw new RuntimeException('zip name must be a file name');
    }
    release_normalize($name);

    return __DIR__.'/api/'.$name;
}

function release_http(): void
{
    if (! headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    $token = $_GET['token'] ?? '';
    if (! is_string($token) || ! hash_equals(RELEASE_TOKEN, $token)) {
        http_response_code(404);
        echo "not found\n";

        return;
    }

    // Called before maintenance mode, so a host without ext-zip never takes the site down.
    if (isset($_GET['probe'])) {
        if (! class_exists(ZipArchive::class)) {
            http_response_code(500);
            echo "PHP zip extension is not enabled\n";

            return;
        }
        http_response_code(200);
        echo "ok\n";

        return;
    }

    set_time_limit(300);
    ignore_user_abort(true);
    ini_set('display_errors', '0');
    set_error_handler('release_error_handler');

    try {
        release_apply(
            __DIR__,
            basename(__FILE__),
            release_zip_path(RELEASE_APP_ZIP),
            release_zip_path(RELEASE_VENDOR_ZIP),
            RELEASE_APP_SHA !== '' ? RELEASE_APP_SHA : null,
            RELEASE_LOCK_SHA !== '' ? RELEASE_LOCK_SHA : null,
        );
    } catch (Throwable $e) {
        http_response_code(500);
        echo $e->getMessage(), "\n";

        return;
    } finally {
        restore_error_handler();
    }
    http_response_code(200);
    echo "ok\n";
}

if (PHP_SAPI !== 'cli') {
    release_http();
}
