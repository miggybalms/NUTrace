<?php

// Temporary diagnostic: inspects the asset_files rows behind the assets that
// show "No image" on the live site, and tests whether their stored URLs
// actually resolve. Run from the project root, then deleted:
//   php storage/tmp-asset-files-check.php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$rows = DB::table('asset_files')
    ->join('assets', 'asset_files.Asset_id', '=', 'assets.id')
    ->leftJoin('users', 'assets.user_id', '=', 'users.id')
    ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
    ->where('users.department_id', function ($q) {
        $q->select('id')->from('departments')->where('Name', 'IT Department')->limit(1);
    })
    ->orderByDesc('asset_files.Asset_file_ID')
    ->limit(12)
    ->get([
        'assets.id as asset_id',
        'assets.Asset_code',
        'assets.Asset_name',
        'employee_numbers.Full_Name as owner',
        'asset_files.Asset_file_ID',
        'asset_files.file_name',
        'asset_files.file_path',
        'asset_files.url',
    ]);

echo 'recent asset_files rows for IT Department: '.$rows->count().PHP_EOL.PHP_EOL;

$disk = Storage::disk('public');

foreach ($rows as $row) {
    echo "asset #{$row->asset_id} [{$row->Asset_code}] {$row->Asset_name} (owner: ".($row->owner ?? '?').')'.PHP_EOL;
    echo '  file_name: '.var_export($row->file_name, true).PHP_EOL;
    echo '  file_path: '.var_export($row->file_path, true).PHP_EOL;
    echo '  url:       '.var_export($row->url, true).PHP_EOL;

    $url = $row->url;

    if (is_string($url) && str_starts_with($url, 'http')) {
        $headers = @get_headers($url, true);
        $status = is_array($headers) ? ($headers[0] ?? '') : '';
        echo '  url HEAD:  '.$status.PHP_EOL;
    } else {
        echo '  url HEAD:  (not an absolute URL)'.PHP_EOL;
    }

    if (is_string($row->file_path) && $row->file_path !== '') {
        try {
            echo '  disk->exists(file_path): '.($disk->exists($row->file_path) ? 'yes' : 'NO').PHP_EOL;
        } catch (Throwable $e) {
            echo '  disk->exists threw: '.$e->getMessage().PHP_EOL;
        }
    }

    echo PHP_EOL;
}

// Also check the HTTP status of every distinct URL pattern present
$urls = DB::table('asset_files')->whereNotNull('url')->orderByDesc('Asset_file_ID')->limit(25)->pluck('url');

echo '---- HTTP status of the 25 most recent stored URLs ----'.PHP_EOL;

foreach ($urls as $u) {
    $headers = @get_headers($u, true);
    $status = is_array($headers) ? ($headers[0] ?? '?') : 'request failed';
    echo substr($status, 0, 40).'  '.$u.PHP_EOL;
}
