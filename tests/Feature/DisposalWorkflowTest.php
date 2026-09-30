<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Support\Disposals;
use App\Support\Inventory;
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

        $archived = $this->actingAs($this->admin)
            ->postJson("/admin/disposal/{$disposalId}/archive")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('asset_removed', true)
            ->assertJsonPath('asset_code', 'AST-0001');

        $this->assertStringContainsString('Disposal record archived successfully.', $archived->json('message'));
        $this->assertStringContainsString(
            'Asset AST-0001 has been removed from the inventory',
            $archived->json('message')
        );

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
            "Disposal record #{$disposalId} for asset AST-0001 was archived and the asset was removed from the inventory.",
            $audit->action_description
        );

        // The asset row is untouched by archiving — only its inventory state moved.
        $this->assertSame(
            Disposals::DISPOSED_STATUS,
            DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status')
        );
        $this->assertNotNull(DB::table('assets')->where('id', $this->assetId)->value('inventory_removed_at'));
    }

    public function test_archiving_works_as_a_plain_form_post_without_javascript(): void
    {
        // The Disposal page archives through an ordinary HTML form. A result the
        // browser can always complete matters more than a smooth fetch here: a
        // background request that is dropped leaves the Admin with a button that
        // silently does nothing.
        $requestId = $this->submitDisposalRequest();
        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $disposalId = (int) DB::table('disposals')->where('Request_id', $requestId)->value('Disposal_ID');

        $this->actingAs($this->admin)
            ->post("/admin/disposal/{$disposalId}/archive")
            ->assertRedirect('/admin/disposal')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Disposal record archived successfully.'));

        $this->assertTrue((bool) DB::table('disposals')->where('Disposal_ID', $disposalId)->value('is_archived'));

        // Doing it again explains itself instead of doing nothing.
        $this->actingAs($this->admin)
            ->post("/admin/disposal/{$disposalId}/archive")
            ->assertRedirect('/admin/disposal')
            ->assertSessionHas('error', 'This disposal record is already archived.');
    }

    public function test_a_non_admin_cannot_archive_even_through_the_form(): void
    {
        $requestId = $this->submitDisposalRequest();
        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $disposalId = (int) DB::table('disposals')->where('Request_id', $requestId)->value('Disposal_ID');

        $this->actingAs($this->employee)
            ->post("/admin/disposal/{$disposalId}/archive")
            ->assertRedirect('/admin/disposal')
            ->assertSessionHas('error', 'Only the Asset Management Office can archive a disposal record.');

        $this->assertFalse((bool) DB::table('disposals')->where('Disposal_ID', $disposalId)->value('is_archived'));
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

    /** Approve a disposal request and return the resulting Disposal_ID. */
    private function approveDisposalRequest(): int
    {
        $requestId = $this->submitDisposalRequest();
        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        return (int) DB::table('disposals')->where('Request_id', $requestId)->value('Disposal_ID');
    }

    /** A Department Head in the same department as the asset's employee. */
    private function departmentHead(): User
    {
        DB::table('employee_numbers')->insert([
            'id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dept Head',
            'Department_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::create([
            'employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Department Head', 'status' => 'Active',
        ]);
    }

    public function test_archiving_takes_the_asset_out_of_the_inventory_but_keeps_the_row(): void
    {
        $disposalId = $this->approveDisposalRequest();

        // While the disposal is still active the asset is still inventory.
        $this->assertTrue(Asset::inInventory()->whereKey($this->assetId)->exists());
        $this->assertFalse(Inventory::isRemoved(DB::table('assets')->where('id', $this->assetId)->first()));

        $this->actingAs($this->admin)->postJson("/admin/disposal/{$disposalId}/archive")->assertOk();

        // Out of the inventory …
        $asset = DB::table('assets')->where('id', $this->assetId)->first();
        $this->assertNotNull($asset, 'taking an asset out of the inventory must never delete the row');
        $this->assertTrue(Inventory::isRemoved($asset));
        $this->assertNotNull($asset->inventory_removed_at);
        $this->assertSame($this->admin->id, (int) $asset->inventory_removed_by);
        $this->assertFalse(Asset::inInventory()->whereKey($this->assetId)->exists());

        // … but nothing is lost: the archived record still names it, and its own
        // row and history stay readable.
        $this->actingAs($this->admin)->get('/admin/disposal/archived')
            ->assertOk()
            ->assertSee('AST-0001')
            ->assertSee('Removed from inventory');

        $this->actingAs($this->admin)->getJson("/admin/disposal/{$disposalId}/details")
            ->assertOk()
            ->assertJsonPath('asset_still_exists', true)
            ->assertJsonPath('inventory_removed', true)
            ->assertJsonPath('asset_code', 'AST-0001');
    }

    public function test_a_removed_asset_leaves_every_listing_while_its_history_survives(): void
    {
        $disposalId = $this->approveDisposalRequest();
        $head = $this->departmentHead();

        DB::table('asset_accountability')->insert([
            'asset_id' => $this->assetId, 'user_id' => $this->employee->id,
            'Assign_date' => now()->subMonths(6), 'Is_Current' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $listedOnAdminAssets = function (): bool {
            $page = $this->actingAs($this->admin)->get('/admin/assets')->assertOk();

            return collect($page->viewData('departments'))
                ->flatMap(fn ($dept) => $dept->assets ?? [])
                ->pluck('id')
                ->contains($this->assetId);
        };

        // Control: before archiving, every listing really does show the asset.
        $this->assertTrue($listedOnAdminAssets());
        $this->assertFalse(
            $this->actingAs($this->employee)->get('/users/assets')->viewData('assignedAssets')
                ->where('id', $this->assetId)->isEmpty()
        );
        $this->assertFalse(
            $this->actingAs($head)->get('/department-head/assets')->viewData('assignedAssets')
                ->where('id', $this->assetId)->isEmpty()
        );
        $this->actingAs($this->admin)->get('/admin/inventory-download')->assertOk()->assertSee('AST-0001');

        $this->actingAs($this->admin)->postJson("/admin/disposal/{$disposalId}/archive")->assertOk();

        // Gone from the admin Assets page, the employee's own list, the
        // department's list and the institution's inventory export.
        $this->assertFalse($listedOnAdminAssets(), 'a removed asset must not be listed on the Assets page');
        $this->assertTrue(
            $this->actingAs($this->employee)->get('/users/assets')->viewData('assignedAssets')
                ->where('id', $this->assetId)->isEmpty()
        );
        $this->assertTrue(
            $this->actingAs($head)->get('/department-head/assets')->viewData('assignedAssets')
                ->where('id', $this->assetId)->isEmpty()
        );
        $this->actingAs($this->admin)->get('/admin/inventory-download')->assertOk()->assertDontSee('AST-0001');

        // The employee can no longer open its detail page either.
        $this->actingAs($this->employee)->get("/users/assets/{$this->assetId}")->assertNotFound();

        // Nothing was deleted: the asset row, its accountability history and the
        // disposal record are all still there.
        $this->assertNotNull(DB::table('assets')->where('id', $this->assetId)->first());
        $this->assertSame(1, DB::table('asset_accountability')->where('asset_id', $this->assetId)->count());
        $this->assertSame(1, DB::table('disposals')->where('Disposal_ID', $disposalId)->count());
    }

    public function test_the_migration_backfill_removes_assets_whose_disposals_were_already_archived(): void
    {
        // A disposal archived before the inventory columns existed: the record is
        // archived, but the asset still looks like inventory.
        $disposalId = $this->approveDisposalRequest();

        DB::table('disposals')->where('Disposal_ID', $disposalId)->update([
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $this->admin->id,
        ]);
        DB::table('assets')->where('id', $this->assetId)->update([
            'inventory_removed_at' => null,
            'inventory_removed_by' => null,
        ]);

        $this->assertTrue(Asset::inInventory()->whereKey($this->assetId)->exists());

        // Deploying runs the migration; the backfill has to catch these rows up.
        $migration = require database_path('migrations/2026_09_30_010000_add_inventory_removal_to_assets_table.php');
        $migration->up();

        $asset = DB::table('assets')->where('id', $this->assetId)->first();
        $this->assertNotNull($asset->inventory_removed_at, 'an already-archived disposal must take its asset out of the inventory');
        $this->assertSame($this->admin->id, (int) $asset->inventory_removed_by);
        $this->assertFalse(Asset::inInventory()->whereKey($this->assetId)->exists());
    }

    /* ── permanently deleting an archived record and its asset ──────────── */

    /** Approve a disposal request and archive it: the state deletion requires. */
    private function archivedDisposal(): int
    {
        $disposalId = $this->approveDisposalRequest();

        $this->actingAs($this->admin)->postJson("/admin/disposal/{$disposalId}/archive")->assertOk();

        return $disposalId;
    }

    /** A second asset, for the records that have to point at one. */
    private function secondAsset(string $code = 'AST-0002', string $name = 'Printer'): int
    {
        return (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => $code, 'Asset_name' => $name,
            'Category' => 'Info and Equipment', 'Condition' => 'Good', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_an_admin_can_permanently_delete_an_archived_record_and_its_asset(): void
    {
        $disposalId = $this->archivedDisposal();
        $requestId  = (int) DB::table('disposals')->where('Disposal_ID', $disposalId)->value('Request_id');

        $this->actingAs($this->admin)
            ->post("/admin/disposal/{$disposalId}/delete-archived")
            ->assertRedirect('/admin/disposal/archived')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'AST-0001')
                && str_contains($message, 'permanently deleted'));

        // The record, the asset, and the rows that only existed because the asset
        // did (request_items is RESTRICT, so it is what broke the old action).
        $this->assertSame(0, DB::table('disposals')->where('Disposal_ID', $disposalId)->count());
        $this->assertSame(0, DB::table('assets')->where('id', $this->assetId)->count());
        $this->assertSame(0, DB::table('request_items')->where('asset_id', $this->assetId)->count());
        $this->assertSame(0, DB::table('requests')->where('id', $requestId)->count());

        // Neither page knows the record any more. (The flash message from the
        // delete is deliberately not part of this assertion.)
        $this->flushSession();

        $this->actingAs($this->admin)->get('/admin/disposal/archived')->assertOk()->assertDontSee('AST-0001');
        $this->actingAs($this->admin)->get('/admin/disposal')->assertOk()->assertDontSee('AST-0001');

        // The removal itself is the one thing that stays behind: the audit trail
        // names the asset the database no longer has.
        $audit = DB::table('audit_logs')->where('action_description', 'like', '%was permanently deleted%')->first();
        $this->assertNotNull($audit, 'removing an asset must leave an audit trail');
        $this->assertSame('DISPOSAL', $audit->action_type);
        $this->assertStringContainsString('AST-0001', $audit->action_description);
        $this->assertNull($audit->asset_id);
    }

    public function test_deleting_an_archived_record_clears_the_rows_that_restrict_the_asset(): void
    {
        $disposalId = $this->archivedDisposal();
        $requestId  = (int) DB::table('disposals')->where('Disposal_ID', $disposalId)->value('Request_id');

        $otherAssetId = $this->secondAsset();
        $otherRequestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->employee->id, 'asset_id' => $otherAssetId, 'request_type' => 'Repair',
            'status' => 'Pending', 'Note' => 'Other asset, other request.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('request_items')->insert([
            'request_id' => $otherRequestId, 'asset_id' => $otherAssetId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The exact fan-in that made the old delete fail with SQLSTATE 23503:
        // the request line the disposal request already added, plus a repair, a
        // replacement, a file, a pullout and an accountability record.
        $this->assertSame(1, DB::table('request_items')->where('asset_id', $this->assetId)->count());

        DB::table('repairs')->insert([
            'Assets_id' => $this->assetId, 'Request_id' => $requestId,
            'Repair_Description' => 'Replaced the mainboard.', 'Repair_Date' => now()->subMonth(),
            'Approve_by' => 'Sir alex', 'Repair_Cost' => 3200.00,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('replacements')->insert([
            'Request_id' => $requestId, 'old_assets_id' => $this->assetId, 'new_assets_id' => $otherAssetId,
            'reason' => 'Beyond repair', 'replacement_reason' => 'Damage',
            'Replacement_Date' => now()->subMonth(), 'Approve_by' => 'Sir alex',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('asset_files')->insert([
            'Asset_id' => $this->assetId, 'file_name' => 'unit.png', 'file_path' => 'assets/photos/ast-0001.png',
            'file_size' => 1024, 'mime_type' => 'image/png', 'uploaded_at' => now()->toDateTimeString(),
            'url' => 'assets/photos/ast-0001.png', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pullouts')->insert([
            'request_id' => $requestId, 'asset_id' => $this->assetId, 'Approve_by' => 'Sir alex',
            'pullout_date' => now()->subWeeks(2)->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('asset_accountability')->insert([
            'asset_id' => $this->assetId, 'user_id' => $this->employee->id,
            'Assign_date' => now()->subMonths(6), 'Is_Current' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/{$disposalId}/delete-archived")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('asset_deleted', true)
            ->assertJsonPath('asset_code', 'AST-0001');

        foreach ([
            'disposals'           => 'Asset_id',
            'requests'            => 'asset_id',
            'request_items'       => 'asset_id',
            'repairs'             => 'Assets_id',
            'asset_files'         => 'Asset_id',
            'pullouts'            => 'asset_id',
            'asset_accountability' => 'asset_id',
        ] as $table => $column) {
            $this->assertSame(
                0,
                DB::table($table)->where($column, $this->assetId)->count(),
                $table . ' must not outlive the asset it was restricted by'
            );
        }

        // The disposal request existed only for this asset, so it goes with it.
        $this->assertSame(0, DB::table('requests')->where('id', $requestId)->count());

        // The replacement record pointed at two assets; it belonged to the one
        // being deleted, so it goes with it.
        $this->assertSame(0, DB::table('replacements')->where('Request_id', $requestId)->count());
        $this->assertSame(0, DB::table('assets')->where('id', $this->assetId)->count());

        // Nothing that belongs to another asset was touched.
        $this->assertNotNull(DB::table('assets')->where('id', $otherAssetId)->first());
        $this->assertNotNull(DB::table('requests')->where('id', $otherRequestId)->first());
        $this->assertSame(1, DB::table('request_items')->where('asset_id', $otherAssetId)->count());
    }

    public function test_a_request_shared_with_another_asset_survives_the_deletion(): void
    {
        $disposalId = $this->archivedDisposal();
        $requestId  = (int) DB::table('disposals')->where('Disposal_ID', $disposalId)->value('Request_id');

        // The same request also covers a second asset — the Requests page can do
        // that, and deleting one asset must not strip the other one's history.
        $otherAssetId = $this->secondAsset();
        DB::table('requests')->where('id', $requestId)->update(['asset_id' => $this->assetId]);
        DB::table('request_items')->insert([
            'request_id' => $requestId, 'asset_id' => $otherAssetId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/{$disposalId}/delete-archived")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preserved_requests', [$requestId]);

        // The asset and its own record are gone …
        $this->assertSame(0, DB::table('assets')->where('id', $this->assetId)->count());
        $this->assertSame(0, DB::table('disposals')->where('Disposal_ID', $disposalId)->count());
        $this->assertSame(0, DB::table('request_items')->where('asset_id', $this->assetId)->count());

        // … but the shared request survives, no longer naming it.
        $request = DB::table('requests')->where('id', $requestId)->first();
        $this->assertNotNull($request, 'a request that also covers another asset must survive');
        $this->assertNull($request->asset_id);
        $this->assertSame(1, DB::table('request_items')->where('asset_id', $otherAssetId)->count());
        $this->assertNotNull(DB::table('assets')->where('id', $otherAssetId)->first());
    }

    public function test_a_disposal_record_that_is_not_archived_cannot_be_deleted(): void
    {
        $disposalId = $this->approveDisposalRequest();

        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/{$disposalId}/delete-archived")
            ->assertStatus(422);

        $this->assertSame(1, DB::table('disposals')->where('Disposal_ID', $disposalId)->count());
        $this->assertNotNull(
            DB::table('assets')->where('id', $this->assetId)->first(),
            'a record that is not archived must never take its asset with it'
        );
    }

    public function test_only_the_asset_management_office_can_permanently_delete_archived_records(): void
    {
        $disposalId = $this->archivedDisposal();

        $this->actingAs($this->employee)
            ->post("/admin/disposal/{$disposalId}/delete-archived")
            ->assertRedirect('/admin/disposal/archived')
            ->assertSessionHas('error', 'Only the Asset Management Office can permanently delete an archived disposal record.');

        $this->actingAs($this->employee)
            ->getJson("/admin/disposal/{$disposalId}/delete-impact")
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->post('/admin/disposal/archived/delete-all')
            ->assertRedirect('/admin/disposal/archived')
            ->assertSessionHas('error', 'Only the Asset Management Office can permanently delete archived disposal records.');

        $this->assertSame(1, DB::table('disposals')->where('Disposal_ID', $disposalId)->count());
        $this->assertNotNull(DB::table('assets')->where('id', $this->assetId)->first());
    }

    public function test_delete_all_removes_every_archived_record_and_the_assets_they_name(): void
    {
        $this->archivedDisposal();

        // A legacy record with no asset at all, exactly like the ones already
        // sitting on the deployed site ("Scanned Disposal", code N/A).
        DB::table('disposals')->insert([
            'Asset_id' => null, 'Request_id' => null, 'Approve_by' => 'System',
            'Description' => 'Scanned Disposal', 'disposal_date' => now()->subMonth()->toDateString(),
            'disposal_reason' => 'Obsolete', 'is_archived' => true,
            'archived_at' => now(), 'archived_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('disposals')->where('is_archived', true)->count());

        $this->actingAs($this->admin)
            ->post('/admin/disposal/archived/delete-all')
            ->assertRedirect('/admin/disposal/archived')
            ->assertSessionHas('success', fn ($message) => str_contains($message, '2 archived disposal records')
                && str_contains($message, '1 asset record'));

        $this->assertSame(0, DB::table('disposals')->count());
        $this->assertSame(0, DB::table('assets')->where('id', $this->assetId)->count());
        $this->assertSame(0, DB::table('request_items')->where('asset_id', $this->assetId)->count());

        $this->flushSession();

        $this->actingAs($this->admin)->get('/admin/disposal/archived')
            ->assertOk()
            ->assertSee('Nothing archived yet');

        $this->assertNotNull(
            DB::table('audit_logs')->where('action_description', 'like', '%Permanently deleted 2 archived disposal record%')->first(),
            'a bulk delete has to be recorded too'
        );
    }

    public function test_the_delete_preview_lists_what_will_be_removed_and_why_it_may_be_refused(): void
    {
        $disposalId = $this->archivedDisposal();

        DB::table('asset_files')->insert([
            'Asset_id' => $this->assetId, 'file_name' => 'unit.png', 'file_path' => 'assets/photos/ast-0001.png',
            'file_size' => 1024, 'mime_type' => 'image/png', 'uploaded_at' => now()->toDateTimeString(),
            'url' => 'assets/photos/ast-0001.png', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $preview = $this->actingAs($this->admin)
            ->getJson("/admin/disposal/{$disposalId}/delete-impact")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('impact.is_archived', true)
            ->assertJsonPath('impact.asset_code', 'AST-0001')
            ->assertJsonPath('impact.asset_exists', true)
            ->assertJsonPath('impact.blockers', [])
            ->assertJsonPath('impact.removes.Asset record', 1)
            ->assertJsonPath('impact.removes.Disposal record', 1)
            ->assertJsonPath('impact.removes.Request line item', 1)
            ->assertJsonPath('impact.removes.Asset file', 1)
            ->assertJsonPath('impact.files', 1);

        // The audit trail is reported as surviving, because it does.
        $this->assertGreaterThan(0, $preview->json('impact.keeps.Audit entry'));

        // A record that is not archived yet says why it cannot be deleted.
        $activeId = $this->approveDisposalRequest();

        $active = $this->actingAs($this->admin)
            ->getJson("/admin/disposal/{$activeId}/delete-impact")
            ->assertOk()
            ->assertJsonPath('impact.is_archived', false);

        $this->assertStringContainsString('archive this record first', implode(' ', $active->json('impact.blockers')));
    }

    public function test_the_archived_page_offers_deletion_instead_of_claiming_nothing_can_be_deleted(): void
    {
        $this->archivedDisposal();

        $page = $this->actingAs($this->admin)->get('/admin/disposal/archived')->assertOk();

        $page->assertSee('openDeleteModal');
        $page->assertSee('Permanently delete all 1');
        $page->assertSee('/delete-impact');
        $page->assertDontSee('nothing here can be deleted');
    }
}
