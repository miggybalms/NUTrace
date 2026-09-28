<?php

// Temporary diagnostic: verifies a real upload -> URL -> public read -> delete
// round-trip against the configured Supabase bucket. Run from the project root:
//   php storage/tmp-supabase-check.php
// Deleted again once the check has passed.

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('public');

echo 'driver: '.config('filesystems.disks.public.driver').PHP_EOL;
echo 'bucket: '.config('filesystems.disks.public.bucket').PHP_EOL;
echo 'endpoint: '.config('filesystems.disks.public.endpoint').PHP_EOL;

$path = '_write_test/ping-'.time().'.txt';

try {
    $ok = $disk->put($path, 'nutrace-write-test');
    echo 'put: '.($ok ? 'OK' : 'FALSE (no exception, but put returned false)').PHP_EOL;
} catch (Throwable $e) {
    echo 'put FAILED: '.get_class($e).' :: '.$e->getMessage().PHP_EOL;
    if ($e->getPrevious()) {
        echo '  previous: '.$e->getPrevious()->getMessage().PHP_EOL;
    }
    exit(1);
}

try {
    echo 'exists: '.($disk->exists($path) ? 'yes' : 'no').PHP_EOL;
    $url = $disk->url($path);
    echo 'url: '.$url.PHP_EOL;

    $public = @file_get_contents($url);
    echo 'public GET: '.($public === 'nutrace-write-test'
        ? 'OK (object readable anonymously)'
        : 'FAILED (got: '.var_export(substr((string) $public, 0, 120), true).')').PHP_EOL;

    $disk->delete($path);
    echo 'cleanup: '.($disk->exists($path) ? 'STILL THERE' : 'deleted').PHP_EOL;
} catch (Throwable $e) {
    echo 'check FAILED: '.get_class($e).' :: '.$e->getMessage().PHP_EOL;
    exit(1);
}
