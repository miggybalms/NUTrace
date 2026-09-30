<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Disposals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The whole chain, end to end:
 *
 *   user submits a Disposal request
 *     -> Admin approves it on the Requests page
 *     -> a disposal record is created from the request (never re-typed)
 *     -> the asset becomes Disposed but stays in the inventory
 *     -> the record appears on the Disposal page
 *     -> the Admin archives it
 *     -> it moves to Archived Disposal Assets, history intact
 *
 * The regression this locks down hardest is the one that hit production: the
 * Disposal page's delete action issued `delete from assets`, which PostgreSQL
 * refused (SQLSTATE 23503, request_items still referenced the asset) and which
 * destroyed an asset's history for the rows it did delete.
 */
class DisposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private int $assetId;
    private int $requestId;

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

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Fair', 'Lifecycle_Status' => 'Active',
            'purchase_Price' => 42000.00, 'accusion_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Submit a Disposal request exactly the way the user form does. */
    private function submitDisposalRequest(string $note = 'The unit is damaged beyond repair.'): int
    {
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id'      => $this->employee->id,
            'asset_id'     => null,
            'request_type' => 'Disposal',
            'status'       => 'Pending',
            'Note'         => $note,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('request_items')->insert([
            'request_id' => $requestId, 'asset_id' => $this->assetId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $requestId;
    }

    public function test_approving_a_disposal_request_creates_the_record_and_retires_the_asset(): void
    {
        $requestId = $this->submitDisposalRequest();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();

        $request = DB::table('requests')->where('id', $requestId)->first();
        $this->assertSame('Approved', $request->status);

        $disposal = DB::table('disposals')->where('Request_id', $requestId)->first();
        $this->assertNotNull($disposal, 'approving must create the disposal record');
        $this->assertSame($this->assetId, (int) $disposal->Asset_id);
        $this->assertSame($this->admin->email, $disposal->Approve_by);
        // The reason travels with the requester's note — the Admin does not retype it.
        $this->assertSame('Beyond Repair', $disposal->disposal_reason);
        $this->assertSame($request->Note, $disposal->notes);
        $this->assertSame(now()->toDateString(), (string) $disposal->disposal_date);
        $this->assertFalse((bool) $disposal->is_archived);

        // Retired, not deleted.
        $asset = DB::table('assets')->where('id', $this->assetId)->first();
        $this->assertNotNull($asset, 'a disposed asset must never be deleted');
        $this->assertSame(Disposals::DISPOSED_STATUS, $asset->Lifecycle_Status);

        // Audit + notification, in the existing systems.
        $audit = DB::table('audit_logs')->where('request_id', $requestId)->where('action_type', 'DISPOSAL')->first();
        $this->assertNotNull($audit);
        $this->assertSame(
            "Disposal request #{$requestId} was approved and a disposal record was created for asset AST-0001.",
            $audit->action_description
        );

        $notification = DB::table('notifications')->where('user_id', $this->employee->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('Disposal Request Approved', $notification->title);
        $this->assertSame(
            'Your disposal request for asset AST-0001 has been approved and recorded as disposed.',
            $notification->message
        );
        $this->assertSame('DISPOSAL', $notification->type);
    }

    public function test_approving_twice_does_not_create_a_second_disposal_record(): void
    {
        $requestId = $this->submitDisposalRequest();

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        // The second click is refused because the request is no longer Pending.
        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(422);

        $this->assertSame(1, DB::table('disposals')->where('Request_id', $requestId)->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $this->employee->id)->count());
    }

    public function test_a_disposal_container_with_no_reason_or_note_is_refused(): void
    {
        $requestId = $this->submitDisposalRequest('');

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(422);

        $this->assertSame(0, DB::table('disposals')->count());
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
    }

    public function test_a_disposal_request_with_no_asset_linked_fails_with_a_clear_error(): void
    {
        // request_items.asset_id is ON DELETE RESTRICT, so an asset cannot go
        // missing while a request points at it. This covers the other half of
        // the same edge case: a request that names nothing to retire.
        $requestId = $this->submitDisposalRequest();
        DB::table('request_items')->where('request_id', $requestId)->delete();

        $response = $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve");

        $response->assertStatus(422);
        $this->assertStringContainsString('No assets found', $response->json('message'));
        $this->assertSame(0, DB::table('disposals')->count());
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));

        // Nothing changed for the asset either.
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    public function test_rejecting_a_disposal_request_never_creates_a_record(): void
    {
        $requestId = $this->submitDisposalRequest();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/reject", ['reason' => 'Still under warranty.'])
            ->assertOk();

        $this->assertSame('Rejected', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame(0, DB::table('disposals')->count());
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    public function test_only_the_asset_management_office_can_approve(): void
    {
        $requestId = $this->submitDisposalRequest();

        $this->actingAs($this->employee)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(403);

        $this->assertSame(0, DB::table('disposals')->count());
    }

    public function test_archiving_moves_the_record_out_of_the_main_list_without_deleting_it(): void
    {
        $requestId = $this->submitDisposalRequest();
        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $disposalId = (int) DB::table('disposals')->where('Request_id', $requestId)->value('Disposal_ID');

        // It is on the main Disposal page …
        $this->actingAs($this->admin)->get('/admin/disposal')
            ->assertOk()
            ->assertSee('AST-0001')
            ->assertDontSee('Archived Disposal Asset</span>');

        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/{$disposalId}/archive")
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Disposal record archived successfully.']);

        // … and no longer there, but still on the archived page.
        $this->actingAs($this->admin)->get('/admin/disposal')
            ->assertOk()
            ->assertDontSee('AST-0001');

        $this->actingAs($this->admin)->get('/admin/disposal/archived')
            ->assertOk()
            ->assertSee('AST-0001');

        $disposal = DB::table('disposals')->where('Disposal_ID', $disposalId)->first();
        $this->assertNotNull($disposal, 'archiving must never delete the record');
        $this->assertTrue((bool) $disposal->is_archived);
        $this->assertNotNull($disposal->archived_at);
        $this->assertSame($this->admin->id, (int) $disposal->archived_by);

        $audit = DB::table('audit_logs')->where('action_type', 'UPDATE')
            ->where('action_description', 'like', '%was archived%')->first();
        $this->assertNotNull($audit);
        $this->assertSame(
            "Disposal record #{$disposalId} for asset AST-0001 was archived.",
            $audit->action_description
        );

        // The asset is untouched by archiving.
        $this->assertSame(
            Disposals::DISPOSED_STATUS,
            DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status')
        );
    }

    public function test_a_record_cannot_be_archived_twice(): void
    {
        $requestId = $this->submitDisposalRequest();
        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $disposalId = (int) DB::table('disposals')->where('Request_id', $requestId)->value('Disposal_ID');

        $this->actingAs($this->admin)->postJson("/admin/disposal/{$disposalId}/archive")->assertOk();
        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/{$disposalId}/archive")
            ->assertStatus(422);

        $this->assertSame(1, DB::table('disposals')->where('Disposal_ID', $disposalId)->count());
    }

    public function test_view_details_explains_the_disposal_and_keeps_the_history(): void
    {
        $requestId = $this->submitDisposalRequest();
        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        DB::table('asset_accountability')->insert([
            'asset_id' => $this->assetId, 'user_id' => $this->employee->id,
            'Assign_date' => now()->subMonths(6), 'Is_Current' => 1,
            'notes' => 'Issued to the registrar office.', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $disposalId = (int) DB::table('disposals')->where('Request_id', $requestId)->value('Disposal_ID');

        $response = $this->actingAs($this->admin)
            ->getJson("/admin/disposal/{$disposalId}/details")
            ->assertOk();

        $response->assertJsonPath('reason', 'Beyond Repair');
        $response->assertJsonPath('asset_name', 'Laptop');
        $response->assertJsonPath('asset_still_exists', true);
        $response->assertJsonPath('request.id', $requestId);
        $response->assertJsonPath('request.requester_email', 'juan@nu-lipa.edu.ph');
        $response->assertJsonPath('request.note', 'The unit is damaged beyond repair.');
        $this->assertCount(1, $response->json('history.accountability'));
        $this->assertCount(1, $response->json('history.disposals'));
    }

    public function test_the_delete_asset_action_is_gone(): void
    {
        $uris = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertNotContains(
            'admin/disposal/{id}/permanent-delete',
            $uris,
            'Deleting the asset from the Disposal page is what broke production.'
        );
        $this->assertContains('admin/disposal/{id}/archive', $uris);
        $this->assertContains('admin/disposal/archived', $uris);
    }

    public function test_the_recorded_lifecycle_value_is_one_the_database_accepts(): void
    {
        // assets.Lifecycle_Status is constrained to a fixed set that does NOT
        // include 'Disposed'. Writing 'Disposed' would be rejected by the CHECK
        // constraint in production, so the constant must stay on 'Disposal'.
        $this->assertSame('Disposal', Disposals::DISPOSED_STATUS);
    }
}
