<?php

// One-off hook, uploaded under a random name by deploy/ftp-deploy.sh, called once and deleted again.
// FTP gives no shell, so migrations and caches run through the web PHP.

// This file is uploaded to the vhost root under a random name, next to the frontend.
require __DIR__.'/api/vendor/autoload.php';

$kernel = (require __DIR__.'/api/bootstrap/app.php')->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');
foreach (['migrate' => ['--force' => true], 'config:cache' => [], 'route:cache' => [], 'event:cache' => [], 'up' => []] as $command => $args) {
    $status = $kernel->call($command, $args);
    echo "$ artisan $command\n", $kernel->output();
    if ($status !== 0) {
        http_response_code(500);
        exit;
    }
}
