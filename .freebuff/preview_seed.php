<?php

/**
 * A throwaway database for walking through the Requests page in a browser.
 *
 * It exists so the walkthrough can run against SQLite with MAIL_MAILER=log
 * instead of the institution's live Supabase database and its real mailbox.
 * Nothing here is part of the application; delete the file when done.
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=$PWD/database/preview.sqlite \
 *   MAIL_MAILER=log php .freebuff/preview_seed.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

DB::table('transfer_reviews')->delete();
DB::table('transfer_review_items')->delete();
DB::table('request_items')->delete();
DB::table('requests')->delete();
DB::table('assets')->delete();
DB::table('users')->delete();
DB::table('employee_numbers')->delete();
DB::table('departments')->delete();

DB::table('departments')->insert([
    ['id' => 1, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
    ['id' => 2, 'Name' => 'Accountancy', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
]);

DB::table('employee_numbers')->insert([
    ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Nena Santos', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Rico Bautista', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Lito Uy (Asset Management Officer)', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
]);

$users = [
    ['employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph', 'role' => 'Admin'],
    ['employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'nena@nu-lipa.edu.ph', 'role' => 'Employee'],
    ['employee_numbers_id' => 2, 'department_id' => 2, 'email' => 'rico@nu-lipa.edu.ph', 'role' => 'Employee'],
];

foreach ($users as $user) {
    DB::table('users')->insert($user + [
        'password'   => Hash::make('preview-password'),
        'status'     => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$nena = (int) DB::table('users')->where('email', 'nena@nu-lipa.edu.ph')->value('id');
$rico = (int) DB::table('users')->where('email', 'rico@nu-lipa.edu.ph')->value('id');

$assets = [
    ['Asset_code' => 'AST-0001', 'Asset_name' => 'Dell Laptop', 'Category' => 'Info and Equipment', 'Condition' => 'Good', 'serial_Number' => 'SN-AST-0001'],
    ['Asset_code' => 'AST-0002', 'Asset_name' => 'Epson Projector', 'Category' => 'Info and Equipment', 'Condition' => 'Good', 'serial_Number' => 'SN-AST-0002'],
];

foreach ($assets as $asset) {
    DB::table('assets')->insert($asset + [
        'user_id'          => $nena,
        'Lifecycle_Status' => 'Active',
        'asset_location'   => 'Registrar Office',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

$assetIds = DB::table('assets')->orderBy('id')->pluck('id')->all();

// A pending transfer: this is the one the office approves, and either completes
// or hands to the Executive Director.
$pending = (int) DB::table('requests')->insertGetId([
    'user_id'           => $nena,
    'asset_id'          => $assetIds[0],
    'request_type'      => 'Transfer',
    'status'            => 'Pending',
    'Note'              => 'These should move to the new custodian after the reshuffle.',
    'assign_to_user_id' => $rico,
    'created_at'        => now(),
    'updated_at'        => now(),
]);

foreach ($assetIds as $assetId) {
    DB::table('request_items')->insert([
        'request_id' => $pending,
        'asset_id'   => $assetId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// A transfer the office already approved and forwarded, so the resend path can
// be walked through too. Its invitation is created from the Requests page.
$approved = (int) DB::table('requests')->insertGetId([
    'user_id'           => $nena,
    'asset_id'          => $assetIds[1],
    'request_type'      => 'Transfer',
    'status'            => 'Approved',
    'Note'              => 'Second batch of assets for the same employee.',
    'assign_to_user_id' => $rico,
    'created_at'        => now(),
    'updated_at'        => now(),
]);

DB::table('request_items')->insert([
    'request_id' => $approved,
    'asset_id'   => $assetIds[1],
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "Seeded: pending transfer request #{$pending}, approved transfer request #{$approved}\n";
echo 'Admin: amo@nu-lipa.edu.ph / preview-password' . PHP_EOL;
