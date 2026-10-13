<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The employee and department-head asset pages.
 *
 * They used to load every asset and filter the cards in JavaScript, so once the
 * listing is paged the tabs and the search would only have seen the ten cards on
 * screen. These tests pin the property that matters: a tab or a search is
 * applied to the whole set, and paging never drops the filter that produced the
 * page.
 */
class AssetListingPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Kevin Andrew', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Demo name', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'kevin@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->head = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Department Head', 'status' => 'Active',
        ]);

        // 20 ordinary active assets, then the ones that sort to the last page.
        for ($i = 1; $i <= 20; $i++) {
            $this->makeAsset(sprintf('Asset %02d', $i), sprintf('AST-%04d', $i), 'Active', $this->employee);
        }

        $this->makeAsset('Asset 21', 'AST-0021', 'For Checking', $this->employee);

        for ($i = 1; $i <= 3; $i++) {
            $this->makeAsset(sprintf('Zeta Repair %02d', $i), sprintf('AST-R%03d', $i), 'For Repair', $this->employee);
        }

        // The newest asset in the department, and it sorts last of all.
        $this->makeAsset('Zeta Recent 01', 'AST-N001', 'Active', $this->employee, [
            'accusion_date' => now()->subDay()->toDateString(),
        ]);

        // Assets that left the holder: never listed, never counted.
        $this->makeAsset('Zeta Pullout 01', 'AST-P001', 'Pullout', $this->employee);
        $this->makeAsset('Zeta Disposal 01', 'AST-D001', 'Disposal', $this->employee);
    }

    private function makeAsset(string $name, string $code, string $status, User $owner, array $overrides = []): int
    {
        return (int) DB::table('assets')->insertGetId($overrides + [
            'user_id'          => $owner->id,
            'Asset_code'       => $code,
            'Asset_name'       => $name,
            'Category'         => 'General and Office Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => $status,
            'asset_location'   => 'Faculty Room',
            // Long enough ago that only the asset dated below counts as recent.
            'accusion_date'    => now()->subMonths(3)->toDateString(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function cards(string $html): int
    {
        return substr_count($html, 'asset-card bg-white');
    }

    public function test_the_employee_page_lists_ten_assets_at_a_time(): void
    {
        $response = $this->actingAs($this->employee)->get('/users/assets')->assertOk();

        $response->assertSee('Showing 25 assets', false);
        $this->assertSame(10, $response->viewData('assignedAssets')->perPage());
        $this->assertSame(25, $response->viewData('assignedAssets')->total());
        $this->assertSame(10, $this->cards($response->getContent()));
    }

    public function test_the_last_page_holds_the_remainder(): void
    {
        $last = $this->actingAs($this->employee)->get('/users/assets?page=3')->assertOk();

        $this->assertSame(5, $this->cards($last->getContent()));
    }

    public function test_a_status_tab_covers_the_whole_listing_not_just_the_page_on_screen(): void
    {
        $first = $this->actingAs($this->employee)->get('/users/assets')->assertOk();

        // The For Repair assets are not on the first page at all...
        $first->assertDontSee('AST-R001', false);

        // ...yet the tab finds all three of them.
        $filtered = $this->actingAs($this->employee)->get('/users/assets?filter=For Repair')->assertOk();

        $filtered->assertSee('AST-R001', false)
            ->assertSee('AST-R002', false)
            ->assertSee('AST-R003', false)
            ->assertSee('Showing 3 assets', false)
            ->assertDontSee('AST-0001', false);

        $this->assertSame(3, $filtered->viewData('assignedAssets')->total());
    }

    public function test_the_search_box_finds_an_asset_that_is_not_on_the_first_page(): void
    {
        $response = $this->actingAs($this->employee)->get('/users/assets?q=Zeta Repair 02')->assertOk();

        $response->assertSee('AST-R002', false)
            ->assertSee('Showing 1 assets', false)
            ->assertDontSee('AST-0001', false);
    }

    public function test_the_search_box_matches_what_a_card_shows(): void
    {
        // A code is specific enough to land on one page.
        $this->actingAs($this->employee)
            ->get('/users/assets?q=AST-0015')
            ->assertOk()
            ->assertSee('AST-0015', false)
            ->assertSee('Showing 1 assets', false);

        // Category and location are printed on every card, so they match the
        // whole listing — and that result still pages 10 at a time.
        foreach (['General and Office Equipment', 'Faculty Room'] as $term) {
            $response = $this->actingAs($this->employee)
                ->get('/users/assets?q='.urlencode($term))
                ->assertOk();

            $response->assertSee('Showing 25 assets', false);
            $this->assertSame(10, $this->cards($response->getContent()));
        }
    }

    public function test_recently_added_covers_the_whole_listing(): void
    {
        $response = $this->actingAs($this->employee)->get('/users/assets?filter=recent')->assertOk();

        $response->assertSee('AST-N001', false)
            ->assertSee('Showing 1 assets', false)
            ->assertDontSee('AST-0001', false);
    }

    public function test_the_personal_tab_on_the_head_page_covers_the_whole_department(): void
    {
        $this->makeAsset('Zeta Mine 01', 'AST-M001', 'Active', $this->head);

        $all = $this->actingAs($this->head)->get('/department-head/assets')->assertOk();
        $all->assertDontSee('AST-M001', false);   // sorts last, so it is on a later page

        $mine = $this->actingAs($this->head)->get('/department-head/assets?filter=department')->assertOk();

        $mine->assertSee('AST-M001', false)
            ->assertSee('Showing 1 assets', false)
            ->assertDontSee('AST-0001', false);
    }

    public function test_paging_keeps_the_tab_and_the_search(): void
    {
        $html = $this->actingAs($this->employee)
            ->get('/users/assets?filter=Active&q=Asset')
            ->assertOk()
            ->getContent();

        preg_match_all('/href="([^"]*page=2[^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'the filtered listing should be long enough to page');

        $carried = false;

        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);

            if (str_contains($href, 'filter=Active') && str_contains($href, 'q=Asset')) {
                $carried = true;
            }
        }

        $this->assertTrue($carried, 'a page link must carry the tab and the search');
    }

    public function test_the_tabs_keep_the_search_that_is_typed(): void
    {
        $html = $this->actingAs($this->employee)
            ->get('/users/assets?q=Zeta')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('q=Zeta', $html);
        // Spaces are encoded in a query string, so accept either form.
        $this->assertMatchesRegularExpression('/filter=For(\+|%20)Repair/', $html);
    }

    public function test_assets_that_left_the_holder_are_neither_listed_nor_counted(): void
    {
        $response = $this->actingAs($this->employee)->get('/users/assets')->assertOk();

        $response->assertDontSee('AST-P001', false)
            ->assertDontSee('AST-D001', false)
            ->assertDontSee('Zeta Pullout 01', false);

        // 25 visible of the 27 rows: the summary cards count holdings, not pages.
        $this->assertSame(25, $response->viewData('totalAssets'));
        $this->assertSame(21, $response->viewData('activeAssets'));
    }

    public function test_a_filter_with_no_match_says_so_and_offers_a_way_back(): void
    {
        $response = $this->actingAs($this->employee)
            ->get('/users/assets?q=nothing-matches-this')
            ->assertOk();

        $response->assertSee('No Matching Assets', false)
            ->assertSee('Show all assets', false);

        $this->assertSame(0, $this->cards($response->getContent()));
    }

    public function test_the_summary_cards_do_not_move_when_a_tab_is_clicked(): void
    {
        $unfiltered = $this->actingAs($this->employee)->get('/users/assets')->assertOk();
        $filtered = $this->actingAs($this->employee)->get('/users/assets?filter=For Repair')->assertOk();

        $this->assertSame(25, $unfiltered->viewData('totalAssets'));
        $this->assertSame(25, $filtered->viewData('totalAssets'));
        $this->assertSame(21, $filtered->viewData('activeAssets'));
    }

    public function test_the_head_page_pages_the_department_ten_at_a_time(): void
    {
        $response = $this->actingAs($this->head)->get('/department-head/assets')->assertOk();

        $this->assertSame(AssetListing::PER_PAGE, $response->viewData('assignedAssets')->perPage());
        $this->assertSame(10, $this->cards($response->getContent()));

        $second = $this->actingAs($this->head)->get('/department-head/assets?page=2')->assertOk();
        $this->assertSame(10, $this->cards($second->getContent()));
    }
}
