<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The service-life review on the Disposal page.
 *
 * It files assets for a human look. The tests here prove it never becomes a
 * disposal rule of its own: reaching the age mark changes no lifecycle status,
 * creates no request and records no disposal.
 */
class AssetAgeReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Juan Dela Cruz', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 2, 'email' => 'juan@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    private function asset(
        string $code,
        ?Carbon $acquired,
        string $status = 'Active',
        string $name = 'Office chair',
        ?int $ownerId = null,
        ?Carbon $removedAt = null
    ): int {
        return (int) DB::table('assets')->insertGetId([
            'user_id'                  => $ownerId ?: $this->employee->id,
            'Asset_code'               => $code,
            'Asset_name'               => $name,
            'Category'                 => 'Furnitures and Fixtures',
            'Condition'                => 'Fair',
            'Lifecycle_Status'         => $status,
            'asset_location'           => 'Registrar Office',
            'accusion_date'            => $acquired?->toDateString(),
            'purchase_Price'           => 8500.00,
            'inventory_removed_at'     => $removedAt,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    /* ═══════════════ 1. which assets reach the review list ═══════════════ */

    public function test_assets_at_or_past_the_proposed_mark_are_offered_for_review(): void
    {
        $this->asset('AST-OLD', now()->subYears(4));
        $this->asset('AST-EDGE', now()->subYears(3));
        $this->asset('AST-YOUNG', now()->subYears(3)->addDay());
        $this->asset('AST-NODATE', null);

        $response = $this->actingAs($this->admin)->get('/admin/disposal?tab=age');

        $response->assertOk()
            ->assertSee('Service-life review')
            ->assertSee('AST-OLD')
            ->assertSee('AST-EDGE')
            ->assertDontSee('AST-YOUNG')
            ->assertDontSee('AST-NODATE');
    }

    public function test_retired_and_inventory_removed_assets_are_not_on_the_review_list(): void
    {
        $this->asset('AST-LIVE', now()->subYears(4));
        $this->asset('AST-DISPOSED', now()->subYears(4), 'Disposal');
        $this->asset('AST-PULLED', now()->subYears(4), 'Pullout');
        $this->asset('AST-REMOVED', now()->subYears(4), 'Active', 'Office chair', null, now()->subMonth());

        $response = $this->actingAs($this->admin)->get('/admin/disposal?tab=age');

        $response->assertOk()
            ->assertSee('AST-LIVE')
            ->assertDontSee('AST-DISPOSED')
            ->assertDontSee('AST-PULLED')
            ->assertDontSee('AST-REMOVED');
    }

    public function test_an_asset_already_in_a_disposal_request_is_marked_not_hidden(): void
    {
        $assetId = $this->asset('AST-ASKED', now()->subYears(5));

        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id'      => $this->employee->id,
            'asset_id'     => null,
            'request_type' => 'Disposal',
            'status'       => 'Pending',
            'Note'         => 'Broken beyond repair.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('request_items')->insert([
            'request_id' => $requestId,
            'asset_id'   => $assetId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/disposal?tab=age')
            ->assertOk()
            ->assertSee('AST-ASKED')
            ->assertSee('Already in a disposal request');
    }

    public function test_the_search_narrows_the_review_list(): void
    {
        $this->asset('AST-CHAIR', now()->subYears(4), 'Active', 'Office chair');
        $this->asset('AST-PRINTER', now()->subYears(4), 'Active', 'Laser printer');

        $response = $this->actingAs($this->admin)->get('/admin/disposal?tab=age&q=printer');

        $response->assertOk()
            ->assertSee('AST-PRINTER')
            ->assertDontSee('AST-CHAIR');
    }

    /* ═══════════════ 2. a review is not a decision ═══════════════ */

    public function test_viewing_the_review_list_changes_nothing_at_all(): void
    {
        $assetId = $this->asset('AST-AGED', now()->subYears(7));

        $before = DB::table('assets')->where('id', $assetId)->first();

        $this->actingAs($this->admin)->get('/admin/disposal?tab=age')->assertOk();

        $after = DB::table('assets')->where('id', $assetId)->first();

        $this->assertSame($before->Lifecycle_Status, $after->Lifecycle_Status);
        $this->assertSame($before->updated_at, $after->updated_at);
        $this->assertSame(0, DB::table('requests')->count(), 'Reviewing an asset must not create a disposal request.');
        $this->assertSame(0, DB::table('disposals')->count(), 'Reviewing an asset must not record a disposal.');
    }

    public function test_the_age_mark_is_configurable(): void
    {
        config(['disposal.age_review.years' => 5]);

        $this->asset('AST-FOUR', now()->subYears(4));
        $this->asset('AST-SIX', now()->subYears(6));

        $this->actingAs($this->admin)
            ->get('/admin/disposal?tab=age')
            ->assertOk()
            ->assertSee('5 years')
            ->assertSee('AST-SIX')
            ->assertDontSee('AST-FOUR');
    }

    public function test_the_review_can_be_switched_off(): void
    {
        config(['disposal.age_review.enabled' => false]);

        $this->asset('AST-OLD', now()->subYears(6));

        $this->actingAs($this->admin)
            ->get('/admin/disposal?tab=age')
            ->assertOk()
            ->assertDontSee('Service-life review')
            ->assertDontSee('AST-OLD');

        $this->actingAs($this->admin)->get('/admin/disposal')->assertOk();
    }

    public function test_only_the_asset_management_office_can_open_the_review(): void
    {
        $this->actingAs($this->employee)
            ->get('/admin/disposal?tab=age')
            ->assertForbidden();
    }
}
