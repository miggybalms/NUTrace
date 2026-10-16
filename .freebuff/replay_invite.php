<?php

/**
 * Throwaway walkthrough step. Not part of the application.
 *
 * Marks the seeded transfer requests approved and issues the review invitations
 * through the same call the office's approval route makes, so the browser
 * walkthrough starts where the live system is: an outstanding invitation.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Support\TransferReviews;
use Illuminate\Support\Facades\DB;

$office = User::where('email', 'alex@nu-lipa.edu.ph')->first();

foreach (DB::table('requests')->where('request_type', 'Transfer')->orderBy('id')->pluck('id') as $requestId) {
    DB::table('requests')->where('id', $requestId)->update(['status' => 'Approved', 'updated_at' => now()]);

    $result = TransferReviews::invite((int) $requestId, $office, false, 'miggybalmes9@gmail.com');

    echo 'request #' . $requestId . ': ' . ($result['ok'] ? 'invitation sent' : 'REFUSED') . ' — ' . $result['message'] . PHP_EOL;
}

echo 'tokens are in storage/logs/laravel.log' . PHP_EOL;
