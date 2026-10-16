<?php

/**
 * Read-only diagnostic. Not part of the application.
 *
 * Answers, for one transfer request and its assets: who holds the asset now,
 * what the request looks like, whether an invitation was issued for it, and
 * every audit line that touched either — in the order it was written. Nothing
 * here writes anything.
 *
 * Usage: php .freebuff/prod_read.php [request_id ...]
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$requestIds = array_slice($argv, 1);

if (! $requestIds) {
    echo "Pass one or more request ids.\n";
    exit(1);
}

$now = fn ($v) => $v === null ? '—' : (string) $v;

$people = array_merge([1], array_map('intval', $requestIds));

echo 'PEOPLE' . PHP_EOL;

foreach (DB::table('users')
    ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
    ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
    ->whereIn('users.id', [15, 16, 17, 1])
    ->orderBy('users.id')
    ->get(['users.id', 'users.email', 'users.role', 'users.status', DB::raw('employee_numbers."Full_Name" as name'), DB::raw('departments."Name" as dept')]) as $person) {
    echo '  user #' . $person->id . ' | ' . $person->email . ' | role ' . ($person->role ?? '—')
        . ' | ' . ($person->name ?? '—') . ' | dept ' . ($person->dept ?? '—') . ' | ' . ($person->status ?? '—') . PHP_EOL;
}

foreach ($requestIds as $rawId) {
    $requestId = (int) $rawId;

    echo str_repeat('=', 78) . PHP_EOL;
    echo "REQUEST #{$requestId}" . PHP_EOL;

    $request = DB::table('requests')->where('id', $requestId)->first();

    if (! $request) {
        echo "  no such request\n";
        continue;
    }

    echo '  type ' . $request->request_type
        . ' | status ' . $request->status
        . ' | requester (user_id) ' . $now($request->user_id)
        . ' | assign_to_user_id ' . $now($request->assign_to_user_id)
        . ' | asset_id ' . $now($request->asset_id)
        . ' | created ' . $now($request->created_at)
        . ' | updated ' . $now($request->updated_at) . PHP_EOL;
    echo '  note: ' . str_replace("\n", ' ', (string) $request->Note) . PHP_EOL;

    $assetIds = DB::table('request_items')->where('request_id', $requestId)->pluck('asset_id')->map(fn ($v) => (int) $v)->all();

    if (! $assetIds && $request->asset_id) {
        $assetIds = [(int) $request->asset_id];
    }

    echo '  assets: [' . implode(', ', $assetIds) . ']' . PHP_EOL;

    foreach ($assetIds as $assetId) {
        $asset = DB::table('assets')->where('id', $assetId)->first();

        if (! $asset) {
            echo "    asset #{$assetId}: missing\n";
            continue;
        }

        $holder = DB::table('users')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.id', $asset->user_id)
            ->first(['users.id', 'users.email', DB::raw('employee_numbers."Full_Name" as name')]);

        echo '    asset #' . $assetId . ' ' . $asset->Asset_code
            . ' | lifecycle ' . $asset->Lifecycle_Status
            . ' | user_id ' . $now($asset->user_id) . ' (' . ($holder->name ?? $holder->email ?? '—') . ')'
            . ' | updated ' . $now($asset->updated_at) . PHP_EOL;
    }

    echo '  transfer_reviews:' . PHP_EOL;
    $reviews = DB::table('transfer_reviews')->where('request_id', $requestId)->orderBy('id')->get();

    if ($reviews->isEmpty()) {
        echo "    (none)\n";
    }

    foreach ($reviews as $row) {
        echo '    #' . $row->id
            . ' | ' . $row->status
            . ' | to ' . $now($row->recipient_email)
            . ' | sent ' . $now($row->sent_count) . ' | last ' . $now($row->last_sent_at)
            . ' | verified ' . $now($row->verified_at)
            . ' | expires ' . $now($row->expires_at)
            . ' | revoked ' . $now($row->revoked_at)
            . ' | executed ' . $now($row->executed_at)
            . ' | decided ' . $now($row->decided_at) . PHP_EOL;
    }

    echo '  accountability (open rows first):' . PHP_EOL;
    $rows = DB::table('asset_accountability')->whereIn('asset_id', $assetIds)->orderBy('asset_id')->orderBy('id')->get();

    if ($rows->isEmpty()) {
        echo "    (none)\n";
    }

    foreach ($rows as $row) {
        echo '    #' . $row->id . ' asset ' . $row->asset_id
            . ' | user ' . $now($row->user_id)
            . ' | current ' . $now($row->Is_Current)
            . ' | date_acquired ' . $now($row->date_acquired ?? null)
            . ' | created ' . $now($row->created_at)
            . ' | updated ' . $now($row->updated_at)
            . PHP_EOL;
    }

    echo '  audit_logs (oldest first):' . PHP_EOL;
    $logs = DB::table('audit_logs')
        ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where(function ($q) use ($requestId, $assetIds) {
            $q->where('audit_logs.request_id', $requestId);

            if ($assetIds) {
                $q->orWhereIn('audit_logs.asset_id', $assetIds);
            }
        })
        ->orderBy('audit_logs.id')
        ->get(['audit_logs.id', 'audit_logs.user_id', DB::raw('employee_numbers."Full_Name" as actor_name'), 'audit_logs.request_id', 'audit_logs.asset_id', 'audit_logs.action_type', 'audit_logs.notes', 'audit_logs.action_description', 'audit_logs.created_at']);

    if ($logs->isEmpty()) {
        echo "    (none)\n";
    }

    foreach ($logs as $log) {
        echo '    #' . $log->id . ' ' . $now($log->created_at) . ' | user ' . $now($log->user_id)
            . ' | req ' . $now($log->request_id) . ' | asset ' . $now($log->asset_id)
            . ' | ' . $log->action_type . ' | ' . $log->notes
            . ' || ' . $log->action_description . PHP_EOL;
    }
}
