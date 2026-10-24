<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Paging and filtering on the four Asset Management Office lists.
 *
 * The rule these lock down: the page shows ten rows, and a tab or a search is
 * answered by the database over the whole table. A filter must find a record
 * that is not on page 1 — the bug being that the same controls used to run in
 * JavaScript over the rows already fetched, so they could only ever hide rows
 * on the page that happened to be open.
 */
class AdminListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Juan Dela Cruz', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'juan@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);
    }

    private function makeAsset(string $code, string $name): int
    {
        return (int) DB::table('assets')->insertGetId([
            'user_id'          => $this->employee->id,
            'Asset_code'       => $code,
            'Asset_name'       => $name,
            'Category'         => 'General and Office Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location'   => 'Faculty Room',
            'accusion_date'    => now()->subYear()->toDateString(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function makeRequest(int $assetId, string $type): int
    {
        return (int) DB::table('requests')->insertGetId([
            'user_id'      => $this->employee->id,
            'asset_id'     => $assetId,
            'request_type' => $type,
            'status'       => 'Approved',
            'Note'         => 'Seeded for the list.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function test_the_repair_queue_pages_ten_and_filters_the_whole_queue(): void
    {
        // The ten newest are "Alpha …"; the two oldest — page 2 — are "Zeta …".
        for ($i = 1; $i <= 12; $i++) {
            $assetId = $this->makeAsset(sprintf('AST-%03d', $i), sprintf('%s Repair %02d', $i <= 10 ? 'Alpha' : 'Zeta', $i));
            $requestId = $this->makeRequest($assetId, 'Repair');

            DB::table('repairs')->insert([
                'Assets_id'          => $assetId,
                'Request_id'         => $requestId,
                'Repair_Description' => 'Fault ' . $i,
                'Repair_Date'        => now()->subDays(20 - $i)->toDateString(),
                'Approve_by'         => 'amo@nu-lipa.edu.ph',
                'Repair_Cost'        => 500,
                // The two rows on page 2 are the cancelled ones.
                'status'             => $i <= 10 ? 'Completed' : 'Cancelled',
                'created_at'         => now()->subDays(20 - $i),
                'updated_at'         => now()->subDays(20 - $i),
            ]);
        }

        $page1 = $this->actingAs($this->admin)->get('/admin/repair')->assertOk();
        $repairs = $page1->viewData('repairs');

        $this->assertSame(10, $repairs->perPage());
        $this->assertSame(12, $repairs->total());
        $this->assertCount(10, $repairs->items());
        $page1->assertSee('page=2', false);

        // The cancelled repairs are on page 2, yet the tab finds them.
        $filtered = $this->actingAs($this->admin)->get('/admin/repair?status=cancelled')->assertOk();
        $this->assertSame(2, $filtered->viewData('repairs')->total());
        $filtered->assertSee('Zeta Repair 11', false);

        // Same for a search: a name that only exists beyond page 1.
        $searched = $this->actingAs($this->admin)->get('/admin/repair?q=Zeta Repair 12')->assertOk();
        $this->assertSame(1, $searched->viewData('repairs')->total());
        $searched->assertSee('Zeta Repair 12', false);
    }

    public function test_the_replacement_list_pages_ten_and_filters_the_whole_list(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $oldId = $this->makeAsset(sprintf('OLD-%03d', $i), sprintf('%s Old Unit %02d', $i <= 10 ? 'Alpha' : 'Zeta', $i));
            $newId = $this->makeAsset(sprintf('NEW-%03d', $i), 'Replacement Unit ' . $i);
            $requestId = $this->makeRequest($oldId, 'Replacement');

            DB::table('replacements')->insert([
                'Request_id'         => $requestId,
                'old_assets_id'      => $oldId,
                'new_assets_id'      => $newId,
                'reason'             => 'Beyond repair',
                'Replacement_Date'   => now()->subDays(20 - $i)->toDateString(),
                'Approve_by'         => 'amo@nu-lipa.edu.ph',
                // The two rows on page 2 are the received ones.
                'status'             => $i <= 10 ? 'Approved' : 'Received',
                'replacement_reason' => 'Beyond Repair',
                'created_at'         => now()->subDays(20 - $i),
                'updated_at'         => now()->subDays(20 - $i),
            ]);
        }

        $page1 = $this->actingAs($this->admin)->get('/admin/replacement')->assertOk();
        $replacements = $page1->viewData('replacements');

        $this->assertSame(10, $replacements->perPage());
        $this->assertSame(12, $replacements->total());
        $this->assertCount(10, $replacements->items());
        $page1->assertSee('page=2', false);
        $page1->assertSee('12 records', false);

        $filtered = $this->actingAs($this->admin)->get('/admin/replacement?status=Received')->assertOk();
        $this->assertSame(2, $filtered->viewData('replacements')->total());
        $filtered->assertSee('OLD-011', false);

        $searched = $this->actingAs($this->admin)->get('/admin/replacement?q=Zeta Old Unit 12')->assertOk();
        $this->assertSame(1, $searched->viewData('replacements')->total());
        $searched->assertSee('OLD-012', false);
    }

    public function test_the_disposal_list_pages_ten_and_searches_the_whole_table(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $assetId = $this->makeAsset(sprintf('DIS-%03d', $i), sprintf('%s Disposed Unit %02d', $i <= 10 ? 'Alpha' : 'Zeta', $i));
            $requestId = $this->makeRequest($assetId, 'Disposal');

            DB::table('disposals')->insert([
                'Asset_id'        => $assetId,
                'Request_id'      => $requestId,
                'Approve_by'      => 'amo@nu-lipa.edu.ph',
                'Description'     => 'Disposal record ' . $i,
                'disposal_date'   => now()->subDays(20 - $i)->toDateString(),
                'disposal_reason' => 'Beyond Repair',
                'notes'           => 'Seeded.',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $page1 = $this->actingAs($this->admin)->get('/admin/disposal')->assertOk();
        $records = $page1->viewData('disposalRecords');

        $this->assertSame(10, $records->perPage());
        $this->assertSame(12, $records->total());
        $page1->assertSee('page=2', false);

        $searched = $this->actingAs($this->admin)->get('/admin/disposal?q=Zeta Disposed Unit 11')->assertOk();
        $this->assertSame(1, $searched->viewData('disposalRecords')->total());
        $searched->assertSee('DIS-011', false);
    }

    public function test_the_pullout_list_pages_ten_and_searches_the_whole_table(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $assetId = $this->makeAsset(sprintf('PUL-%03d', $i), sprintf('%s Pulled Unit %02d', $i <= 10 ? 'Alpha' : 'Zeta', $i));
            $requestId = $this->makeRequest($assetId, 'Pullout');

            DB::table('pullouts')->insert([
                'request_id'   => $requestId,
                'asset_id'     => $assetId,
                'Approve_by'   => 'amo@nu-lipa.edu.ph',
                'Description'  => 'Pullout record ' . $i,
                'notes'        => 'Seeded.',
                'pullout_date' => now()->subDays(20 - $i)->toDateString(),
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        $page1 = $this->actingAs($this->admin)->get('/admin/pullout')->assertOk();
        $records = $page1->viewData('pulloutRecords');

        $this->assertSame(10, $records->perPage());
        $this->assertSame(12, $records->total());
        $page1->assertSee('page=2', false);

        $searched = $this->actingAs($this->admin)->get('/admin/pullout?q=Zeta Pulled Unit 12')->assertOk();
        $this->assertSame(1, $searched->viewData('pulloutRecords')->total());
        $searched->assertSee('PUL-012', false);
    }
}
