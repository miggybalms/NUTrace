<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin Assets page: one card per department.
 *
 * The cards show how many assets a department holds and how those split across
 * the lifecycle statuses. Nobody lists assets on this page — "View All" opens the
 * department's own paged list — so the page must count in SQL rather than load
 * every asset to count it in PHP.
 *
 * The last test is the one that keeps it that way: the number of queries on the
 * page must not grow with the size of the registry.
 */
class AdminAssetOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $facultyEmployee;

    private User $registrarEmployee;

    private User $undepartmented;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 3, 'Name' => 'Empty Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Registrar Staff', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'Employee_number' => 'EMP-5', 'Full_Name' => 'No Department', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'alex@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->facultyEmployee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'nena@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->registrarEmployee = User::create([
            'employee_numbers_id' => 4, 'department_id' => 2, 'email' => 'registrar@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        // An account with no department of its own: its assets can only be
        // placed by the department recorded on the asset itself.
        $this->undepartmented = User::create([
            'employee_numbers_id' => 5, 'department_id' => null, 'email' => 'nodept@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);
    }

    private function makeAsset(array $overrides = []): int
    {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('assets')->insertGetId($overrides + [
            'user_id'          => $this->facultyEmployee->id,
            'department_id'    => null,
            'Asset_code'       => 'AST-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'Asset_name'       => 'Demo asset ' . $sequence,
            'Category'         => 'General and Office Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => 'Active',
            'accusion_date'    => '2026-10-08',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** The card for a department, keyed by name. */
    private function card(string $name)
    {
        $response = $this->actingAs($this->admin)->get('/admin/assets')->assertOk();

        return collect($response->viewData('departments'))->firstWhere('name', $name);
    }

    public function test_each_department_card_counts_its_assets_by_status(): void
    {
        $this->makeAsset(['Lifecycle_Status' => 'Active']);
        $this->makeAsset(['Lifecycle_Status' => 'Active']);
        $this->makeAsset(['Lifecycle_Status' => 'For Repair']);
        $this->makeAsset(['Lifecycle_Status' => 'Disposed']);
        $this->makeAsset(['Lifecycle_Status' => 'Acquired', 'user_id' => $this->registrarEmployee->id]);

        $facilities = $this->card('Facilities');

        $this->assertSame(4, $facilities->total_assets);
        $this->assertSame(2, $facilities->counts['active']);
        $this->assertSame(1, $facilities->counts['for_repair']);
        $this->assertSame(1, $facilities->counts['disposed']);
        $this->assertSame(0, $facilities->counts['pulled_out']);

        $registrar = $this->card('Registrar');

        $this->assertSame(1, $registrar->total_assets);
        $this->assertSame(1, $registrar->counts['acquired']);
    }

    public function test_an_assets_own_department_wins_over_its_holders_department(): void
    {
        // An imported asset can name its department before anybody is assigned.
        $this->makeAsset(['department_id' => 2, 'user_id' => $this->facultyEmployee->id]);

        $this->assertSame(0, $this->card('Facilities')->total_assets);
        $this->assertSame(1, $this->card('Registrar')->total_assets);
    }

    public function test_an_asset_without_a_department_is_not_counted_anywhere(): void
    {
        // No department on the asset and no holder to read one from.
        $this->makeAsset(['user_id' => $this->undepartmented->id, 'department_id' => null]);

        $this->assertSame(0, $this->card('Facilities')->total_assets);
        $this->assertSame(0, $this->card('Registrar')->total_assets);
    }

    public function test_assets_that_left_the_inventory_are_not_counted(): void
    {
        $this->makeAsset(['Lifecycle_Status' => 'Active']);
        $this->makeAsset(['Lifecycle_Status' => 'Disposed', 'inventory_removed_at' => now()]);

        $facilities = $this->card('Facilities');

        $this->assertSame(1, $facilities->total_assets);
        $this->assertSame(0, $facilities->counts['disposed']);
    }

    public function test_an_empty_department_shows_zeroes_and_no_head(): void
    {
        $empty = $this->card('Empty Department');

        $this->assertSame(0, $empty->total_assets);
        $this->assertSame(0, $empty->counts['active']);
        $this->assertSame('', $empty->head_email);
    }

    public function test_the_page_shows_the_department_head(): void
    {
        $head = User::create([
            'employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Department Head', 'status' => 'Active',
        ]);

        $facilities = $this->card('Facilities');

        $this->assertSame($head->id, $facilities->head_id);
        $this->assertSame('head@nu-lipa.edu.ph', $facilities->head_email);
        $this->assertSame('Dept Head', $facilities->head);
    }

    public function test_the_page_does_not_load_more_rows_as_the_registry_grows(): void
    {
        // Warm the schema check and the session/auth lookups so both counts
        // measure the page itself.
        Inventory::columnsReady();
        $this->actingAs($this->admin)->get('/admin/assets')->assertOk();

        $this->makeAsset();

        $queriesWithOne = $this->queryCount();

        for ($i = 0; $i < 40; $i++) {
            $this->makeAsset(['Lifecycle_Status' => $i % 2 === 0 ? 'Active' : 'For Repair']);
        }

        $queriesWithMany = $this->queryCount();

        $this->assertSame(
            $queriesWithOne,
            $queriesWithMany,
            'The overview page runs the same number of queries whatever the registry holds: '
            . 'one aggregate, not one row per asset.'
        );

        // And the extra assets are still counted.
        $this->assertSame(41, $this->card('Facilities')->total_assets);
    }

    /** How many SQL statements the overview page runs. */
    private function queryCount(): int
    {
        $count = 0;

        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->actingAs($this->admin)->get('/admin/assets')->assertOk();

        return $count;
    }
}
