<?php

/**
 * Throwaway walkthrough check. Not part of the application.
 *
 * Prints what the walkthrough decision actually wrote: the review row, the
 * assets' holders, the accountability history, the notifications and the audit
 * trail of a request.
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=$PWD/database/replay.sqlite \
 *   php .freebuff/replay_check.php [request_id]
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$requestId = (int) ($argv[1] ?? 0);

echo 'reviews:' . PHP_EOL;

foreach (DB::table('transfer_reviews')->orderBy('id')->get() as $row) {
    echo '  #' . $row->id . ' req ' . $row->request_id
        . ' | ' . $row->status
        . ' | decision ' . ($row->decision ?? '—')
        . ' | approved ' . $row->approved_count . ' declined ' . $row->declined_count
        . ' | executed ' . ($row->executed_at ?? '—')
        . PHP_EOL . '      handover as issued: ' . ($row->handover ?? '—') . PHP_EOL;
}

echo 'assets:' . PHP_EOL;

$names = [];

foreach (DB::table('users')
    ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
    ->get(['users.id', DB::raw('employee_numbers."Full_Name" as full_name')]) as $person) {
    $names[(int) $person->id] = $person->full_name ?? ('user #' . $person->id);
}

foreach (DB::table('assets')->orderBy('id')->get() as $asset) {
    echo '  ' . $asset->Asset_code . ' → ' . ($names[$asset->user_id] ?? $asset->user_id) . PHP_EOL;
}

echo 'accountability (' . DB::table('asset_accountability')->count() . ' rows):' . PHP_EOL;

foreach (DB::table('asset_accountability')->orderBy('id')->get() as $row) {
    echo '  asset ' . $row->asset_id . ' | user ' . ($names[$row->user_id] ?? $row->user_id)
        . ' | current ' . $row->Is_Current
        . ' | request ' . ($row->request_id ?? '—')
        . ' | ' . ($row->transfer_reason ?? '—') . PHP_EOL;
}

echo 'notifications:' . PHP_EOL;

foreach (DB::table('notifications')->orderBy('id')->get() as $note) {
    echo '  to ' . ($names[$note->user_id] ?? $note->user_id) . ' | ' . $note->title . PHP_EOL
        . '      ' . $note->message . PHP_EOL;
}

if ($requestId > 0) {
    echo 'audit for request #' . $requestId . ':' . PHP_EOL;

    foreach (DB::table('audit_logs')->where('request_id', $requestId)->orderBy('id')->get() as $log) {
        echo '  ' . $log->action_type . ' | ' . $log->notes . PHP_EOL . '      ' . $log->action_description . PHP_EOL;
    }
}
