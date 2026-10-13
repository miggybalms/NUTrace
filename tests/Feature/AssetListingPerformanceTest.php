<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The employee and department-head asset listings.
 *
 * The bottleneck on these pages was never the database: measured on 400 assets
 * x 3 photos the listing query runs ~1.8ms, and a per-asset subquery rewrite of
 * it measured ~18ms, so the join + MAX lookup is kept. The real cost was the
 * browser: every card's full-size photo was requested up front, before anything
 * scrolled into view. The cards now carry loading="lazy".
 *
 * What is locked down here is behaviour, not a fixed query count: the same photo
 * value still reaches the page, and the database work does not grow with the
 * number of assets.
 */
class AssetListingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $head;

    private int $queries = 0;

    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function () {
            $this->queries++;
        });

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Kevin Andrew', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'kevin@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->head = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Department Head', 'status' => 'Active',
        ]);
    }

    /** @return int the id of the new asset */
    private function makeAsset(string $code, int $photos = 1): int
    {
        $assetId = (int) DB::table('assets')->insertGetId([
            'user_id'          => $this->employee->id,
            'Asset_code'       => $code,
            'Asset_name'       => 'Demo asset '.$code,
            'Category'         => 'General and Office Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location'   => 'Faculty Room',
            'accusion_date'    => '2026-10-08',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        for ($i = 1; $i <= $photos; $i++) {
            DB::table('asset_files')->insert([
                'Asset_id'    => $assetId,
                'file_name'   => 'photo-'.$i.'.jpg',
                'file_path'   => 'assets/photos/'.$code.'-'.$i.'.jpg',
                'file_size'   => 1024,
                'mime_type'   => 'image/jpeg',
                'uploaded_at' => (string) now(),
                'url'         => 'https://media.example.test/'.$code.'-'.$i.'.jpg',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return $assetId;
    }

    public function test_the_employee_listing_keeps_the_photo_value_and_does_not_run_a_query_per_asset(): void
    {
        $assetId = $this->makeAsset('AST-0001', 3);

        // Warm up first: one-off work (schema introspection, the first-visit
        // audit row) is paid here, so both measurements below are steady state.
        $this->actingAs($this->employee)->get('/users/assets')->assertOk();

        $before = $this->queries;
        $response = $this->actingAs($this->employee)->get('/users/assets')->assertOk();
        $withOneAsset = $this->queries - $before;

        $page = $response->viewData('assignedAssets');
        $row = collect($page->items())->firstWhere('id', $assetId);

        // The same value the MAX(asset_files.url) join produced, and the page
        // still contains exactly one row per asset.
        $this->assertSame('https://media.example.test/AST-0001-3.jpg', $row->image_url);
        $this->assertSame(1, $page->total());

        for ($i = 2; $i <= 25; $i++) {
            $this->makeAsset('AST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 2);
        }

        $this->actingAs($this->employee)->get('/users/assets')->assertOk(); // warm up

        $before = $this->queries;
        $this->actingAs($this->employee)->get('/users/assets')->assertOk();
        $withManyAssets = $this->queries - $before;

        $this->assertSame(
            $withOneAsset,
            $withManyAssets,
            'the employee asset listing must not run extra queries per asset'
        );
    }

    public function test_the_department_head_listing_behaves_the_same_way(): void
    {
        $this->makeAsset('AST-0001', 2);

        $this->actingAs($this->head)->get('/department-head/assets')->assertOk(); // warm up

        $before = $this->queries;
        $response = $this->actingAs($this->head)->get('/department-head/assets')->assertOk();
        $withOneAsset = $this->queries - $before;

        $this->assertSame(1, $response->viewData('assignedAssets')->total());

        for ($i = 2; $i <= 25; $i++) {
            $this->makeAsset('AST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 2);
        }

        $this->actingAs($this->head)->get('/department-head/assets')->assertOk(); // warm up

        $before = $this->queries;
        $response = $this->actingAs($this->head)->get('/department-head/assets')->assertOk();
        $withManyAssets = $this->queries - $before;

        $this->assertSame(25, $response->viewData('assignedAssets')->total());
        $this->assertSame(
            $withOneAsset,
            $withManyAssets,
            'the department-head asset listing must not run extra queries per asset'
        );
    }

    public function test_card_photos_are_fetched_only_as_they_scroll_into_view(): void
    {
        $this->makeAsset('AST-0001', 1);

        $this->actingAs($this->employee)
            ->get('/users/assets')
            ->assertOk()
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertSee('https://media.example.test/AST-0001-1.jpg', false);

        $this->actingAs($this->head)
            ->get('/department-head/assets')
            ->assertOk()
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false);
    }

    public function test_an_asset_without_a_photo_is_still_listed_with_an_empty_image(): void
    {
        $assetId = $this->makeAsset('AST-0099', 0);

        $page = $this->actingAs($this->employee)->get('/users/assets')->viewData('assignedAssets');
        $row = collect($page->items())->firstWhere('id', $assetId);

        $this->assertNull($row->image_url);
    }
}
