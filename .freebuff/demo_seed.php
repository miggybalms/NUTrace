<?php

/**
 * A throwaway database for the demonstration walkthrough.
 *
 * It is not part of the application. It fills a SQLite file with a small, tidy
 * data set so that each screen named in the demonstration guide has something
 * to show: departments with assets in every lifecycle status, requests spread
 * over several months, one disposal request whose official form is ready, and
 * two assets that have passed the three-year mark.
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=/var/www/html/database/demo.sqlite \
 *   php .freebuff/demo_seed.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

foreach ([
    'transfer_review_items', 'transfer_reviews', 'disposal_form_items', 'disposal_forms',
    'disposal_documents', 'disposals', 'request_items', 'requests', 'audit_logs',
    'notifications', 'asset_files', 'assets', 'users', 'employee_numbers', 'departments',
] as $table) {
    DB::table($table)->delete();
}

$now = now();

DB::table('departments')->insert([
    ['id' => 1, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => $now, 'Update_at' => $now],
    ['id' => 2, 'Name' => 'Accountancy', 'status' => 'Active', 'Create_at' => $now, 'Update_at' => $now],
    ['id' => 3, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => $now, 'Update_at' => $now],
]);

DB::table('employee_numbers')->insert([
    ['id' => 1, 'Employee_number' => 'NU-1001', 'Full_Name' => 'Lito Uy', 'Department_id' => 1, 'Email' => 'amo@nu-lipa.edu.ph', 'created_at' => $now, 'updated_at' => $now],
    ['id' => 2, 'Employee_number' => 'NU-1002', 'Full_Name' => 'Nena Santos', 'Department_id' => 1, 'Email' => 'nena@nu-lipa.edu.ph', 'created_at' => $now, 'updated_at' => $now],
    ['id' => 3, 'Employee_number' => 'NU-1003', 'Full_Name' => 'Rico Bautista', 'Department_id' => 2, 'Email' => 'rico@nu-lipa.edu.ph', 'created_at' => $now, 'updated_at' => $now],
    ['id' => 4, 'Employee_number' => 'NU-1004', 'Full_Name' => 'Grace Lim', 'Department_id' => 1, 'Email' => 'grace@nu-lipa.edu.ph', 'created_at' => $now, 'updated_at' => $now],
    ['id' => 5, 'Employee_number' => 'NU-1005', 'Full_Name' => 'Mark Diaz', 'Department_id' => 2, 'Email' => 'mark@nu-lipa.edu.ph', 'created_at' => $now, 'updated_at' => $now],
    ['id' => 6, 'Employee_number' => 'NU-1006', 'Full_Name' => 'Bea Cruz', 'Department_id' => 3, 'Email' => 'bea@nu-lipa.edu.ph', 'created_at' => $now, 'updated_at' => $now],
]);

$accounts = [
    ['employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph', 'role' => 'Admin'],
    ['employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'nena@nu-lipa.edu.ph', 'role' => 'Employee'],
    ['employee_numbers_id' => 3, 'department_id' => 2, 'email' => 'rico@nu-lipa.edu.ph', 'role' => 'Employee'],
    ['employee_numbers_id' => 4, 'department_id' => 1, 'email' => 'grace@nu-lipa.edu.ph', 'role' => 'Department Head'],
    ['employee_numbers_id' => 5, 'department_id' => 2, 'email' => 'mark@nu-lipa.edu.ph', 'role' => 'Department Head'],
    ['employee_numbers_id' => 6, 'department_id' => 3, 'email' => 'bea@nu-lipa.edu.ph', 'role' => 'Employee'],
];

foreach ($accounts as $account) {
    DB::table('users')->insert($account + [
        'password'   => Hash::make('DemoPass!2026'),
        'status'     => 'Active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

$admin = (int) DB::table('users')->where('email', 'amo@nu-lipa.edu.ph')->value('id');
$nena = (int) DB::table('users')->where('email', 'nena@nu-lipa.edu.ph')->value('id');
$rico = (int) DB::table('users')->where('email', 'rico@nu-lipa.edu.ph')->value('id');
$bea  = (int) DB::table('users')->where('email', 'bea@nu-lipa.edu.ph')->value('id');

$assets = [
    // Registrar
    ['AST-0001', 'Dell Latitude 5440 Laptop', 'Info and Office Equipment', 'Active', 'Registrar Office', '2024-01-15', 1, $nena],
    ['AST-0002', 'Epson EB-X06 Projector', 'Info and Office Equipment', 'Active', 'Registrar Office', '2023-06-20', 1, $nena],
    ['AST-0003', 'HP LaserJet Pro Printer', 'Info and Office Equipment', 'For Repair', 'Records Room', '2022-08-11', 1, $nena],
    ['AST-0004', 'Executive Office Desk', 'Furniture and Fixtures', 'Active', 'Registrar Office', '2019-03-04', 1, $nena],
    ['AST-0005', 'Steel Filing Cabinet', 'Furniture and Fixtures', 'Active', 'Records Room', '2021-07-19', 1, $nena],
    ['AST-0006', 'Aircon 1.5 HP Split Type', 'Airconditioning Equipment', 'For Checking', 'Registrar Office', '2018-11-05', 1, $nena],
    ['AST-0007', 'Sharp Calculator', 'Office Supplies and Devices', 'Acquired', 'Registrar Office', '2026-10-10', 1, $nena],
    ['AST-0008', 'Logitech Webcam C920', 'Info and Office Equipment', 'For Replacement', 'Registrar Office', '2022-02-14', 1, $nena],
    ['AST-0009', 'Wooden Bookshelf', 'Furniture and Fixtures', 'Pullout', 'Registrar Office', '2017-09-01', 1, $nena],
    ['AST-0010', 'Canon Document Scanner', 'Info and Office Equipment', 'Active', 'Records Room', '2024-05-22', 1, $nena],
    // Accountancy
    ['AST-0011', 'Accounting Workstation PC', 'Info and Office Equipment', 'Active', 'Accountancy Office', '2023-02-08', 2, $rico],
    ['AST-0012', 'Dell Monitor 24"', 'Info and Office Equipment', 'Active', 'Accountancy Office', '2024-08-30', 2, $rico],
    ['AST-0013', 'Conference Table', 'Furniture and Fixtures', 'Active', 'Accountancy Office', '2020-04-12', 2, $rico],
    ['AST-0014', 'Wall Clock', 'Office Supplies and Devices', 'For Repair', 'Accountancy Office', '2021-12-01', 2, $rico],
    ['AST-0015', 'Heavy Duty Paper Shredder', 'Info and Office Equipment', 'Disposed', 'Accountancy Office', '2016-06-15', 2, $rico],
    // Facilities
    ['AST-0016', 'Industrial Vacuum Cleaner', 'Facilities Equipment', 'Active', 'Facilities Office', '2023-10-18', 3, $bea],
    ['AST-0017', 'Folding Tables (Set of 5)', 'Furniture and Fixtures', 'Active', 'Gym Storage', '2022-03-27', 3, $bea],
    ['AST-0018', 'Portable Sound System', 'Info and Office Equipment', 'For Checking', 'Facilities Office', '2019-08-09', 3, $bea],
];

$assetIds = [];

foreach ($assets as [$code, $name, $category, $status, $location, $acquired, $deptId, $owner]) {
    $assetIds[$code] = (int) DB::table('assets')->insertGetId([
        'user_id'          => $owner,
        'department_id'    => $deptId,
        'Asset_code'       => $code,
        'Asset_name'       => $name,
        'Category'         => $category,
        'Condition'        => $status === 'For Repair' ? 'Fair' : 'Good',
        'Lifecycle_Status' => $status,
        'asset_location'   => $location,
        'accusion_date'    => $acquired,
        'purchase_Price'   => 25000,
        'property_id_no'   => '2026-' . substr($code, -4),
        'accountable_name' => 'Nena Santos',
        'created_at'       => $now,
        'updated_at'       => $now,
    ]);
}

/** A request plus its request_items rows. */
$makeRequest = function (
    string $type,
    string $status,
    int $ownerId,
    array $codes,
    string $note,
    string $createdAt,
    array $extra = []
): int {
    $id = (int) DB::table('requests')->insertGetId($extra + [
        'user_id'      => $ownerId,
        'asset_id'     => $GLOBALS['assetIds'][$codes[0]] ?? null,
        'request_type' => $type,
        'status'       => $status,
        'Note'         => $note,
        'created_at'   => $createdAt,
        'updated_at'   => $createdAt,
    ]);

    foreach ($codes as $code) {
        DB::table('request_items')->insert([
            'request_id' => $id,
            'asset_id'   => $GLOBALS['assetIds'][$code],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    return $id;
};

$GLOBALS['assetIds'] = $assetIds;

$approvedDisposal = $makeRequest(
    'Disposal', 'Approved', $nena, ['AST-0015'],
    'The printer has been beyond repair for two years and the service centre no longer carries parts.',
    '2026-10-06 09:15:00',
    ['admin_remarks' => 'Approved for disposal. Supporting photographs attached.']
);

$pendingDisposal = $makeRequest(
    'Disposal', 'Pending', $nena, ['AST-0008'],
    'Webcam no longer detected by any computer; replaced with a newer unit.',
    '2026-10-20 14:05:00'
);

$makeRequest(
    'Repair', 'Pending', $nena, ['AST-0003'],
    'Printer jams on every second page and the fuser makes a grinding noise.',
    '2026-10-18 10:40:00'
);

$makeRequest(
    'Transfer', 'Approved', $nena, ['AST-0002'],
    'Projector to be transferred to the Accountancy department for their new room.',
    '2026-10-02 11:20:00',
    ['assign_to_user_id' => $rico]
);

$makeRequest(
    'Replacement', 'Rejected', $nena, ['AST-0008'],
    'Requesting a replacement webcam for online hearings.',
    '2026-09-18 15:10:00',
    ['admin_remarks' => 'Replacement is on hold until the disposed unit is archived.']
);

$makeRequest(
    'Repair', 'Approved', $nena, ['AST-0006'],
    'Aircon is not cooling; cleaning was already done last month.',
    '2026-09-09 08:55:00'
);

$makeRequest(
    'Pullout', 'Approved', $nena, ['AST-0009'],
    'Bookshelf is being pulled out to free space for the new records cabinets.',
    '2026-08-22 13:30:00'
);

$makeRequest(
    'Transfer', 'Rejected', $nena, ['AST-0010'],
    'Scanner transfer to Facilities for the incoming inventory project.',
    '2026-07-11 16:45:00'
);

// The official disposal form for the approved request: prepared and marked
// ready, which is the stage the Forms tab shows.
$formId = (int) DB::table('disposal_forms')->insertGetId([
    'request_id'            => $approvedDisposal,
    'status'                => 'ready',
    'office_department'     => 'Registrar',
    'expected_disposal_date' => '2026-10-28',
    'method_key'            => 'scrap',
    'method_code'           => 'S',
    'approved_reason'       => 'Beyond Repair',
    'prepared_by'           => $admin,
    'prepared_at'           => '2026-10-07 09:00:00',
    'ready_at'              => '2026-10-07 09:30:00',
    'remarks'               => 'Unit is unserviceable; parts are no longer available.',
    'created_at'            => $now,
    'updated_at'            => $now,
]);

DB::table('disposal_form_items')->insert([
    'form_id'       => $formId,
    'asset_id'      => $assetIds['AST-0015'],
    'tag_no'        => 'AST-0015',
    'quantity'      => 1,
    'description'   => 'Heavy Duty Paper Shredder',
    'serial_number' => 'SN-AST-0015',
    'date_acquired' => '2016-06-15',
    'location'      => 'Accountancy Office',
    'custodian'     => 'Rico Bautista',
    'condition_code' => 'P',
    'reason_code'    => 'B',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

// Two notifications for the office, so the bell has something in it.
DB::table('notifications')->insert([
    [
        'user_id'        => $admin,
        'title'          => 'New disposal request',
        'message'        => 'Nena Santos submitted a disposal request for a webcam (AST-0008).',
        'type'           => 'DISPOSAL',
        'reference_id'   => $pendingDisposal,
        'reference_type' => 'request',
        'is_read'        => false,
        'created_at'     => '2026-10-20 14:06:00',
        'updated_at'     => '2026-10-20 14:06:00',
    ],
    [
        'user_id'        => $admin,
        'title'          => 'Disposal Request Approved',
        'message'        => 'The disposal request for AST-0015 has been approved.',
        'type'           => 'DISPOSAL',
        'reference_id'   => $approvedDisposal,
        'reference_type' => 'request',
        'is_read'        => true,
        'created_at'     => '2026-10-06 10:00:00',
        'updated_at'     => '2026-10-06 10:00:00',
    ],
    [
        'user_id'        => $nena,
        'title'          => 'Disposal Request Approved',
        'message'        => 'Your disposal request for the Heavy Duty Paper Shredder (AST-0015) was approved. The Asset Management Office is preparing the official form.',
        'type'           => 'DISPOSAL',
        'reference_id'   => $approvedDisposal,
        'reference_type' => 'request',
        'is_read'        => false,
        'created_at'     => '2026-10-06 10:00:00',
        'updated_at'     => '2026-10-06 10:00:00',
    ],
]);

DB::table('audit_logs')->insert([
    [
        'user_id' => $admin, 'request_id' => $approvedDisposal, 'asset_id' => $assetIds['AST-0015'],
        'action_type' => 'APPROVAL', 'notes' => 'Disposal request #' . $approvedDisposal . ' approved.',
        'action_description' => 'The Asset Management Office approved a disposal request.',
        'created_at' => '2026-10-06 10:00:00', 'updated_at' => '2026-10-06 10:00:00',
    ],
    [
        'user_id' => $admin, 'request_id' => null, 'asset_id' => $assetIds['AST-0007'],
        'action_type' => 'CREATE', 'notes' => 'Asset AST-0007 registered.',
        'action_description' => 'A new asset was registered with a signed QR sticker.',
        'created_at' => '2026-10-10 09:30:00', 'updated_at' => '2026-10-10 09:30:00',
    ],
]);

echo 'Seeded demo database: ' . DB::table('assets')->count() . ' assets, '
    . DB::table('requests')->count() . ' requests, '
    . DB::table('disposal_forms')->count() . ' disposal form(s).' . PHP_EOL;
echo 'Admin login: amo@nu-lipa.edu.ph / DemoPass!2026' . PHP_EOL;
