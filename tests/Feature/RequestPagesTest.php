<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The three Request pages, and the three things the office asked for on them:
 *
 *   1. a request list is paged — 15 rows per page, never "page 1 shows nothing"
 *   2. a Pending request is always on top (it is the only one still waiting on
 *      the Asset Management Office, and the only one with Approve / Reject),
 *      and the one that has been waiting longest is the first row
 *   3. each role keeps its own pages: the employee never lands on the
 *      department-head form (or the other way round), and an empty list links to
 *      that role's own "submit a request" page instead of a URL that 404s.
 *
 * The last one shipped broken: the department head's empty state pointed at
 * `/user/requests/create`, a route that has never existed.
 */
class RequestPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private User $head;
    private User $outsider;
    private int $assetId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Other Dept', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'alex@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'nena@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->head = User::create([
            'employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Department Head', 'status' => 'Active',
        ]);

        $this->outsider = User::create([
            'employee_numbers_id' => 4, 'department_id' => 2, 'email' => 'other@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Good', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ── helpers ────────────────────────────────────────────────────────── */

    /** A request plus the request_items row the forms always write. */
    private function makeRequest(User $owner, string $status, Carbon $createdAt, string $type = 'Repair'): int
    {
        $id = (int) DB::table('requests')->insertGetId([
            'user_id' => $owner->id, 'asset_id' => null, 'request_type' => $type,
            'status' => $status, 'Note' => 'Please look into this asset.',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        DB::table('request_items')->insert([
            'request_id' => $id, 'asset_id' => $this->assetId,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        return $id;
    }

    /** The request ids in the order the admin table renders them. */
    private function adminRowIds(string $html): array
    {
        preg_match_all('/data-request-id="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    /** The request ids in the order a role's card list renders them. */
    private function cardIds(string $html): array
    {
        $start = strpos($html, 'id="requestsList"');
        $end   = strpos($html, '<!-- View Modal -->');
        $body  = substr($html, (int) $start, (int) $end - (int) $start);
        preg_match_all('/openViewModal\((\d+)\)/', $body, $matches);

        return array_map('intval', $matches[1]);
    }

    /* ── the admin Requests page ────────────────────────────────────────── */

    public function test_the_admin_request_page_shows_fifteen_rows_with_pending_first(): void
    {
        // The pending ones are deliberately the OLDEST, so "pending first" can
        // only come from the status ordering, not from the date ordering.
        // They are built oldest-first, which is also the order they must be
        // listed in: the longest-waiting request is the first row.
        $pending = [];
        for ($i = 0; $i < 8; $i++) {
            $pending[] = $this->makeRequest($this->employee, 'Pending', now()->subDays(30 - $i));
        }

        $approved = [];
        for ($i = 0; $i < 20; $i++) {
            $approved[] = $this->makeRequest($this->employee, 'Approved', now()->subMinutes(40 - $i * 2));
        }

        $page1 = $this->actingAs($this->admin)->get('/admin/requests')->assertOk();

        $ids = $this->adminRowIds($page1->getContent());
        $this->assertCount(15, $ids, 'the admin request table must be 15 rows per page');

        // 8 pending (oldest waiting first), then the 7 newest approved.
        $expected = array_merge(
            $pending,
            array_slice(array_reverse($approved), 0, 7)
        );
        $this->assertSame($expected, $ids, 'pending requests must sit above decided ones, longest wait first');

        $page1->assertSee('Showing 1–15')
            ->assertSee('of 28 requests')
            ->assertSee('page=2', false);

        // The stat cards count the whole table, not the visible page.
        $this->assertMatchesRegularExpression(
            '/Total Requests.*?font-bold"[^>]*>28</s',
            $page1->getContent(),
            'the Total Requests card must count every request'
        );
        $this->assertMatchesRegularExpression(
            '/>Pending<.*?font-bold"[^>]*>8</s',
            $page1->getContent(),
            'the Pending card must count every pending request'
        );

        // The tabs are real links (the server filters), and the one dashboard
        // links into (?tab=pending) is recognised.
        $page1->assertSee('tab=pending', false)->assertSee('tab=rejected', false);
        $this->actingAs($this->admin)->get('/admin/requests?tab=pending')->assertOk()
            ->assertSee('tab-active', false);

        $page2 = $this->actingAs($this->admin)->get('/admin/requests?page=2')->assertOk();
        $rest  = $this->adminRowIds($page2->getContent());

        $this->assertCount(13, $rest);
        $this->assertSame(array_slice(array_reverse($approved), 7), $rest);
        $this->assertSame([], array_intersect($ids, $rest), 'no request may appear on two pages');
    }

    /**
     * The order the office asked for: the request that has been pending longest
     * is the top row, and everything already decided follows it, newest first.
     *
     * The dates are interleaved, so neither half of that order can be produced
     * by a single date ordering on its own.
     */
    public function test_the_longest_waiting_pending_request_is_the_first_row(): void
    {
        $oldestPending = $this->makeRequest($this->employee, 'Pending', now()->subDays(9));
        $olderApproved = $this->makeRequest($this->employee, 'Approved', now()->subDays(7));
        $middlePending = $this->makeRequest($this->employee, 'Pending', now()->subDays(5));
        $newerApproved = $this->makeRequest($this->employee, 'Approved', now()->subDays(3));
        $newestPending = $this->makeRequest($this->employee, 'Pending', now()->subDay());

        // Three pending rows oldest-first, then the two decided ones newest-first.
        $expected = [$oldestPending, $middlePending, $newestPending, $newerApproved, $olderApproved];

        $ids = $this->adminRowIds(
            $this->actingAs($this->admin)->get('/admin/requests')->assertOk()->getContent()
        );

        $this->assertSame(
            $expected,
            $ids,
            'pending rows read oldest-waiting-first; decided rows read newest-first'
        );

        // The same rule on the two user-side lists.
        $headIds = $this->cardIds(
            $this->actingAs($this->head)->get('/department-head/requests')->assertOk()->getContent()
        );
        $this->assertSame($expected, $headIds);

        $employeeIds = $this->cardIds(
            $this->actingAs($this->employee)->get('/user/requests')->assertOk()->getContent()
        );
        $this->assertSame($expected, $employeeIds);
    }

    public function test_the_admin_status_tabs_filter_before_the_page_is_cut(): void
    {
        // Three rejected requests, then 20 newer approved ones: with a filter
        // applied to the rendered rows only, the Rejected tab would come up empty.
        $rejected = [];
        for ($i = 0; $i < 3; $i++) {
            $rejected[] = $this->makeRequest($this->employee, 'Rejected', now()->subDays(20 - $i), 'Disposal');
        }
        for ($i = 0; $i < 20; $i++) {
            $this->makeRequest($this->employee, 'Approved', now()->subMinutes(40 - $i));
        }

        $page = $this->actingAs($this->admin)->get('/admin/requests?tab=rejected')->assertOk();

        $this->assertSame(array_reverse($rejected), $this->adminRowIds($page->getContent()));
        $page->assertSee('Showing 1–3')->assertSee('of 3 requests');

        // Pending still wins the top spot when the tab shows everything.
        $pendingId = $this->makeRequest($this->employee, 'Pending', now()->subYear());
        $all = $this->adminRowIds(
            $this->actingAs($this->admin)->get('/admin/requests')->assertOk()->getContent()
        );
        $this->assertSame($pendingId, $all[0], 'a pending request is never buried under older rows');
    }

    /* ── the department head's own list ─────────────────────────────────── */

    public function test_the_department_head_list_pages_fifteen_with_pending_on_top(): void
    {
        $own = [];
        for ($i = 0; $i < 6; $i++) {
            $own[] = $this->makeRequest($this->employee, 'Pending', now()->subDays(40 - $i));
        }
        $approved = [];
        for ($i = 0; $i < 12; $i++) {
            $approved[] = $this->makeRequest($this->employee, 'Approved', now()->subMinutes(30 - $i));
        }

        // Another department's request must never show up on this list.
        $foreign = $this->makeRequest($this->outsider, 'Pending', now());

        $page1 = $this->actingAs($this->head)->get('/department-head/requests')->assertOk();
        $ids   = $this->cardIds($page1->getContent());

        $this->assertCount(15, $ids, 'the department head list must be 15 per page');
        $this->assertSame(
            array_merge($own, array_slice(array_reverse($approved), 0, 9)),
            $ids
        );
        $this->assertNotContains($foreign, $ids);

        // The paginator is rendered, and keeps the filter in its links.
        $page1->assertSee('page=2', false);

        $page2 = $this->actingAs($this->head)->get('/department-head/requests?page=2')->assertOk();
        $this->assertCount(3, $this->cardIds($page2->getContent()));
    }

    public function test_the_employee_list_pages_fifteen_with_pending_on_top(): void
    {
        $pending = [];
        for ($i = 0; $i < 5; $i++) {
            $pending[] = $this->makeRequest($this->employee, 'Pending', now()->subDays(30 - $i));
        }
        $approved = [];
        for ($i = 0; $i < 15; $i++) {
            $approved[] = $this->makeRequest($this->employee, 'Approved', now()->subMinutes(30 - $i));
        }

        // Somebody else's request must not appear on the employee's own list.
        $foreign = $this->makeRequest($this->outsider, 'Pending', now());

        $page1 = $this->actingAs($this->employee)->get('/user/requests')->assertOk();
        $ids   = $this->cardIds($page1->getContent());

        $this->assertCount(15, $ids);
        $this->assertSame(
            array_merge($pending, array_slice(array_reverse($approved), 0, 10)),
            $ids
        );
        $this->assertNotContains($foreign, $ids);

        $page2 = $this->actingAs($this->employee)->get('/user/requests?page=2')->assertOk();
        $this->assertCount(5, $this->cardIds($page2->getContent()));
    }

    public function test_an_empty_request_list_links_to_that_roles_own_form(): void
    {
        $this->makeRequest($this->employee, 'Pending', now());

        // "Rejected" is empty for the head — the state the bug report came from.
        $head = $this->actingAs($this->head)
            ->get('/department-head/requests?status=Rejected')
            ->assertOk();

        $head->assertSee('Submit Your First Request');
        $head->assertSee(route('department_head.request-asset'), false);
        $head->assertDontSee('/user/requests/create', false);

        $employee = $this->actingAs($this->employee)
            ->get('/user/requests?status=Rejected')
            ->assertOk();

        $employee->assertSee('Submit Your First Request');
        $employee->assertSee(route('user.request-asset'), false);
    }

    /* ── each role keeps its own submit page ────────────────────────────── */

    public function test_the_department_head_form_is_its_own_page_with_its_own_people(): void
    {
        $page = $this->actingAs($this->head)
            ->get('/department-head/request-asset')
            ->assertOk();

        // It posts to the department-head endpoint …
        $page->assertSee(route('department_head.requests.store'), false);
        $page->assertDontSee(route('user.requests.store'), false);

        // … and offers this department's people as transfer targets, nobody else.
        $page->assertSee('Aling Nena');
        $page->assertDontSee('Other Dept');
    }

    public function test_the_employee_form_stays_the_employee_page(): void
    {
        $this->actingAs($this->employee)
            ->get('/user/request-asset')
            ->assertOk()
            ->assertSee(route('user.requests.store'), false);
    }

    public function test_a_department_head_never_lands_on_the_employee_form(): void
    {
        $this->actingAs($this->head)
            ->get('/user/request-asset')
            ->assertRedirect(route('department_head.request-asset'));
    }

    public function test_an_employee_cannot_open_the_department_head_page(): void
    {
        $this->actingAs($this->employee)
            ->get('/department-head/request-asset')
            ->assertForbidden();
    }
}
