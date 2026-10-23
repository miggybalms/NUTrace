<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Reviewers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The approval workflow, locked down (panel recommendation 3).
 *
 * Every test goes through the same door the browser uses: a real HTTP request
 * to a real route, with an account that really is (or really is not) allowed to
 * make it. Nothing here calls a controller directly, because the whole point of
 * the recommendation is that hiding a button is not access control - the server
 * has to refuse the request no matter who sends it.
 */
class ApprovalRbacTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $facilities;
    private User $employee;
    private User $head;
    private User $otherHead;
    private int $assetId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Alex Reyes', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Nena Santos', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dina Cruz', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Rico Bautista', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'Employee_number' => 'EMP-5', 'Full_Name' => 'Lito Uy', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = $this->account(1, 1, 'amo@nu-lipa.edu.ph', 'Admin');

        // An account still holding the retired Facilities role, the way one
        // created before the role was retired would look. It is used here to
        // prove such a row is understood but has no office authority left.
        $this->facilities = $this->account(5, 1, 'facilities@nu-lipa.edu.ph', 'Facilities');
        $this->employee = $this->account(2, 2, 'nena@nu-lipa.edu.ph', 'Employee');
        $this->head = $this->account(3, 2, 'head@nu-lipa.edu.ph', 'Department Head');
        $this->otherHead = $this->account(4, 2, 'head2@nu-lipa.edu.ph', 'Department Head');

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Fair', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function account(int $employeeNumberId, int $departmentId, string $email, string $role, string $status = 'Active'): User
    {
        return User::create([
            'employee_numbers_id' => $employeeNumberId,
            'department_id'       => $departmentId,
            'email'               => $email,
            'password'            => Hash::make('secret-password'),
            'role'                => $role,
            'status'              => $status,
        ]);
    }

    /** Submit a Repair request the way the employee form does. */
    private function submitRequest(
        ?User $submitter = null,
        ?int $assetId = null,
        string $note = 'The screen flickers and the hinge is loose.'
    ): int {
        $submitter = $submitter ?? $this->employee;

        Storage::fake('public');

        $this->actingAs($submitter)->post('/user/requests/store', [
            'request_type' => 'Repair',
            'asset_ids'    => [$assetId ?? $this->assetId],
            'notes'        => $note,
            'attachment'   => $this->requestPhoto(),
        ])->assertRedirect();

        return (int) DB::table('requests')->max('id');
    }

    /* ═══════════ 3.1 — the existing rules, still in force ═══════════ */

    public function test_the_asset_management_office_can_approve(): void
    {
        $requestId = $this->submitRequest();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();

        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));

        // The repair record and the asset's lifecycle are the existing
        // post-approval behaviour, and they still happen.
        $this->assertSame(1, DB::table('repairs')->where('Request_id', $requestId)->count());
        $this->assertSame('For Repair', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $requestId = $this->submitRequest();

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/reject", ['reason' => 'Changed my mind.'])
            ->assertStatus(422);

        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));
    }

    /* ═══════════ 3.2 — nobody decides their own request ═══════════ */

    public function test_an_administrator_cannot_approve_their_own_request(): void
    {
        // Requested against an asset the Admin is accountable for, so the
        // request really is theirs.
        $ownAsset = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->admin->id, 'Asset_code' => 'AST-0002', 'Asset_name' => 'Office Chair',
            'Category' => 'Furnitures and Fixtures', 'Condition' => 'Good', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The office form only lists an employee's own assets, so this is built
        // the way the row would look if the Admin had submitted it.
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->admin->id, 'asset_id' => null, 'request_type' => 'Repair',
            'status' => 'Pending', 'Note' => 'My own chair squeaks.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('request_items')->insert([
            'request_id' => $requestId, 'asset_id' => $ownAsset, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(403)
            ->assertJsonPath('message', 'You cannot approve or reject your own request. It has to be decided by the Asset Management Office.');

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame('Active', DB::table('assets')->where('id', $ownAsset)->value('Lifecycle_Status'));

        // The attempt is in the audit log, next to the approvals.
        $entry = DB::table('audit_logs')
            ->where('request_id', $requestId)
            ->where('notes', 'like', 'Denied:%')
            ->first();

        $this->assertNotNull($entry, 'a denied self-approval must be recorded');
        $this->assertStringContainsString('submitted themselves', $entry->notes);
    }

    public function test_an_administrator_cannot_reject_their_own_request(): void
    {
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->admin->id, 'asset_id' => $this->assetId, 'request_type' => 'Repair',
            'status' => 'Pending', 'Note' => 'Mine.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/reject", ['reason' => 'No.'])
            ->assertStatus(403);

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
    }

    public function test_the_submitter_is_read_from_the_database_not_the_request_body(): void
    {
        // The Admin's own request, with a body that claims somebody else
        // submitted it. The row decides, so the refusal stands.
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->admin->id, 'asset_id' => $this->assetId, 'request_type' => 'Repair',
            'status' => 'Pending', 'Note' => 'Mine, dressed up as somebody else\'s.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve", [
                'user_id'      => $this->employee->id,
                'submitted_by' => 'nena@nu-lipa.edu.ph',
            ])
            ->assertStatus(403);

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
    }

    /* ═══════════ 3.3 / 3.5 — who reaches which area ═══════════ */

    public function test_an_employee_cannot_reach_the_admin_area(): void
    {
        $this->actingAs($this->employee)->get('/admin/requests')->assertStatus(403);
        $this->actingAs($this->employee)->get('/admin')->assertStatus(403);
        $this->actingAs($this->employee)->get('/admin/audit-logs')->assertStatus(403);
        $this->actingAs($this->employee)->get('/admin/assets')->assertStatus(403);
    }

    public function test_an_employee_cannot_decide_a_request_by_posting_the_url(): void
    {
        $requestId = $this->submitRequest();

        $this->actingAs($this->employee)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(403);

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame(0, DB::table('repairs')->where('Request_id', $requestId)->count());
    }

    public function test_a_department_head_keeps_their_own_pages_and_not_the_admin_ones(): void
    {
        $this->actingAs($this->head)->get('/department-head')->assertOk();
        $this->actingAs($this->head)->get('/department-head/requests')->assertOk();
        $this->actingAs($this->head)->get('/admin/requests')->assertStatus(403);
        $this->actingAs($this->head)->get('/admin/employees')->assertStatus(403);
    }

    public function test_a_department_head_only_sees_their_own_departments_requests(): void
    {
        // A request from an account in another department, which is not this
        // head's. A different note on each one, so the page can be read for
        // which request it shows.
        $outsiderAsset = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->facilities->id, 'Asset_code' => 'AST-0900', 'Asset_name' => 'Printer',
            'Category' => 'General and Office Equipment', 'Condition' => 'Good', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->submitRequest($this->facilities, $outsiderAsset, 'The printer will not turn on at all.');
        $this->submitRequest($this->employee, $this->assetId, 'The screen flickers and the hinge is loose.');

        $response = $this->actingAs($this->head)->get('/department-head/requests');

        $response->assertOk()
            ->assertSee('The screen flickers and the hinge is loose.')
            ->assertDontSee('The printer will not turn on at all.');
    }

    /* ═══════════ 3.7 — account status ═══════════ */

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/status", ['status' => 'Inactive'])
            ->assertOk();

        $this->post('/login', ['email' => 'nena@nu-lipa.edu.ph', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        // The session that was opened is closed again: the next private page is
        // a visitor's request.
        $this->get('/users')->assertRedirect('/');
    }

    public function test_a_deactivated_account_is_signed_out_of_the_session_it_already_had(): void
    {
        DB::table('users')->where('id', $this->employee->id)->update(['status' => 'Inactive']);

        // The account is loaded again, the way the next request would load it:
        // the decision is made from the row, not from what was read at sign-in.
        $this->actingAs(User::find($this->employee->id))->get('/users')->assertRedirect('/');

        $this->assertGuest();

        $this->get('/users')->assertRedirect('/');
    }

    public function test_reactivating_an_account_restores_access(): void
    {
        DB::table('users')->where('id', $this->employee->id)->update(['status' => 'Inactive']);

        $this->post('/login', ['email' => 'nena@nu-lipa.edu.ph', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        DB::table('users')->where('id', $this->employee->id)->update(['status' => 'Active']);

        $this->post('/login', ['email' => 'nena@nu-lipa.edu.ph', 'password' => 'secret-password'])
            ->assertRedirect('/users');

        $this->assertAuthenticatedAs(User::find($this->employee->id));
    }

    /* ═══════════ 3.6 — a change of role takes effect at once ═══════════ */

    public function test_a_demoted_account_loses_the_authority_it_had(): void
    {
        $requestId = $this->submitRequest();

        // A second administrator, so this test can demote one without the
        // page's own "last administrator" rule getting in the way of the raw
        // update below.
        DB::table('employee_numbers')->insert([
            ['id' => 6, 'Employee_number' => 'EMP-6', 'Full_Name' => 'Mara Diaz', 'Department_id' => 1,
             'created_at' => now(), 'updated_at' => now()],
        ]);

        $second = $this->account(6, 1, 'amo2@nu-lipa.edu.ph', 'Admin');

        // This administrator can decide today…
        $this->actingAs($second)->get('/admin/requests')->assertOk();

        DB::table('users')->where('id', $second->id)->update(['role' => 'Employee']);

        // …and the very next request is refused, without anything being cached
        // from the sign-in.
        $this->actingAs(User::find($second->id))->get('/admin/requests')->assertStatus(403);

        $this->actingAs(User::find($second->id))
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(403);

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
    }

    public function test_pending_requests_are_reported_when_no_reviewer_is_left(): void
    {
        $this->submitRequest();

        // The only two accounts that could decide it are switched off.
        DB::table('users')->whereIn('id', [$this->admin->id, $this->facilities->id])->update(['status' => 'Inactive']);

        $this->assertSame(1, \App\Support\AccountAdmin::pendingWithoutReviewer());

        // Reactivating one clears the flag, and the request was preserved
        // throughout - never reassigned or deleted.
        DB::table('users')->where('id', $this->admin->id)->update(['status' => 'Active']);

        $this->assertSame(0, \App\Support\AccountAdmin::pendingWithoutReviewer());
        $this->assertSame(1, DB::table('requests')->whereRaw('LOWER(status) = ?', ['pending'])->count());
    }

    /* ═══════════ 3.8 — notifications ═══════════ */

    public function test_submitting_a_request_notifies_the_reviewers_and_not_the_submitter(): void
    {
        $requestId = $this->submitRequest();

        $officeNotice = DB::table('notifications')
            ->where('user_id', $this->admin->id)
            ->where('reference_id', $requestId)
            ->where('reference_type', 'request')
            ->first();

        $this->assertNotNull($officeNotice, 'the office must hear about a request that is waiting for it');
        $this->assertSame('New Repair Request', $officeNotice->title);
        $this->assertStringContainsString('Laptop', $officeNotice->message);

        $headNotice = DB::table('notifications')
            ->where('user_id', $this->head->id)
            ->where('reference_id', $requestId)
            ->first();

        $this->assertNotNull($headNotice, "the submitter's own department head is told too");

        $this->assertSame(0, DB::table('notifications')
            ->where('user_id', $this->employee->id)
            ->where('reference_id', $requestId)
            ->count(), 'nobody is notified about their own submission');
    }

    public function test_a_deactivated_reviewer_is_not_notified(): void
    {
        DB::table('users')->where('id', $this->admin->id)->update(['status' => 'Inactive']);

        $requestId = $this->submitRequest();

        $this->assertSame(0, DB::table('notifications')
            ->where('user_id', $this->admin->id)
            ->where('reference_id', $requestId)
            ->count());
    }

    public function test_repeated_notices_for_the_same_request_are_not_duplicated(): void
    {
        $requestId = $this->submitRequest();

        $before = DB::table('notifications')->where('reference_id', $requestId)->count();

        // The same announcement, announced twice.
        Reviewers::notifyNewRequest($requestId, $this->employee, 'Repair', 'Laptop');

        $this->assertSame($before, DB::table('notifications')->where('reference_id', $requestId)->count());
    }

    public function test_the_requester_is_told_about_the_decision(): void
    {
        $requestId = $this->submitRequest();

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $notice = DB::table('notifications')
            ->where('user_id', $this->employee->id)
            ->where('reference_id', $requestId)
            ->where('title', 'Request Approved')
            ->first();

        $this->assertNotNull($notice, 'the submitter must hear that their request was approved');
    }

    /* ═══════════ 3.9 — the audit trail ═══════════ */

    public function test_a_decision_reaches_the_audit_log(): void
    {
        $requestId = $this->submitRequest();

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $approved = DB::table('audit_logs')
            ->where('request_id', $requestId)
            ->where('action_type', 'APPROVAL')
            ->where('user_id', $this->admin->id)
            ->first();

        $this->assertNotNull($approved);
        $this->assertStringContainsString('approved', strtolower((string) $approved->action_description));
    }

    public function test_an_attempt_from_outside_the_office_changes_nothing_at_all(): void
    {
        $requestId = $this->submitRequest();

        $this->actingAs($this->employee)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(403);

        // The refusal happens at the door of the admin area, so the account
        // never reached the route that could have decided anything: the request
        // is untouched and there is no decision to record against it. Inside
        // the area, refusals *are* recorded - see the self-approval tests.
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame(0, DB::table('repairs')->where('Request_id', $requestId)->count());
        $this->assertSame(0, DB::table('audit_logs')
            ->where('request_id', $requestId)
            ->where('action_type', 'APPROVAL')
            ->count());
    }

    public function test_a_rejection_is_recorded_with_its_reason(): void
    {
        $requestId = $this->submitRequest();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/reject", ['reason' => 'The unit is still under warranty.'])
            ->assertOk();

        $entry = DB::table('audit_logs')
            ->where('request_id', $requestId)
            ->where('action_type', 'APPROVAL')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('Rejected', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame('The unit is still under warranty.', DB::table('requests')->where('id', $requestId)->value('admin_remarks'));
    }

    /* ═══════════ the retired Facilities role ═══════════ */

    public function test_the_facilities_role_can_no_longer_be_assigned(): void
    {
        // The role endpoint is the only door a role comes through, and it
        // refuses a retired role the same way it refuses an invented one.
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/role", ['role' => 'Facilities'])
            ->assertStatus(422);

        $this->assertSame('Employee', User::find($this->employee->id)->role);

        $this->assertNotContains('Facilities', \App\Support\Access::ROLES);
        $this->assertFalse(\App\Support\Access::isKnownRole('Facilities'));
    }

    public function test_a_legacy_facilities_account_holds_no_office_authority(): void
    {
        // An account created before the retirement still reads correctly and
        // can still sign in as an ordinary account - but the office's area and
        // the office's decisions are no longer open to it.
        $this->assertSame('Facilities', User::find($this->facilities->id)->role);

        $this->actingAs($this->facilities)->get('/admin/requests')->assertStatus(403);
        $this->actingAs($this->facilities)->get('/admin/repair')->assertStatus(403);
        $this->actingAs($this->facilities)->get('/admin/assets')->assertStatus(403);
        $this->actingAs($this->facilities)->get('/admin/employees')->assertStatus(403);

        $requestId = $this->submitRequest();

        $this->actingAs($this->facilities)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(403);

        $this->actingAs($this->facilities)
            ->postJson("/admin/requests/{$requestId}/reject", ['reason' => 'Not covered by the maintenance budget.'])
            ->assertStatus(403);

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));

        // Nothing else about the account is broken: it still has its own pages.
        $this->actingAs($this->facilities)->get('/users')->assertOk();
    }

    public function test_the_office_is_the_administrator_role_alone(): void
    {
        $requestId = $this->submitRequest();

        // A department head of the submitter's own department is not the office.
        $this->actingAs($this->head)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(403);

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));

        // The administrator still decides, so nothing was taken away from the
        // office in the process.
        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();

        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));
    }
}
