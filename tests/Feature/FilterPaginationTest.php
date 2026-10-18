<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Every paged page, filtered.
 *
 * The failure this guards against is the one a pager makes easy: filter the
 * rows that happen to be on screen, then render page 2 from the unfiltered
 * query (or with the filter missing from the page links). Each test asks the
 * page itself for its second-page URL, follows that exact URL, and checks both
 * the filter in the query string and the rows that came back. A page that only
 * filters the first page fails here even though page 1 looks right.
 */
class FilterPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private User $head;
    private User $outsider;

    /** Asset codes that make each row's department identifiable. */
    private array $deptOneActive = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The login middleware writes its first LOGIN row after the response is
        // built, which would make the audit page's own count grow between the
        // two requests in the test. Mark the session as already logged so the
        // page is measured, not the act of arriving at it.
        $this->withSession(['login_logged' => true]);

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        // The four accounts these pages sign in as, plus enough masterlist rows
        // and accounts to push every listing past its first page.
        $people = [
            [1, 'EMP-01', 'Sir Alex', 1],
            [2, 'EMP-02', 'Aling Nena', 1],
            [3, 'EMP-03', 'Dept Head', 1],
            [4, 'EMP-04', 'Other Dept', 2],
            [5, 'EMP-05', 'Admin Two', 1],
        ];

        for ($i = 6; $i <= 22; $i++) {
            $people[] = [$i, 'EMP-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Zulu ' . $i, $i <= 20 ? 1 : 2];
        }

        foreach ($people as [$id, $number, $name, $department]) {
            DB::table('employee_numbers')->insert([
                'id' => $id, 'Employee_number' => $number, 'Full_Name' => $name,
                'Department_id' => $department, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->admin = $this->account(1, 1, 'alex@nu-lipa.edu.ph', 'Admin');
        $this->employee = $this->account(2, 1, 'nena@nu-lipa.edu.ph', 'Employee');
        $this->head = $this->account(3, 1, 'head@nu-lipa.edu.ph', 'Department Head');
        $this->outsider = $this->account(4, 2, 'other@nu-lipa.edu.ph', 'Employee');

        // A second administrator and seventeen employee accounts: the Accounts
        // tab filters by role, so a page-2 leak would put one of these on the
        // wrong side.
        $this->account(5, 1, 'admin.two@nu-lipa.edu.ph', 'Admin');

        for ($i = 6; $i <= 20; $i++) {
            $this->account($i, 1, 'user' . $i . '@nu-lipa.edu.ph', 'Employee');
        }

        $this->account(21, 2, 'user21@nu-lipa.edu.ph', 'Employee');

        // Department assets: 18 Active plus two Disposal records in Facilities,
        // and two Active assets in the other department.
        for ($i = 0; $i < 18; $i++) {
            $code = 'AST-' . (100 + $i);
            $this->deptOneActive[] = $code;
            $this->asset($code, $this->employee->id, 'Active');
        }

        $this->asset('AST-900', $this->employee->id, 'Disposal');
        $this->asset('AST-901', $this->employee->id, 'Disposal');
        $this->asset('AST-800', $this->outsider->id, 'Active');
        $this->asset('AST-801', $this->outsider->id, 'Active');

        // 18 Pending and 30 Approved requests for the employee, plus one Pending
        // request from the other department. The report page shows 25 at a time,
        // so the approved set has a real second page.
        for ($i = 0; $i < 18; $i++) {
            $this->request($this->employee, 'Pending', now()->subMinutes($i));
        }

        for ($i = 0; $i < 30; $i++) {
            $this->request($this->employee, 'Approved', now()->subHours(2)->subMinutes($i));
        }

        $this->request($this->outsider, 'Pending', now()->subMinutes(40));

        // 18 asset actions today and two sign-ins yesterday: the audit page's
        // date filter has to survive page 2 without mixing the two days.
        for ($i = 0; $i < 18; $i++) {
            DB::table('audit_logs')->insert([
                'user_id' => $this->admin->id,
                'asset_id' => DB::table('assets')->where('Asset_code', $this->deptOneActive[0])->value('id'),
                'action_type' => 'CREATE',
                'notes' => 'asset action ' . $i,
                'action_description' => 'Asset action ' . $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('audit_logs')->insert([
            [
                'user_id' => $this->admin->id, 'action_type' => 'LOGIN',
                'notes' => 'User logged in', 'action_description' => 'User logged in',
                'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
            ],
            [
                'user_id' => $this->admin->id, 'action_type' => 'LOGIN',
                'notes' => 'User logged in', 'action_description' => 'User logged in',
                'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
            ],
        ]);
    }

    private function account(int $employeeNumberId, int $departmentId, string $email, string $role): User
    {
        return User::create([
            'employee_numbers_id' => $employeeNumberId,
            'department_id' => $departmentId,
            'email' => $email,
            'password' => Hash::make('secret-password'),
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function asset(string $code, int $ownerId, string $status): void
    {
        DB::table('assets')->insert([
            'user_id' => $ownerId, 'Asset_code' => $code, 'Asset_name' => 'Asset ' . $code,
            'Category' => 'Info and Equipment', 'Condition' => 'Good',
            'Lifecycle_Status' => $status, 'asset_location' => 'Registrar Office',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function request(User $owner, string $status, Carbon $createdAt): void
    {
        $id = (int) DB::table('requests')->insertGetId([
            'user_id' => $owner->id, 'asset_id' => null, 'request_type' => 'Repair',
            'status' => $status, 'Note' => $status . ' request',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        DB::table('request_items')->insert([
            'request_id' => $id,
            'asset_id' => DB::table('assets')->where('Asset_code', 'AST-100')->value('id'),
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    /* ═════════════════════ Employees: Directory and Accounts ═════════════════════ */

    public function test_the_employee_directory_keeps_its_department_filter_on_page_two(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/employees?tab=directory&department=1')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $employees */
        $employees = $page->viewData('employees');

        $this->assertSame(19, $employees->total(), 'the department filter must count only Facilities records');
        $this->assertStringContainsString('department=1', $employees->url(2));

        $second = $this->actingAs($this->admin)->get('/admin/employees?tab=directory&department=1&page=2')->assertOk();
        $secondPage = $second->viewData('employees');

        $this->assertSame(19, $secondPage->total());
        $this->assertCount(4, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame(1, (int) $row->department_id);
        }

        // A Registrar-only employee must never appear on the Facilities page,
        // before or after paging.
        $second->assertDontSee('Zulu 21');
    }

    public function test_the_accounts_tab_keeps_its_role_filter_on_page_two(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/employees?tab=accounts&role=Employee')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $accounts */
        $accounts = $page->viewData('accounts');

        $this->assertSame(18, $accounts->total(), 'only Employee accounts may be counted');
        $this->assertStringContainsString('role=Employee', $accounts->url(2));
        $this->assertStringContainsString('accounts_page=2', $accounts->url(2));

        // The tab link itself has to carry the role too; clicking the Accounts
        // tab again must not turn the filter off.
        $page->assertSee('role=Employee', false);

        $second = $this->actingAs($this->admin)->get('/admin/employees?tab=accounts&role=Employee&accounts_page=2')->assertOk();
        $secondPage = $second->viewData('accounts');

        $this->assertSame(18, $secondPage->total());
        $this->assertCount(3, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame('Employee', $row->role);
        }

        $second->assertDontSee('admin.two@nu-lipa.edu.ph');
    }

    /* ═════════════════════════════════ Audit log ═════════════════════════════════ */

    public function test_the_audit_log_keeps_its_date_filter_on_page_two(): void
    {
        $date = now()->toDateString();

        $page = $this->actingAs($this->admin)->get('/admin/audit-logs?date=' . $date)->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $logs */
        $logs = $page->viewData('logs');

        $this->assertSame(18, $logs->total());
        $this->assertStringContainsString('date=' . $date, $logs->url(2));

        $second = $this->actingAs($this->admin)->get('/admin/audit-logs?date=' . $date . '&page=2')->assertOk();
        $secondPage = $second->viewData('logs');

        $this->assertSame(18, $secondPage->total());
        $this->assertCount(3, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame($date, Carbon::parse($row->created_at)->toDateString());
        }

        $second->assertDontSee('User logged in');
    }

    /* ═══════════════════════════ Department assets ═══════════════════════════ */

    public function test_the_department_asset_page_keeps_its_status_filter_on_page_two(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/assets/department/1?status=active')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $assets */
        $assets = $page->viewData('assets');

        $this->assertSame(18, $assets->total());
        $this->assertStringContainsString('status=active', $assets->url(2));

        $second = $this->actingAs($this->admin)->get('/admin/assets/department/1?status=active&page=2')->assertOk();
        $secondPage = $second->viewData('assets');

        $this->assertSame(18, $secondPage->total());
        $this->assertCount(8, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame('Active', $row->status_raw);
        }

        $second->assertDontSee('AST-900');
        $second->assertDontSee('AST-901');
    }

    /* ══════════════════════════════ Admin requests ══════════════════════════════ */

    public function test_the_admin_requests_queue_keeps_its_status_tab_on_page_two(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/requests?tab=pending')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $requests */
        $requests = $page->viewData('requests');

        $this->assertSame(19, $requests->total(), '18 employee requests plus the other department one');
        $this->assertStringContainsString('tab=pending', $requests->url(2));

        $second = $this->actingAs($this->admin)->get('/admin/requests?tab=pending&page=2')->assertOk();
        $secondPage = $second->viewData('requests');

        $this->assertSame(19, $secondPage->total());
        $this->assertCount(4, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            // The page normalises the stored status for its badges.
            $this->assertSame('pending', $row->status);
        }
    }

    /* ═══════════════════════ Employee and head requests ═══════════════════════ */

    public function test_the_employee_request_page_keeps_its_status_filter_on_page_two(): void
    {
        $page = $this->actingAs($this->employee)->get('/user/requests?status=Approved')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $requests */
        $requests = $page->viewData('requests');

        $this->assertSame(30, $requests->total());
        $this->assertStringContainsString('status=Approved', $requests->url(2));

        $second = $this->actingAs($this->employee)->get('/user/requests?status=Approved&page=2')->assertOk();
        $secondPage = $second->viewData('requests');

        $this->assertSame(30, $secondPage->total());
        $this->assertCount(15, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame('Approved', $row->status);
        }
    }

    public function test_the_department_head_request_page_keeps_its_status_filter_on_page_two(): void
    {
        $page = $this->actingAs($this->head)->get('/department-head/requests?status=Approved')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $requests */
        $requests = $page->viewData('requests');

        $this->assertSame(30, $requests->total());
        $this->assertStringContainsString('status=Approved', $requests->url(2));

        $second = $this->actingAs($this->head)->get('/department-head/requests?status=Approved&page=2')->assertOk();
        $secondPage = $second->viewData('requests');

        $this->assertSame(30, $secondPage->total());
        $this->assertCount(15, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame('Approved', $row->status);
        }

        // The other department's requests are never part of this listing.
        $second->assertDontSee('other@nu-lipa.edu.ph');
    }

    /* ═══════════════════════════════════ Reports ═══════════════════════════════════ */

    public function test_the_requests_report_keeps_its_status_filter_on_page_two(): void
    {
        $page = $this->actingAs($this->admin)
            ->get('/admin/reports?category=requests&status=Approved')
            ->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $rows */
        $rows = $page->viewData('report')['rows'];

        $this->assertSame(30, $rows->total());
        $this->assertStringContainsString('status=Approved', $rows->url(2));

        $second = $this->actingAs($this->admin)
            ->get('/admin/reports?category=requests&status=Approved&page=2')
            ->assertOk();

        $secondPage = $second->viewData('report')['rows'];

        $this->assertSame(30, $secondPage->total());
        $this->assertCount(5, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame('Approved', $row['status']);
        }
    }

    /* ═══════════════════════════════ Asset listings ═══════════════════════════════ */

    public function test_the_employee_asset_listing_keeps_its_tab_on_page_two(): void
    {
        $page = $this->actingAs($this->employee)->get('/users/assets?filter=Active')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $assets */
        $assets = $page->viewData('assignedAssets');

        $this->assertSame(18, $assets->total());
        $this->assertStringContainsString('filter=Active', $assets->url(2));

        $second = $this->actingAs($this->employee)->get('/users/assets?filter=Active&page=2')->assertOk();
        $secondPage = $second->viewData('assignedAssets');

        $this->assertSame(18, $secondPage->total());
        $this->assertCount(8, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame('Active', $row->Lifecycle_Status);
        }
    }

    public function test_the_department_head_asset_listing_keeps_its_tab_on_page_two(): void
    {
        $page = $this->actingAs($this->head)->get('/department-head/assets?filter=Active')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $assets */
        $assets = $page->viewData('assignedAssets');

        $this->assertSame(18, $assets->total());
        $this->assertStringContainsString('filter=Active', $assets->url(2));

        $second = $this->actingAs($this->head)->get('/department-head/assets?filter=Active&page=2')->assertOk();
        $secondPage = $second->viewData('assignedAssets');

        $this->assertSame(18, $secondPage->total());
        $this->assertCount(8, $secondPage->items());
        foreach ($secondPage->items() as $row) {
            $this->assertSame('Active', $row->Lifecycle_Status);
            $this->assertSame(1, (int) $row->department_id);
        }
    }
}
