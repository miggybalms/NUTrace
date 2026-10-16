<?php

/**
 * Throwaway check of the transfer hold, against the walkthrough database.
 * Boots the real application code and asks the real question — no HTTP, no
 * fabricated assertions. Delete when done.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\AssetTransfer;
use App\Support\TransferReviews;
use Illuminate\Support\Facades\DB;

$pending = DB::table('requests')->where('status', 'Pending')->orderBy('id')->first();
$assets  = DB::table('request_items')->where('request_id', $pending->id)->pluck('asset_id')->all();

$admin = DB::table('users')->where('role', 'Admin')->first();
$adminModel = App\Models\User::find($admin->id);

echo "Forwarding request #{$pending->id} for review…\n";

// The same call the office's approval makes, with the same guard in front of it.
$request = DB::table('requests')->where('id', $pending->id)->update(['status' => 'Approved']);
$invitation = TransferReviews::invite((int) $pending->id, $adminModel, false, 'dr.villanueva@nu-lipa.edu.ph');

echo '  invitation: ' . ($invitation['ok'] ? 'sent' : 'refused') . ' — ' . $invitation['message'] . PHP_EOL;

$held = TransferReviews::assetsOnHold($assets);

echo '  assets on hold: ' . count($held) . ' of ' . count($assets) . PHP_EOL;
echo '  refusal the office would read: ' . TransferReviews::holdRefusal($held) . PHP_EOL;

try {
    AssetTransfer::create(
        (int) $pending->user_id,
        (int) DB::table('users')->where('id', '!=', $pending->user_id)->where('role', '!=', 'Admin')->value('id'),
        $assets,
        'Employee Relocation',
        null,
        (int) $admin->id
    );

    echo "  THE TRANSFER PAGE MOVED THEM ANYWAY — BUG\n";
} catch (RuntimeException $e) {
    echo '  transfer page refused: ' . $e->getMessage() . PHP_EOL;
}

echo '  assets still held by: ' . DB::table('assets')->whereIn('id', $assets)->pluck('user_id')->implode(', ') . PHP_EOL;

// Now the decision itself: the same execution the Executive Director's
// approval runs. The handover must reach the accountability history.
$applied = AssetTransfer::applyRequestTransfer(
    DB::table('requests')->where('id', $pending->id)->first(),
    $assets,
    null,
    'Executive Director approval via review invitation'
);

echo "  the decision moved {$applied} asset(s)" . PHP_EOL;

echo '  accountability history now:' . PHP_EOL;

foreach (DB::table('asset_accountability')->orderBy('id')->get() as $row) {
    echo '    asset #' . $row->asset_id . ' user ' . $row->user_id
        . ' | current ' . $row->Is_Current
        . ' | request ' . $row->request_id
        . ' | ' . $row->transfer_reason . PHP_EOL;
}

echo '  assets now held by: ' . DB::table('assets')->whereIn('id', $assets)->pluck('user_id')->implode(', ') . PHP_EOL;

echo '  receiving employee notified: ' . DB::table('notifications')->where('title', 'Assets Transferred To You')->count() . PHP_EOL;

echo '  still on hold: ' . count(TransferReviews::assetsOnHold($assets)) . PHP_EOL;
