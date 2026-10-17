<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Reports page.
 *
 * The panel's reporting recommendation is mostly a list of things a report must
 * not do: it must not show another department's records, must not let a filter
 * widen what a department head may read, must not confuse a workflow status with
 * a finished event, must not count units it never recorded, must not export only
 * the page on screen, and must not let a failed attempt pass for the report that
 * was asked for.
 *
 * Those are the tests below. Each one is written against the page the two roles
 * actually open — /admin/reports and /department-head/reports — because a
 * permission that only exists in a WHERE clause nobody reaches is not a
 * permission.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $head;
    private User $employee;
    private User $otherHead;
    private User $outsider;

    private int $ownAsset;
    private int $foreignAsset;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Admin office', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Registrar Head', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'Employee_number' => 'EMP-5', 'Full_Name' => 'Other Dept', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = $this->account(1, 1, 'alex@nu-lipa.edu.ph', 'Admin');
        $this->employee = $this->account(2, 1, 'nena@nu-lipa.edu.ph', 'Employee');
        $this->head = $this->account(3, 1, 'head@nu-lipa.edu.ph', 'Department Head');
        $this->otherHead = $this->account(4, 2, 'rhead@nu-lipa.edu.ph', 'Department Head');
        $this->outsider = $this->account(5, 2, 'other@nu-lipa.edu.ph', 'Employee');

        $this->ownAsset = $this->asset('AST-0001', 'Office Laptop', $this->employee->id, [
            'accusion_date' => now()->toDateString(),
        ]);
        $this->foreignAsset = $this->asset('AST-9001', 'Registrar Printer', $this->outsider->id, [
            'accusion_date' => now()->subMonthNoOverflow()->toDateString(),
        ]);
    }

    /* ── helpers ───────────────────────────────────────────────────────── */

    private function account(int $employeeNumberId, int $departmentId, string $email, string $role): User
    {
        return User::create([
            'employee_numbers_id' => $employeeNumberId,
            'department_id'       => $departmentId,
            'email'               => $email,
            'password'            => Hash::make('secret-password'),
            'role'                => $role,
            'status'              => 'Active',
        ]);
    }

    /** One asset record, in the inventory, held by one employee. */
    private function asset(string $code, string $name, int $holderId, array $overrides = []): int
    {
        return (int) DB::table('assets')->insertGetId(array_merge([
            'user_id'          => $holderId,
            'Asset_code'       => $code,
            'Asset_name'       => $name,
            'Category'         => 'Info and Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => 'Active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));
    }

    private function request(array $overrides = []): int
    {
        return (int) DB::table('requests')->insertGetId(array_merge([
            'user_id'      => $this->employee->id,
            'asset_id'     => null,
            'request_type' => 'Repair',
            'status'       => 'Pending',
            'Note'         => 'Please look into this.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $overrides));
    }

    private function repair(int $assetId, int $requestId, string $status = 'Pending', ?string $date = null): int
    {
        return (int) DB::table('repairs')->insertGetId([
            'Assets_id'          => $assetId,
            'Request_id'         => $requestId,
            'Repair_Description' => 'Diagnosed and serviced',
            'Repair_Date'        => $date ?? now()->toDateTimeString(),
            'Approve_by'         => 'Sir Alex',
            'Repair_Cost'        => 1500.00,
            'Repair_result'      => 'Repairable',
            'status'             => $status,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function disposal(int $assetId, ?int $requestId, string $date, bool $archived = false): int
    {
        return (int) DB::table('disposals')->insertGetId([
            'Asset_id'        => $assetId,
            'Request_id'      => $requestId,
            'Approve_by'      => 'Sir Alex',
            'Description'     => 'End of service life',
            'disposal_date'   => $date,
            'disposal_reason' => 'Obsolete',
            'is_archived'     => $archived,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'Disposal_ID');
    }

    /** A transfer request with its assets in request_items. */
    private function transfer(int $fromId, int $toId, array $assetIds, string $status = 'Approved'): int
    {
        $id = $this->request([
            'user_id'            => $fromId,
            'assign_to_user_id'  => $toId,
            'request_type'       => 'Transfer',
            'status'             => $status,
        ]);

        foreach ($assetIds as $assetId) {
            DB::table('request_items')->insert([
                'request_id' => $id, 'asset_id' => $assetId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    /** The history entry a carried-out transfer leaves on an asset. */
    private function recordHandover(int $requestId, int $assetId, Carbon $at): void
    {
        DB::table('audit_logs')->insert([
            'user_id'            => $this->admin->id,
            'request_id'         => $requestId,
            'asset_id'           => $assetId,
            'action_type'        => 'TRANSFER',
            'notes'              => 'Transferred',
            'action_description' => 'Asset reassigned',
            'created_at'         => $at,
            'updated_at'         => $at,
        ]);
    }

    /* ═════════════ 1. who may read reports at all ═════════════ */

    public function test_reports_are_open_to_the_office_and_to_department_heads_only(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->assertSee('Reports');
        $this->actingAs($this->head)->get('/department-head/reports')->assertOk()->assertSee('Reports');

        // An employee holds no reporting authority, on either side.
        $this->actingAs($this->employee)->get('/admin/reports')->assertForbidden();
        $this->actingAs($this->employee)->get('/department-head/reports')->assertForbidden();

        // Each role keeps its own prefix.
        $this->actingAs($this->head)->get('/admin/reports')->assertForbidden();
        $this->actingAs($this->admin)->get('/department-head/reports')->assertForbidden();
    }

    /**
     * The retired Facilities role gains nothing. It is not an Administrator, so
     * every reporting door that the office uses is closed to it — the same
     * refusal an ordinary employee account gets.
     */
    public function test_the_retired_facilities_role_gains_no_reporting_access(): void
    {
        $facilities = $this->account(2, 1, 'facilities@nu-lipa.edu.ph', 'Facilities');

        $this->actingAs($facilities)->get('/admin/reports')->assertForbidden();
        $this->actingAs($facilities)->get('/admin/reports/export')->assertForbidden();
        $this->actingAs($facilities)->get('/admin/reports/print')->assertForbidden();
        $this->actingAs($facilities)->get('/department-head/reports')->assertForbidden();
    }

    public function test_reports_and_their_downloads_are_not_served_without_a_session(): void
    {
        $this->get('/admin/reports')->assertRedirect('/');
        $this->get('/admin/reports/export')->assertRedirect('/');
        $this->get('/admin/reports/print')->assertRedirect('/');
        $this->post('/admin/reports/generate', ['category' => 'inventory'])->assertRedirect('/');
        $this->get('/department-head/reports')->assertRedirect('/');
        $this->get('/department-head/reports/export')->assertRedirect('/');
    }

    /* ═════════════ 2. the categories, and what one row is ═════════════ */

    public function test_the_default_report_is_the_current_inventory() : void
    {
        $page = $this->actingAs($this->admin)->get('/admin/reports')->assertOk();

        $page->assertSee('Current Asset Inventory');
        $page->assertSee('AST-0001');
        $page->assertSee('AST-9001');

        // The categories the schema can actually answer are all offered…
        foreach (['Asset Acquisitions', 'Repairs and Maintenance', 'Asset Transfers', 'Asset Disposals', 'Requests and Workflow Statuses'] as $label) {
            $page->assertSee($label);
        }
    }

    public function test_every_report_category_renders_and_uses_its_own_date_field(): void
    {
        $requestId = $this->request(['request_type' => 'Repair']);
        DB::table('request_items')->insert([
            'request_id' => $requestId, 'asset_id' => $this->ownAsset,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->repair($this->ownAsset, $requestId, 'Completed');
        $this->disposal($this->foreignAsset, null, now()->toDateString());
        $transferId = $this->transfer($this->employee->id, $this->otherHead->id, [$this->ownAsset]);
        $this->recordHandover($transferId, $this->ownAsset, now()->subDay());

        $dates = [
            'inventory'    => 'Date acquired',
            'acquisitions' => 'Date acquired',
            'repairs'      => 'Repair date',
            'transfers'    => 'Transfer date',
            'disposals'    => 'Disposal date',
            'requests'     => 'Date submitted',
        ];

        foreach ($dates as $category => $dateLabel) {
            $page = $this->actingAs($this->admin)
                ->get('/admin/reports?category=' . $category)
                ->assertOk();

            $page->assertSee($dateLabel);

            // Every one of them offers the same practical presets.
            foreach (['This Month', 'Last Month', 'This Year', 'Last Year', 'Custom Date Range'] as $preset) {
                $page->assertSee($preset);
            }
        }
    }

    /**
     * A request is one row whatever it names, and a repair is one row per
     * record — the two are never counted as each other, and a transfer of two
     * assets is two rows and one transfer.
     */
    public function test_records_requests_and_transaction_lines_are_counted_separately(): void
    {
        $second = $this->asset('AST-0002', 'Second Laptop', $this->employee->id, [
            'accusion_date' => now()->toDateString(),
        ]);

        $transferId = $this->transfer($this->employee->id, $this->otherHead->id, [$this->ownAsset, $second]);
        $this->recordHandover($transferId, $this->ownAsset, now());
        $this->recordHandover($transferId, $second, now());

        $page = $this->actingAs($this->admin)->get('/admin/reports?category=transfers')->assertOk();

        $page->assertSee('Assets in matching transfers');
        $page->assertSee('2');                    // two rows, one per asset
        $page->assertSee('Transfers');
        $page->assertSee('TR-' . str_pad((string) $transferId, 4, '0', STR_PAD_LEFT));
    }

    /* ═════════════ 3. a department head reads one department ═════════════ */

    public function test_a_department_head_reads_only_their_own_department(): void
    {
        // A repair and a disposal on the other department's asset, and one
        // request that belongs to the other department.
        $foreignRequest = $this->request(['user_id' => $this->outsider->id, 'request_type' => 'Repair']);
        $this->repair($this->foreignAsset, $foreignRequest, 'Completed');
        $this->disposal($this->foreignAsset, null, now()->toDateString());

        $ownRequest = $this->request(['asset_id' => null]);
        $this->repair($this->ownAsset, $ownRequest, 'Pending');
        $this->disposal($this->ownAsset, null, now()->toDateString());

        $foreignTransfer = $this->transfer($this->outsider->id, $this->outsider->id, [$this->foreignAsset]);
        $this->recordHandover($foreignTransfer, $this->foreignAsset, now());

        // …and a transfer that is this department's own, so the transfers report
        // has something in it for the head as well.
        $ownTransfer = $this->transfer($this->employee->id, $this->head->id, [$this->ownAsset]);
        $this->recordHandover($ownTransfer, $this->ownAsset, now());

        foreach (['inventory', 'acquisitions', 'repairs', 'transfers', 'disposals', 'requests'] as $category) {
            $page = $this->actingAs($this->head)
                ->get('/department-head/reports?category=' . $category)
                ->assertOk();

            $page->assertSee('AST-0001');
            $page->assertDontSee('AST-9001');
        }
    }

    /**
     * The scope is the account, not a filter. Hand-editing the URL — or posting
     * a department — must not open another department's records.
     */
    public function test_a_department_head_cannot_widen_their_scope_through_a_request(): void
    {
        $this->disposal($this->foreignAsset, null, now()->toDateString());

        foreach ([
            '/department-head/reports?category=inventory&department=2',
            '/department-head/reports?category=disposals&department=ALL',
            '/department-head/reports/export?category=inventory&department=2',
            '/department-head/reports/print?category=inventory&department=2',
        ] as $url) {
            $response = $this->actingAs($this->head)->get($url)->assertOk();

            $this->assertStringNotContainsString('AST-9001', $response->getContent(), $url);
            $this->assertStringNotContainsString('Registrar Printer', $response->getContent(), $url);
        }

        // The same through the Apply Filters button.
        $applied = $this->actingAs($this->head)->post('/department-head/reports/generate', [
            'category'   => 'inventory',
            'preset'     => 'all',
            'status'     => 'all',
            'department' => '2',
        ]);

        $applied->assertRedirect();

        $this->assertStringNotContainsString(
            'AST-9001',
            $this->actingAs($this->head)->get('/department-head/reports?category=inventory')->getContent()
        );

        // And the department filter is not offered to them at all.
        $this->actingAs($this->head)
            ->get('/department-head/reports')
            ->assertOk()
            ->assertDontSee('report-department', false)
            ->assertSee('Department Head view');
    }

    public function test_the_office_may_narrow_a_report_to_one_department(): void
    {
        $page = $this->actingAs($this->admin)
            ->get('/admin/reports?category=inventory&department=2')
            ->assertOk();

        $page->assertSee('AST-9001');
        $page->assertDontSee('AST-0001');

        // A department id that names nothing filters nothing, and says so.
        $this->actingAs($this->admin)
            ->post('/admin/reports/generate', ['category' => 'inventory', 'department' => '4242'])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get('/admin/reports?category=inventory')
            ->assertOk()
            ->assertSee('AST-0001');
    }

    /* ═════════════ 4. dates ═════════════ */

    public function test_date_presets_use_the_reports_own_event_date(): void
    {
        $thisMonth = 'AST-1000';
        $lastMonth = 'AST-1001';
        $lastYear  = 'AST-1002';

        $this->asset($thisMonth, 'Bought this month', $this->employee->id, ['accusion_date' => now()->toDateString()]);
        $this->asset($lastMonth, 'Bought last month', $this->employee->id, ['accusion_date' => now()->subMonthNoOverflow()->toDateString()]);
        $this->asset($lastYear, 'Bought last year', $this->employee->id, ['accusion_date' => now()->subYear()->toDateString()]);

        $thisMonthPage = $this->actingAs($this->admin)
            ->get('/admin/reports?category=acquisitions&preset=this_month')->assertOk();
        $thisMonthPage->assertSee($thisMonth);
        $thisMonthPage->assertDontSee($lastMonth);
        $thisMonthPage->assertDontSee($lastYear);
        $thisMonthPage->assertSee('This Month');

        $lastMonthPage = $this->actingAs($this->admin)
            ->get('/admin/reports?category=acquisitions&preset=last_month')->assertOk();
        $lastMonthPage->assertSee($lastMonth);
        $lastMonthPage->assertDontSee($thisMonth);

        $lastYearPage = $this->actingAs($this->admin)
            ->get('/admin/reports?category=acquisitions&preset=last_year')->assertOk();
        $lastYearPage->assertSee($lastYear);
        $lastYearPage->assertDontSee($thisMonth);

        // Repairs read the repair date, not the asset's acquisition date.
        $requestId = $this->request();
        $this->repair($this->ownAsset, $requestId, 'Completed', now()->subMonthsNoOverflow(2)->toDateTimeString());

        $this->actingAs($this->admin)
            ->get('/admin/reports?category=repairs&preset=this_month')
            ->assertOk()
            ->assertDontSee('Diagnosed and serviced');

        $this->actingAs($this->admin)
            ->get('/admin/reports?category=repairs&preset=' . 'all')
            ->assertOk()
            ->assertSee('Diagnosed and serviced');
    }

    public function test_a_custom_range_includes_its_final_day(): void
    {
        $today = now()->toDateString();

        $this->asset('AST-2000', 'Dated today', $this->employee->id, ['accusion_date' => $today]);

        $page = $this->actingAs($this->admin)->get(
            '/admin/reports?category=acquisitions&preset=custom&from=' . $today . '&to=' . $today
        )->assertOk();

        $page->assertSee('AST-2000');
        $page->assertSee('Custom Date Range');
    }

    public function test_an_undated_record_is_listed_but_never_inside_a_date_filter(): void
    {
        $undated = $this->asset('AST-3000', 'Undated acquisition', $this->employee->id, ['accusion_date' => null]);

        // No date filter: it belongs in the report.
        $all = $this->actingAs($this->admin)
            ->get('/admin/reports?category=acquisitions&preset=all')->assertOk();
        $all->assertSee('AST-3000');

        // A date filter: a row with no date cannot be inside a range, and the
        // report says how many were left out.
        $filtered = $this->actingAs($this->admin)
            ->get('/admin/reports?category=acquisitions&preset=this_month')->assertOk();
        $filtered->assertDontSee('AST-3000');
        $filtered->assertSee('Not shown (no recorded date)');

        $this->assertNotSame(0, $undated);
    }

    /* ═════════════ 5. current status vs historical status ═════════════ */

    public function test_current_status_and_historical_status_are_kept_apart(): void
    {
        // An approved disposal request whose asset has NOT been disposed of:
        // the request is answered, the asset is still in service.
        $requestId = $this->request(['request_type' => 'Disposal', 'status' => 'Approved']);

        $this->actingAs($this->admin)
            ->get('/admin/reports?category=requests')
            ->assertOk()
            ->assertSee('REQ-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT))
            ->assertSee('Approved')
            ->assertSee('No disposal record');

        // The disposal report holds disposal records. There is none yet, so the
        // approved request does not appear here as though the asset were gone.
        $disposals = $this->actingAs($this->admin)
            ->get('/admin/reports?category=disposals')->assertOk();
        $disposals->assertSee('No records found for the selected filters');
        $disposals->assertDontSee('REQ-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT));

        // And the asset is still in the inventory.
        $this->actingAs($this->admin)
            ->get('/admin/reports?category=inventory')
            ->assertOk()
            ->assertSee('AST-0001');
    }

    public function test_a_status_that_belongs_to_another_report_is_refused_and_said_so(): void
    {
        // 'Approved' is a request status: it says nothing about an asset's
        // current lifecycle status, so the inventory report refuses it.
        $page = $this->actingAs($this->admin)->post('/admin/reports/generate', [
            'category' => 'inventory',
            'status'   => 'Approved',
        ]);

        $page->assertRedirect('/admin/reports?category=inventory');

        $this->actingAs($this->admin)
            ->get('/admin/reports?category=inventory')
            ->assertOk()
            ->assertSee('does not apply to the', false);

        // On the requests report the very same value is the right one.
        $requestId = $this->request(['status' => 'Approved']);

        $this->actingAs($this->admin)
            ->get('/admin/reports?category=requests&status=Approved')
            ->assertOk()
            ->assertSee('REQ-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT));
    }

    /* ═════════════ 6. summaries ═════════════ */

    public function test_summary_cards_and_breakdowns_use_the_applied_filters(): void
    {
        $this->asset('AST-4000', 'Third laptop', $this->employee->id, [
            'accusion_date' => now()->toDateString(),
            'Lifecycle_Status' => 'For Repair',
        ]);

        // Across both departments: the two assets setUp() made plus the one just
        // added, and the breakdown names both departments.
        $all = $this->actingAs($this->admin)->get('/admin/reports?category=inventory')->assertOk();
        $all->assertSee('Assets in this report');
        $all->assertSee('Admin office');
        $all->assertSee('Registrar');
        $this->assertMatchesRegularExpression('/data-report-total[^>]*>3</s', $all->getContent());

        // Narrowed to one department: the same filters reach the cards, and a
        // single-department breakdown is not shown.
        $narrowed = $this->actingAs($this->admin)
            ->get('/admin/reports?category=inventory&department=2')->assertOk();

        $this->assertMatchesRegularExpression('/data-report-total[^>]*>1</s', $narrowed->getContent());
        $narrowed->assertDontSee('By department');

        // A status filter counts the same records the table lists.
        $filtered = $this->actingAs($this->admin)
            ->get('/admin/reports?category=inventory&status=For Repair')->assertOk();

        $this->assertMatchesRegularExpression('/data-report-total[^>]*>1</s', $filtered->getContent());
        $filtered->assertSee('AST-4000');
        $filtered->assertDontSee('AST-0001');
    }

    public function test_a_report_with_no_matches_says_so(): void
    {
        $page = $this->actingAs($this->admin)
            ->get('/admin/reports?category=disposals')->assertOk();

        $page->assertSee('No records found for the selected filters');
        $page->assertSee('0 matching records');
        $this->assertMatchesRegularExpression('/data-report-total[^>]*>0</s', $page->getContent());
    }

    /* ═════════════ 7. export ═════════════ */

    public function test_the_export_holds_every_matching_record_not_only_the_page(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->asset('AST-5' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'Bulk asset ' . $i, $this->employee->id, [
                'accusion_date' => now()->toDateString(),
            ]);
        }

        $page = $this->actingAs($this->admin)->get('/admin/reports?category=inventory')->assertOk();

        // 32 assets altogether, 25 on the screen, and the export link carries
        // the criteria the page was generated with.
        preg_match_all('/data-report-row/', $page->getContent(), $rows);
        $this->assertCount(25, $rows[0], 'the table is paged at 25 rows');
        $page->assertSee('page=2', false);
        $page->assertSee('/admin/reports/export?category=inventory', false);

        $csv = $this->actingAs($this->admin)
            ->get('/admin/reports/export?category=inventory')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AST-5000', $csv);
        $this->assertStringContainsString('AST-5029', $csv);
        $this->assertStringContainsString('AST-9001', $csv);

        $lines = array_map('str_getcsv', preg_split('/\r\n|\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv))));

        // The criteria travel with the file…
        $this->assertContains('Report', array_column($lines, 0));
        $this->assertContains('Date field', array_column($lines, 0));

        // …and the header row names the columns the table shows.
        $headerRow = array_values(array_filter($lines, fn ($line) => ($line[0] ?? '') === 'Report generated at'));
        $this->assertCount(1, $headerRow);
        $this->assertSame(
            ['Report generated at', 'Asset code', 'Asset name', 'Category', 'Accountable employee', 'Department', 'Date acquired', 'Location', 'Condition', 'Current status'],
            $headerRow[0]
        );

        // Every record is in it: 32 assets plus the header and the criteria block.
        $dataRows = array_values(array_filter($lines, fn ($line) => str_starts_with((string) ($line[1] ?? ''), 'AST-')));
        $this->assertCount(32, $dataRows);
    }

    public function test_the_export_uses_the_applied_criteria_and_refuses_foreign_departments(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports?category=inventory&department=2')->assertOk();

        $csv = $this->actingAs($this->admin)
            ->get('/admin/reports/export?category=inventory&department=2')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AST-9001', $csv);
        $this->assertStringNotContainsString('AST-0001', $csv);

        // A department head's export can never hold another department's asset,
        // whatever the URL asks for.
        $headCsv = $this->actingAs($this->head)
            ->get('/department-head/reports/export?category=inventory&department=2')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AST-0001', $headCsv);
        $this->assertStringNotContainsString('AST-9001', $headCsv);
    }

    public function test_an_exported_value_can_never_be_run_as_a_spreadsheet_formula(): void
    {
        $this->asset('AST-6000', '=HYPERLINK("http://evil","click")', $this->employee->id, [
            'accusion_date' => now()->toDateString(),
        ]);

        $csv = $this->actingAs($this->admin)
            ->get('/admin/reports/export?category=inventory')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_the_export_filename_names_the_report(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reports/export?category=repairs')
            ->assertOk()
            ->assertHeader(
                'content-disposition',
                'attachment; filename="nu-trace-repairs-report-' . now()->format('Y-m-d') . '.csv"'
            );
    }

    /* ═════════════ 8. print ═════════════ */

    public function test_the_print_sheet_holds_every_record_and_the_report_metadata(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->asset('AST-7' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'Print asset ' . $i, $this->employee->id, [
                'accusion_date' => now()->toDateString(),
            ]);
        }

        $sheet = $this->actingAs($this->admin)
            ->get('/admin/reports/print?category=inventory')
            ->assertOk();

        $sheet->assertSee('Current Asset Inventory');
        $sheet->assertSee('Generated');
        $sheet->assertSee('Date field');
        $sheet->assertSee('Assets in this report');
        $sheet->assertSee('NU Trace');

        // Every record, including the ones past the first page…
        $sheet->assertSee('AST-7000');
        $sheet->assertSee('AST-7029');
        $this->assertStringContainsString('AST-9001', $sheet->getContent());

        // …and none of the application's own chrome.
        $sheet->assertDontSee('Employee Management');
        $sheet->assertDontSee('Logout');
        $sheet->assertDontSee('Apply Filters');
    }

    public function test_the_print_sheet_stays_inside_its_scope(): void
    {
        $sheet = $this->actingAs($this->head)
            ->get('/department-head/reports/print?category=inventory')
            ->assertOk();

        $this->assertStringContainsString('AST-0001', $sheet->getContent());
        $this->assertStringNotContainsString('AST-9001', $sheet->getContent());
        $sheet->assertSee('Admin office');
    }

    /* ═════════════ 9. applying, and failing to apply ═════════════ */

    public function test_filters_take_effect_only_when_they_are_applied(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/reports?category=inventory')->assertOk();

        // The controls post to the generate endpoint; nothing on this page
        // regenerates itself, and the export and print links carry the applied
        // criteria rather than whatever the controls currently say.
        $page->assertSee('action="/admin/reports/generate"', false);
        $page->assertSee('method="POST"', false);
        $page->assertSee('Filters changed', false);
        $page->assertSee('data-report-export', false);
        $page->assertSee('/admin/reports/print?category=inventory', false);

        // Applying redirects to a URL that names the criteria, so the report the
        // reader is looking at is the one the link says it is.
        $this->actingAs($this->admin)
            ->post('/admin/reports/generate', [
                'category' => 'inventory',
                'preset'   => 'this_month',
                'status'   => 'Active',
            ])
            ->assertRedirect('/admin/reports?category=inventory&preset=this_month&status=Active');

        $applied = $this->actingAs($this->admin)
            ->get('/admin/reports?category=inventory&preset=this_month&status=Active')
            ->assertOk();

        // The form shows what is applied, so an edited control is visibly pending.
        $applied->assertSee('value="this_month" selected', false);
        $applied->assertSee('value="Active" selected', false);
    }

    /**
     * A generation that fails must not hand back the previous report as though
     * it were the one that was asked for — and must not let that older report be
     * exported or printed under the new criteria.
     */
    public function test_a_failed_generation_keeps_the_previous_report_and_says_so(): void
    {
        // A successful inventory report, remembered as the last applied one.
        $this->actingAs($this->admin)->get('/admin/reports?category=inventory')->assertOk();

        // The repairs report cannot be generated: the table it reads is gone.
        Schema::dropIfExists('repair_evaluations');
        Schema::dropIfExists('repairs');

        $failed = $this->actingAs($this->admin)->post('/admin/reports/generate', [
            'category' => 'repairs',
            'preset'   => 'all',
        ])->assertOk();

        // The failure is stated, and so is the fact that what is shown below is
        // the earlier report.
        $failed->assertSee('Repairs and Maintenance report could not be generated');
        $failed->assertSee('data-report-error', false);
        $failed->assertSee('data-report-stale', false);
        $failed->assertSee('Last successful report');

        // The displayed report is the inventory one — its own criteria, its own
        // total — and never the report that was asked for.
        $failed->assertSee('Current Asset Inventory');
        $failed->assertSee('AST-0001');

        // Its export and print are the inventory's, so the older report can
        // never leave the building as the repairs report.
        $failed->assertSee('/admin/reports/export?category=inventory', false);
        $failed->assertSee('/admin/reports/print?category=inventory', false);
        $failed->assertDontSee('/admin/reports/export?category=repairs', false);
        $failed->assertDontSee('/admin/reports/print?category=repairs', false);

        // And the reader can start again from a working report.
        $this->actingAs($this->admin)->get('/admin/reports?category=inventory')->assertOk();
    }

    /* ═════════════ 10. the module leaves the rest of the system alone ═════════════ */

    public function test_reading_reports_changes_nothing(): void
    {
        $this->disposal($this->ownAsset, null, now()->toDateString(), archived: false);

        // One signed-in request first: the sign-in itself is recorded once by
        // middleware that has nothing to do with reports, and is deliberately
        // counted before the snapshot rather than after it.
        $this->actingAs($this->admin)->get('/admin')->assertOk();

        $snapshot = fn (): array => [
            'assets'    => DB::table('assets')->count(),
            'requests'  => DB::table('requests')->count(),
            'disposals' => DB::table('disposals')->count(),
            'audit'     => DB::table('audit_logs')->count(),
        ];

        $before = $snapshot();

        $this->actingAs($this->admin)->get('/admin/reports')->assertOk();
        $this->actingAs($this->admin)->get('/admin/reports?category=disposals')->assertOk();
        $this->actingAs($this->admin)->get('/admin/reports/export?category=disposals')->assertOk();
        $this->actingAs($this->admin)->get('/admin/reports/print?category=disposals')->assertOk();
        $this->actingAs($this->head)->get('/department-head/reports?category=disposals')->assertOk();

        $this->assertSame($before, $snapshot(), 'reading a report must not change a single row');

        // The pages a report is built from still behave the way they did.
        $this->actingAs($this->admin)->get('/admin/disposal')->assertOk();
        $this->actingAs($this->admin)->get('/admin/inventory-download')->assertOk();
        $this->assertSame($before, $snapshot());
    }

    /* ═════════════ 11. navigation ═════════════ */

    public function test_reports_are_reachable_from_both_sidebars(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('/admin/reports', false);

        $this->actingAs($this->head)->get('/department-head')->assertOk()->assertSee('/department-head/reports', false);
    }

}
