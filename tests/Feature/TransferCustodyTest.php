<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Disposals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Who may be handed an asset — and which assets may be handed over at all.
 *
 * A transfer changes who is accountable for an asset that is still in service.
 * An asset that has left the employee's custody is not part of that question:
 * it is retired ('Disposal') or pulled out ('Pullout'), it is deliberately kept
 * out of every Assets listing, and the disposal or pullout record that removed
 * it still names it.
 *
 * The two doors that execute a transfer — the office approving a Transfer
 * request and the Executive Director approving a forwarded one — run the same
 * execution step, so the rule is enforced there, once, and not in only one of
 * them. The Asset Management Office's own Transfer page has always refused
 * these assets (AssetTransfer::create); approving a *request* for one used to
 * hand it over and write it back to 'Active', quietly undoing the disposal.
 *
 * The request itself can honestly go stale this way: the request form only
 * offers assets in custody when the employee submits it, and the asset can be
 * retired by another approved request before the office decides on it.
 */
class TransferCustodyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Nena Santos', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Rico Bautista', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Lito Uy', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin     = $this->account(3, 'amo@nu-lipa.edu.ph', 'Admin');
        $this->employee  = $this->account(1, 'nena@nu-lipa.edu.ph', 'Employee');
        $this->recipient = $this->account(2, 'rico@nu-lipa.edu.ph', 'Employee');
    }

    private function account(int $employeeNumberId, string $email, string $role): User
    {
        return User::create([
            'employee_numbers_id' => $employeeNumberId,
            'department_id'       => 1,
            'email'               => $email,
            'password'            => Hash::make('secret-password'),
            'role'                => $role,
            'status'              => 'Active',
        ]);
    }

    private function asset(string $code, string $name, string $status = 'Active'): int
    {
        return (int) DB::table('assets')->insertGetId([
            'user_id'          => $this->employee->id,
            'Asset_code'       => $code,
            'Asset_name'       => $name,
            'Category'         => 'Info and Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => $status,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** Submit a transfer request through the employee's own form. */
    private function submitTransfer(array $assetIds): int
    {
        $this->actingAs($this->employee)->post('/user/requests/store', [
            'request_type'      => 'Transfer',
            'asset_ids'         => $assetIds,
            'assign_to_user_id' => $this->recipient->id,
            'notes'             => 'Moving these to the new custodian.',
        ])->assertRedirect();

        return (int) DB::table('requests')->max('id');
    }

    /**
     * Retire the asset the way a finalized disposal does: the record is written
     * and the asset takes the one status the CHECK constraint accepts.
     */
    private function retire(int $assetId): void
    {
        DB::table('disposals')->insert([
            'Asset_id'        => $assetId,
            'Request_id'      => null,
            'Approve_by'      => 'amo@nu-lipa.edu.ph',
            'Description'     => 'Beyond repair',
            'disposal_reason' => 'Beyond Repair',
            'disposal_date'   => now()->toDateString(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('assets')->where('id', $assetId)->update([
            'Lifecycle_Status' => Disposals::DISPOSED_STATUS,
            'updated_at'       => now(),
        ]);
    }

    /** Everything an approval would have written, asserted away in one place. */
    private function assertNothingMoved(int $assetId, int $requestId): void
    {
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));

        $asset = DB::table('assets')->where('id', $assetId)->first();
        $this->assertSame($this->employee->id, (int) $asset->user_id, 'the asset must stay with its holder');
        $this->assertSame(
            Disposals::DISPOSED_STATUS,
            $asset->Lifecycle_Status,
            'a retired asset must not be written back into service'
        );

        $this->assertSame(0, DB::table('asset_accountability')->where('asset_id', $assetId)->count());
        $this->assertSame(0, DB::table('audit_logs')->where('request_id', $requestId)->where('action_type', 'TRANSFER')->count());
        $this->assertSame(0, DB::table('notifications')->where('user_id', $this->recipient->id)->count());
    }

    public function test_a_transfer_request_for_a_retired_asset_is_refused(): void
    {
        $assetId   = $this->asset('AST-0001', 'Dell Laptop');
        $requestId = $this->submitTransfer([$assetId]);

        // The asset is retired while the request waits for the office.
        $this->retire($assetId);

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(422);

        $this->assertStringContainsString('AST-0001', $response->json('message'));
        $this->assertStringContainsString('Disposal', $response->json('message'));

        $this->assertNothingMoved($assetId, $requestId);
    }

    public function test_a_transfer_request_for_a_pulled_out_asset_is_refused(): void
    {
        $assetId   = $this->asset('AST-0001', 'Epson Projector');
        $requestId = $this->submitTransfer([$assetId]);

        DB::table('assets')->where('id', $assetId)->update(['Lifecycle_Status' => 'Pullout', 'updated_at' => now()]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'AST-0001'));

        $asset = DB::table('assets')->where('id', $assetId)->first();
        $this->assertSame('Pullout', $asset->Lifecycle_Status);
        $this->assertSame($this->employee->id, (int) $asset->user_id);
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
    }

    public function test_the_assets_that_are_still_in_custody_are_not_moved_around_a_retired_one(): void
    {
        $retired    = $this->asset('AST-0001', 'Dell Laptop');
        $healthy    = $this->asset('AST-0002', 'Printer');
        $requestId  = $this->submitTransfer([$retired, $healthy]);

        $this->retire($retired);

        // One asset in the batch has left custody: the handover is refused as a
        // whole rather than half-performed around it.
        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(422);

        $this->assertNothingMoved($retired, $requestId);

        $stillHeld = DB::table('assets')->where('id', $healthy)->first();
        $this->assertSame($this->employee->id, (int) $stillHeld->user_id);
        $this->assertSame('Active', $stillHeld->Lifecycle_Status);
        $this->assertSame(0, DB::table('asset_accountability')->where('asset_id', $healthy)->count());
    }

    public function test_the_asset_management_office_cannot_hand_a_retired_asset_over_from_the_transfer_page_either(): void
    {
        $assetId = $this->asset('AST-0001', 'Dell Laptop');
        $this->retire($assetId);

        $this->actingAs($this->admin)
            ->post('/admin/transfer', [
                'from_user_id' => $this->employee->id,
                'to_user_id'   => $this->recipient->id,
                'asset_ids'    => [$assetId],
                'reason'       => 'Position Reassignment',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('transfer');

        $asset = DB::table('assets')->where('id', $assetId)->first();
        $this->assertSame($this->employee->id, (int) $asset->user_id);
        $this->assertSame(Disposals::DISPOSED_STATUS, $asset->Lifecycle_Status);
        $this->assertSame(0, DB::table('requests')->where('request_type', 'Transfer')->count());
        $this->assertSame(0, DB::table('asset_accountability')->where('asset_id', $assetId)->count());
    }

    public function test_a_transfer_request_for_assets_still_in_service_still_goes_through(): void
    {
        // 'For Repair' is still the employee's custody — an asset being mended
        // travels with them, so the rule must not refuse the ordinary case.
        $assetId   = $this->asset('AST-0001', 'Dell Laptop', 'For Repair');
        $requestId = $this->submitTransfer([$assetId]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();

        $asset = DB::table('assets')->where('id', $assetId)->first();
        $this->assertSame($this->recipient->id, (int) $asset->user_id);
        $this->assertSame('Active', $asset->Lifecycle_Status);
        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));

        // The handover is recorded on both sides of the relocation.
        $this->assertSame(1, DB::table('asset_accountability')
            ->where('asset_id', $assetId)->where('user_id', $this->recipient->id)->where('Is_Current', 1)->count());
        $this->assertSame(1, DB::table('audit_logs')
            ->where('request_id', $requestId)->where('action_type', 'TRANSFER')->count());
    }
}
