<?php

/**
 * Throwaway walkthrough database. Not part of the application.
 *
 * Replays what the live system actually holds, so the review page can be walked
 * through in a browser instead of reasoned about:
 *
 *   · "Demo name" is a Department Head. Kevin Andrew and Ada Reyes are employees
 *     of the same department, and both assets are Kevin's — exactly as they are on
 *     the live system, where the head requested a transfer of an asset the head
 *     never held, to the employee who already had it.
 *
 *   · Request #1 is the live case: Kevin's laptop, asked to be transferred to
 *     Kevin. Nothing to move; the review must still be decidable.
 *   · Request #2 is the ordinary case: Kevin's other asset, asked to be
 *     transferred to Ada. It must really change hands.
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=$PWD/database/replay.sqlite \
 *   MAIL_MAILER=log php .freebuff/replay_seed.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

foreach ([
    'transfer_review_items', 'transfer_reviews', 'request_items', 'requests',
    'asset_accountability', 'audit_logs', 'notifications', 'assets', 'users',
    'employee_numbers', 'departments',
] as $table) {
    DB::table($table)->delete();
}

DB::table('departments')->insert([
    ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
]);

DB::table('employee_numbers')->insert([
    ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Demo name', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Kevin Andrew', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Ada Reyes', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Sir alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
]);

foreach ([
    ['employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'demo@gmail.com', 'role' => 'Department Head'],
    ['employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'kevinandrewperez@gmail.com', 'role' => 'Employee'],
    ['employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'ada@gmail.com', 'role' => 'Employee'],
    ['employee_numbers_id' => 4, 'department_id' => 1, 'email' => 'alex@nu-lipa.edu.ph', 'role' => 'Admin'],
] as $user) {
    DB::table('users')->insert($user + [
        'password'   => Hash::make('preview-password'),
        'status'     => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$head    = (int) DB::table('users')->where('email', 'demo@gmail.com')->value('id');
$kevin   = (int) DB::table('users')->where('email', 'kevinandrewperez@gmail.com')->value('id');
$ada     = (int) DB::table('users')->where('email', 'ada@gmail.com')->value('id');
$alex    = (int) DB::table('users')->where('email', 'alex@nu-lipa.edu.ph')->value('id');

$laptop    = (int) DB::table('assets')->insertGetId([
    'user_id' => $kevin, 'Asset_code' => 'AST-LPZY6AN-OATT-02', 'Asset_name' => 'Laptop',
    'Category' => 'General and Office Equipment', 'Condition' => 'New', 'serial_Number' => 'SN-908879731',
    'Lifecycle_Status' => 'Active', 'asset_location' => 'Faculty Room',
    'created_at' => now(), 'updated_at' => now(),
]);

$projector = (int) DB::table('assets')->insertGetId([
    'user_id' => $kevin, 'Asset_code' => 'AST-J53H5EV-I92M-08', 'Asset_name' => 'Demo asset',
    'Category' => 'General and Office Equipment', 'Condition' => 'Good', 'serial_Number' => 'SN-0000082',
    'Lifecycle_Status' => 'Active', 'asset_location' => 'Faculty Room',
    'created_at' => now(), 'updated_at' => now(),
]);

/* The live case: Kevin's laptop, requested for transfer to Kevin. */
$toKevin = (int) DB::table('requests')->insertGetId([
    'user_id' => $head, 'asset_id' => null, 'request_type' => 'Transfer', 'status' => 'Pending',
    'Note' => 'sadsagdfggsdfgrvcsdfsfedfdfd', 'assign_to_user_id' => $kevin,
    'created_at' => now(), 'updated_at' => now(),
]);

DB::table('request_items')->insert(['request_id' => $toKevin, 'asset_id' => $laptop, 'created_at' => now(), 'updated_at' => now()]);

/* The ordinary case: Kevin's other asset, requested for Ada. */
$toAda = (int) DB::table('requests')->insertGetId([
    'user_id' => $head, 'asset_id' => null, 'request_type' => 'Transfer', 'status' => 'Pending',
    'Note' => 'The projector should follow the reshuffle.', 'assign_to_user_id' => $ada,
    'created_at' => now(), 'updated_at' => now(),
]);

DB::table('request_items')->insert(['request_id' => $toAda, 'asset_id' => $projector, 'created_at' => now(), 'updated_at' => now()]);

echo "Seeded.\n";
echo "  head (Demo name) #{$head} · Kevin #{$kevin} · Ada #{$ada} · office (Sir alex) #{$alex}\n";
echo "  request #{$toKevin}: laptop {$laptop} → Kevin (already his: nothing to move)\n";
echo "  request #{$toAda}: asset {$projector} → Ada (must really move)\n";
echo "  office: alex@nu-lipa.edu.ph / preview-password\n";
