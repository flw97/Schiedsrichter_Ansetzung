<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

require_once dirname(__DIR__, 2).'/scripts/extract-release.php';

class ExtractReleaseTest extends TestCase
{
    private string $root;

    private string $outside;

    /** @var list<string> */
    private array $zips = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/release-'.bin2hex(random_bytes(8));
        $this->outside = dirname($this->root).'/release-evil-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/api/storage/app', 0777, true);
        mkdir($this->root.'/api/vendor', 0777, true);
        file_put_contents($this->root.'/stale.txt', 'old');
        file_put_contents($this->root.'/gone.txt', 'gone');
        file_put_contents($this->root.'/extract.php', 'self');
        file_put_contents($this->root.'/api/storage/app/keep.txt', 'keep');
        file_put_contents($this->root.'/api/.env.production', 'SECRET');
        file_put_contents($this->root.'/api/vendor/old.php', 'old');
    }

    protected function tearDown(): void
    {
        release_rrmdir($this->root);
        if (is_file($this->outside)) {
            unlink($this->outside);
        }
        foreach ($this->zips as $zip) {
            if (is_file($zip)) {
                unlink($zip);
            }
        }
        parent::tearDown();
    }

    public function test_extract_replaces_the_release_and_keeps_persistent_files(): void
    {
        $appSha = str_repeat('a', 64);
        $lockSha = str_repeat('b', 64);

        release_apply(
            $this->root,
            'extract.php',
            $this->appZip(['gone.txt' => 'still here']),
            $this->vendorZip(),
            $appSha,
            $lockSha,
        );

        $this->assertFileDoesNotExist($this->root.'/stale.txt');
        $this->assertStringEqualsFile($this->root.'/gone.txt', 'still here');
        $this->assertStringEqualsFile($this->root.'/index.html', 'home');
        $this->assertStringEqualsFile($this->root.'/.htaccess', 'rules');
        $this->assertStringEqualsFile($this->root.'/extract.php', 'self');
        $this->assertStringEqualsFile($this->root.'/api/storage/app/keep.txt', 'keep');
        $this->assertStringEqualsFile($this->root.'/api/.env.production', 'SECRET');
        $this->assertFileDoesNotExist($this->root.'/api/vendor/old.php');
        $this->assertStringEqualsFile($this->root.'/api/vendor/new.php', 'new');
        $this->assertStringEqualsFile($this->root.'/api/vendor/autoload.php', 'autoload');
        $this->assertStringEqualsFile($this->root.'/api/app.sha', $appSha."\n");
        $this->assertStringEqualsFile($this->root.'/api/vendor.sha', $lockSha."\n");
        $this->assertFileDoesNotExist($this->root.'/api/.vendor-new');
        $this->assertFileDoesNotExist($this->root.'/api/.vendor-old');

        release_apply(
            $this->root,
            'extract.php',
            $this->appZip(),
            null,
            str_repeat('c', 64),
            null,
        );

        $this->assertFileDoesNotExist($this->root.'/gone.txt');
        $this->assertStringEqualsFile($this->root.'/api/vendor/new.php', 'new');
        $this->assertStringEqualsFile($this->root.'/api/storage/app/keep.txt', 'keep');
        $this->assertStringEqualsFile($this->root.'/api/.env.production', 'SECRET');
        $this->assertStringEqualsFile($this->root.'/api/vendor.sha', $lockSha."\n");
    }

    public function test_vendor_update_leaves_app_files_in_place(): void
    {
        file_put_contents($this->root.'/stay.txt', 'stay');

        release_apply($this->root, 'extract.php', null, $this->vendorZip(), null, str_repeat('d', 64));

        $this->assertStringEqualsFile($this->root.'/stay.txt', 'stay');
        $this->assertStringEqualsFile($this->root.'/stale.txt', 'old');
        $this->assertFileDoesNotExist($this->root.'/api/vendor/old.php');
        $this->assertStringEqualsFile($this->root.'/api/vendor/new.php', 'new');
    }

    public function test_incomplete_app_archive_is_refused(): void
    {
        $zip = $this->zip(['index.html' => 'only']);

        try {
            release_apply($this->root, 'extract.php', $zip, null, str_repeat('e', 64), null);
            $this->fail('expected an incomplete archive to be refused');
        } catch (RuntimeException $e) {
            $this->assertSame('app zip is not a full release', $e->getMessage());
        }

        $this->assertStringEqualsFile($this->root.'/stale.txt', 'old');
        $this->assertFileDoesNotExist($this->root.'/index.html');
    }

    public function test_app_archive_cannot_replace_storage_or_vendor(): void
    {
        $zip = $this->appZip(['api/storage/app/keep.txt' => 'wiped']);

        try {
            release_apply($this->root, 'extract.php', $zip, null, str_repeat('f', 64), null);
            $this->fail('expected a protected path to be refused');
        } catch (RuntimeException $e) {
            $this->assertSame('app zip contains a protected path', $e->getMessage());
        }

        $this->assertStringEqualsFile($this->root.'/api/storage/app/keep.txt', 'keep');
    }

    public function test_zip_slip_is_refused(): void
    {
        $zip = $this->appZip(['../'.basename($this->outside) => 'pwned']);

        try {
            release_apply($this->root, 'extract.php', $zip, null, str_repeat('1', 64), null);
            $this->fail('expected a zip slip entry to be refused');
        } catch (RuntimeException $e) {
            $this->assertSame('zip entry is unsafe', $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->outside);
        $this->assertStringEqualsFile($this->root.'/stale.txt', 'old');
    }

    public function test_directories_that_still_hold_files_do_not_fail_the_extract(): void
    {
        set_error_handler('release_error_handler');
        try {
            release_apply(
                $this->root,
                'extract.php',
                $this->appZip(),
                $this->vendorZip(),
                str_repeat('a', 64),
                str_repeat('b', 64),
            );
        } finally {
            restore_error_handler();
        }

        $this->assertStringEqualsFile($this->root.'/index.html', 'home');
        $this->assertStringEqualsFile($this->root.'/api/vendor/new.php', 'new');
        $this->assertStringEqualsFile($this->root.'/api/storage/app/keep.txt', 'keep');
    }

    public function test_probe_does_not_extract(): void
    {
        $_GET = ['token' => '__TOKEN__', 'probe' => '1'];
        ob_start();
        release_http();
        $body = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $this->assertSame("ok\n", $body);
        $this->assertStringEqualsFile($this->root.'/stale.txt', 'old');
        http_response_code(200);
    }

    public function test_http_rejects_a_bad_token(): void
    {
        $_GET['token'] = 'nope';
        ob_start();
        release_http();
        $body = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertSame("not found\n", $body);
        http_response_code(200);
    }

    /** @param  array<string, string>  $extra */
    private function appZip(array $extra = []): string
    {
        return $this->zip($extra + [
            'index.html' => 'home',
            '.htaccess' => 'rules',
            'api/artisan' => 'artisan',
            'api/public/index.php' => '<?php',
            'api/bootstrap/app.php' => '<?php',
        ]);
    }

    private function vendorZip(): string
    {
        return $this->zip([
            'vendor/autoload.php' => 'autoload',
            'vendor/new.php' => 'new',
        ]);
    }

    /** @param  array<string, string>  $entries */
    private function zip(array $entries): string
    {
        $path = sys_get_temp_dir().'/release-zip-'.bin2hex(random_bytes(8)).'.zip';
        $this->zips[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }
}
