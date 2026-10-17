<?php

/**
 * A throwaway database for walking through the Reports page in a browser.
 *
 * It exists so the walkthrough can run against SQLite instead of the
 * institution's live Supabase database. Nothing here is part of the
 * application; delete the file when done.
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=$PWD/database/preview-reports.sqlite \
 *   php .freebuff/reports_seed.php
 *
 * What it leaves behind is deliberately awkward, so the reports have something
 * real to say: two departments, an asset with no acquisition date at all, an
 * approved disposal request whose asset is still in service, a transfer that
 * was carried out and one still waiting, and a repair record whose asset was
 * retired afterwards.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

foreach ([
    'transfer_review_items', 'transfer_reviews', 'request_items', 'disposal_form_items', 'disposal_forms',
    'repair_evaluations', 'repairs', 'disposals', 'requests', 'audit_logs', 'assets', 'users',
    'employee_numbers', 'departments',
] as $table) {
    if (Illuminate\Support\Facades\Schema::hasTable($table)) {
        DB::table($table)->delete();
    }
}

DB::table('departments')->insert([
    ['id' => 1, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
    ['id' => 2, 'Name' => 'Accountancy', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
]);

DB::table('employee_numbers')->insert([
    ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Nena Santos', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Rico Bautista', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Lito Uy', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Mila Reyes', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
]);

$accounts = [
    ['employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph', 'role' => 'Admin'],
    ['employee_numbers_id' => 4, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph', 'role' => 'Department Head'],
    ['employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'nena@nu-lipa.edu.ph', 'role' => 'Employee'],
    ['employee_numbers_id' => 2, 'department_id' => 2, 'email' => 'rico@nu-lipa.edu.ph', 'role' => 'Employee'],
];

foreach ($accounts as $account) {
    DB::table('users')->insert($account + [
        'password'   => Hash::make('preview-password'),
        'status'     => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$nena = (int) DB::table('users')->where('email', 'nena@nu-lipa.edu.ph')->value('id');
$rico = (int) DB::table('users')->where('email', 'rico@nu-lipa.edu.ph')->value('id');

$assets = [
    ['Asset_code' => 'AST-0001', 'Asset_name' => 'Dell Laptop', 'Condition' => 'Good', 'user_id' => $nena, 'Lifecycle_Status' => 'Active', 'accusion_date' => now()->subMonthsNoOverflow(2)->toDateString(), 'purchase_Price' => 55000.00, 'supplier' => 'Dell Philippines', 'asset_location' => 'Registrar Office'],
    ['Asset_code' => 'AST-0002', 'Asset_name' => 'Epson Projector', 'Condition' => 'Fair', 'user_id' => $nena, 'Lifecycle_Status' => 'For Repair', 'accusion_date' => now()->subMonthNoOverflow()->toDateString(), 'purchase_Price' => 32000.00, 'supplier' => 'Epson Store', 'asset_location' => 'Registrar Office'],
    ['Asset_code' => 'AST-0003', 'Asset_name' => 'Canon Printer', 'Condition' => 'Good', 'user_id' => $rico, 'Lifecycle_Status' => 'Active', 'accusion_date' => now()->subYear()->toDateString(), 'purchase_Price' => 18000.00, 'supplier' => 'Canon Center', 'asset_location' => 'Accountancy Office'],
    // Deliberately undated: the acquisition report must list it, and a date
    // filter must leave it out and say so.
    ['Asset_code' => 'AST-0004', 'Asset_name' => 'Donated Steel Cabinet', 'Condition' => 'Existing', 'user_id' => $nena, 'Lifecycle_Status' => 'Acquired', 'accusion_date' => null, 'purchase_Price' => null, 'supplier' => null, 'asset_location' => 'Registrar Records'],
    ['Asset_code' => 'AST-0005', 'Asset_name' => 'Retired Monitor', 'Condition' => 'Fair', 'user_id' => $rico, 'Lifecycle_Status' => 'Disposal', 'accusion_date' => now()->subYears(4)->toDateString(), 'purchase_Price' => 9000.00, 'supplier' => 'LG Center', 'asset_location' => 'Accountancy Office'],
    // Acquired this month, so the "This Month" preset has something to show.
    ['Asset_code' => 'AST-0006', 'Asset_name' => 'New Desktop Unit', 'Condition' => 'New', 'user_id' => $nena, 'Lifecycle_Status' => 'Active', 'accusion_date' => now()->toDateString(), 'purchase_Price' => 41000.00, 'supplier' => 'Dell Philippines', 'asset_location' => 'Registrar Office'],
];

foreach ($assets as $asset) {
    DB::table('assets')->insert($asset + [
        'Category'   => 'Info and Equipment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$id = fn (string $code): int => (int) DB::table('assets')->where('Asset_code', $code)->value('id');

// ── A repair request, with the repair record it produced ────────────────
$repairRequest = (int) DB::table('requests')->insertGetId([
    'user_id' => $nena, 'asset_id' => null, 'request_type' => 'Repair', 'status' => 'Approved',
    'Note' => 'Projector shows a purple tint on start-up.',
    'created_at' => now()->subDays(12), 'updated_at' => now()->subDays(12),
]);

DB::table('request_items')->insert([
    'request_id' => $repairRequest, 'asset_id' => $id('AST-0002'),
    'created_at' => now(), 'updated_at' => now(),
]);

DB::table('repairs')->insert([
    'Assets_id' => $id('AST-0002'), 'Request_id' => $repairRequest,
    'Repair_Description' => 'Lamp module replaced and colour wheel cleaned.',
    'Repair_Date' => now()->subDays(10)->setTime(9, 30),
    'Approve_by' => 'Lito Uy', 'Repair_Cost' => 4500.00,
    'Repair_result' => 'Repairable', 'status' => 'In Progress',
    'created_at' => now(), 'updated_at' => now(),
]);

// ── A disposal request that was approved, with its record ──────────────
$disposalRequest = (int) DB::table('requests')->insertGetId([
    'user_id' => $rico, 'asset_id' => null, 'request_type' => 'Disposal', 'status' => 'Approved',
    'Note' => 'Monitor no longer powers on; beyond economical repair.',
    'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(38),
    'admin_remarks' => 'Approved for disposal by the Asset Management Office.',
    'admin_remarks_at' => now()->subDays(37),
]);

DB::table('request_items')->insert([
    'request_id' => $disposalRequest, 'asset_id' => $id('AST-0005'),
    'created_at' => now(), 'updated_at' => now(),
]);

DB::table('disposals')->insert([
    'Asset_id' => $id('AST-0005'), 'Request_id' => $disposalRequest,
    'Approve_by' => 'Lito Uy', 'Description' => 'End of service life',
    'disposal_date' => now()->subDays(35)->toDateString(), 'disposal_reason' => 'Beyond Repair',
    'is_archived' => false, 'notes' => 'Awaiting the gate pass.',
    'created_at' => now(), 'updated_at' => now(),
]);

// ── A disposal request that is approved but has no record yet ─────────
$pendingDisposal = (int) DB::table('requests')->insertGetId([
    'user_id' => $nena, 'asset_id' => null, 'request_type' => 'Disposal', 'status' => 'Approved',
    'Note' => 'Printer parts are no longer available.',
    'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(2),
]);

DB::table('request_items')->insert([
    'request_id' => $pendingDisposal, 'asset_id' => $id('AST-0003'),
    'created_at' => now(), 'updated_at' => now(),
]);

// ── A transfer that was carried out, and one still waiting ────────────
$done = (int) DB::table('requests')->insertGetId([
    'user_id' => $nena, 'asset_id' => null, 'assign_to_user_id' => $rico,
    'request_type' => 'Transfer', 'status' => 'Approved',
    'Note' => 'Position reassignment.',
    'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(19),
]);

DB::table('request_items')->insert([
    'request_id' => $done, 'asset_id' => $id('AST-0001'),
    'created_at' => now(), 'updated_at' => now(),
]);

DB::table('audit_logs')->insert([
    'user_id' => $nena, 'request_id' => $done, 'asset_id' => $id('AST-0001'),
    'action_type' => 'TRANSFER',
    'notes' => 'Transferred AST-0001 from Nena Santos to Rico Bautista (Position Reassignment).',
    'action_description' => 'Transfer ' . $done . ': AST-0001 — Dell Laptop reassigned to Rico Bautista',
    'created_at' => now()->subDays(19), 'updated_at' => now()->subDays(19),
]);

$waiting = (int) DB::table('requests')->insertGetId([
    'user_id' => $rico, 'asset_id' => null, 'assign_to_user_id' => $nena,
    'request_type' => 'Transfer', 'status' => 'Approved',
    'Note' => 'Asset moves back to the Registrar after the audit.',
    'created_at' => now()->subDays(2), 'updated_at' => now()->subDay(),
]);

DB::table('request_items')->insert([
    'request_id' => $waiting, 'asset_id' => $id('AST-0003'),
    'created_at' => now(), 'updated_at' => now(),
]);

echo 'Seeded. Admin: amo@nu-lipa.edu.ph · Head: head@nu-lipa.edu.ph · password: preview-password' . PHP_EOL;
