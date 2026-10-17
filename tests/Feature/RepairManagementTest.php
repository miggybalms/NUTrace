<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Repair management (panel recommendation 1.2).
 *
 * The page has always shown every status, but its Delete action only removed
 * the card from the browser while telling the user the record was gone. These
 * tests hold the server to the words the interface uses: a closed repair can be
 * deleted for real, an open one cannot, and every status change reaches the
 * asset, the audit trail and the owner.
 */
class RepairManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private int $assetId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Alex Reyes', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Nena Santos', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'nena@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Fair', 'Lifecycle_Status' => 'Active',
            'asset_location' => 'Registrar Office', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Open a repair the way the admin page's modal does. */
    private function createRepair(): int
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/repairs/create', [
                'asset_id'          => $this->assetId,
                'issue_description' => 'The screen flickers and the hinge is loose.',
                'priority'          => 'high',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        return (int) DB::table('repairs')->max('Repair_id');
    }

    private function repair(int $id): ?object
    {
        return DB::table('repairs')->where('Repair_id', $id)->first();
    }

    /* ═══════════════════ creating and status changes ═══════════════════ */

    public function test_creating_a_repair_puts_the_asset_in_repair_and_audits_it(): void
    {
        $id = $this->createRepair();

        $this->assertNotNull($this->repair($id));
        $this->assertSame('Pending', $this->repair($id)->status);
        $this->assertSame('For Repair', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));

        $requestId = (int) $this->repair($id)->Request_id;
        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));

        $audit = DB::table('audit_logs')->where('asset_id', $this->assetId)->where('action_type', 'REPAIR')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('Repair #' . $id, $audit->action_description);
    }

    public function test_starting_a_repair_moves_it_to_in_progress_and_notifies_the_owner(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('In Progress', $this->repair($id)->status);
        $this->assertSame('For Repair', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));

        $notice = DB::table('notifications')->where('user_id', $this->employee->id)->first();
        $this->assertNotNull($notice);
        $this->assertSame('Repair Started', $notice->title);
    }

    public function test_repair_cannot_be_marked_completed_through_the_status_action(): void
    {
        $id = $this->createRepair();

        // Completion has to carry a documented result; the plain status
        // endpoint must not let a click skip that.
        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/status", ['status' => 'completed'])
            ->assertStatus(422);

        $this->assertSame('Pending', $this->repair($id)->status);
    }

    public function test_completing_a_repaired_asset_returns_it_to_active_and_notifies_the_owner(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/complete", [
                'repair_result' => 'repaired',
                'actual_cost'   => 1250.50,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $repair = $this->repair($id);
        $this->assertSame('Completed', $repair->status);
        $this->assertSame('Repairable', $repair->Repair_result);
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));

        $evaluation = DB::table('repair_evaluations')->where('repair_id', $id)->first();
        $this->assertNotNull($evaluation);
        $this->assertSame('Repairable', $evaluation->repair_result);

        $notice = DB::table('notifications')->where('user_id', $this->employee->id)->first();
        $this->assertNotNull($notice);
        $this->assertSame('Repair Completed', $notice->title);
    }

    public function test_a_repair_cannot_be_completed_without_a_result(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/complete", [])
            ->assertStatus(422);

        $this->assertSame('Pending', $this->repair($id)->status);
    }

    public function test_cancelling_a_repair_restores_the_asset_and_keeps_the_reason(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/status", [
                'status'              => 'cancelled',
                'cancellation_reason' => 'Parts are no longer available.',
            ])
            ->assertOk();

        $repair = $this->repair($id);
        $this->assertSame('Cancelled', $repair->status);
        $this->assertStringContainsString('Parts are no longer available.', $repair->notes);
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    /* ═════════════════════════ deleting a closed record ═════════════════════════ */

    public function test_deleting_a_cancelled_repair_removes_it_from_the_database(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/status", ['status' => 'cancelled', 'cancellation_reason' => 'Duplicate entry.'])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/delete")
            ->assertOk()
            ->assertJsonPath('success', true);

        // The old page only removed the card; the row must really be gone now.
        $this->assertNull($this->repair($id));
        $this->assertSame(0, DB::table('repair_evaluations')->where('repair_id', $id)->count());

        // The deletion itself stays in the audit trail, with no repair to point at.
        $audit = DB::table('audit_logs')->where('action_description', 'like', 'Repair #' . $id . ' deleted%')->first();
        $this->assertNotNull($audit);
    }

    public function test_an_open_repair_cannot_be_deleted(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/delete")
            ->assertStatus(422);

        $this->assertNotNull($this->repair($id));
        $this->assertSame('For Repair', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    public function test_a_repair_that_explains_a_waiting_replacement_cannot_be_deleted(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/complete", ['repair_result' => 'for_replacement'])
            ->assertOk();

        // The asset is still "For Repair" because the replacement has not
        // happened; deleting the record would erase the reason for that state.
        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/delete")
            ->assertStatus(422);

        $this->assertNotNull($this->repair($id));
    }

    public function test_an_employee_cannot_delete_a_repair(): void
    {
        $id = $this->createRepair();

        $this->actingAs($this->admin)
            ->postJson("/admin/repairs/{$id}/status", ['status' => 'cancelled', 'cancellation_reason' => 'Duplicate.'])
            ->assertOk();

        $this->actingAs($this->employee)
            ->postJson("/admin/repairs/{$id}/delete")
            ->assertForbidden();

        $this->assertNotNull($this->repair($id));
    }
}
