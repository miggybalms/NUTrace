<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The phone layout of the three signed-in shells.
 *
 * Three things used to make the app unusable in a browser narrower than a
 * desk: the shell's opening tag was written as `<main    <div class="…">`,
 * which is not an element — the parser reads it as one tag with junk
 * attributes, so the scrolling column lost `flex-1`; the page header stuck to
 * `top: 0` and slid under the fixed mobile top bar; and the drawer's controls
 * were 24px icons. Those are markup contracts, so they are pinned here.
 *
 * The wide data tables have a fourth rule: on a phone the least important
 * columns drop out (the row itself or its detail modal still carries them)
 * instead of forcing a sideways drag. Each such column has to stay rendered
 * for larger screens, which the assertions on both class and header text
 * cover.
 */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private User $head;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
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

        DB::table('assets')->insert([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Good', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('asset_accountability')->insert([
            'asset_id' => 1, 'user_id' => $this->employee->id, 'Assign_date' => now(),
            'Is_Current' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('requests')->insert([
            'user_id' => $this->employee->id, 'asset_id' => 1, 'request_type' => 'Repair',
            'status' => 'Pending', 'Note' => 'Screen flickers.', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // One archived disposal, so the archived table (and its responsive
        // columns) is actually rendered.
        DB::table('disposals')->insert([
            'Asset_id' => 1, 'Request_id' => 1, 'Approve_by' => $this->admin->id,
            'Description' => 'Disposal of an unserviceable unit', 'disposal_reason' => 'Unserviceable',
            'disposal_date' => now()->toDateString(), 'is_archived' => 1,
            'archived_at' => now(), 'archived_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, array{User, string}> */
    public static function shellPages(): array
    {
        return [
            'admin assets'      => ['admin', '/admin/assets'],
            'admin requests'    => ['admin', '/admin/requests'],
            'admin audit logs'  => ['admin', '/admin/audit-logs'],
            'employee assets'   => ['employee', '/users/assets'],
            'employee requests' => ['employee', '/user/requests'],
            'head assets'       => ['head', '/department-head/assets'],
            'head request form' => ['head', '/department-head/request-asset'],
        ];
    }

    /**
     * `<main    <div class="…">` is not an element: the browser reads `div` as an
     * attribute name, so the class never lands on the scrolling column. Every
     * shell must open one well-formed main with the flex classes on it.
     */
    public function test_every_shell_opens_one_well_formed_main_column(): void
    {
        foreach (self::shellPages() as $label => [$who, $path]) {
            $html = $this->actingAs($this->{$who})->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('<main    ', $html, "$label still has the malformed <main");
            $this->assertDoesNotMatchRegularExpression('/<main\s+</', $html, "$label renders a main tag with an element where an attribute belongs");
            $this->assertStringContainsString('<main class="flex-1', $html, "$label lost the flex-1/overflow classes on main");
        }
    }

    /**
     * The fixed mobile top bar is 3.5rem tall, so the scrolling column needs
     * that much room at the top and a sticky header has to start below it.
     */
    public function test_the_fixed_mobile_top_bar_never_covers_page_content(): void
    {
        foreach (['employee assets' => ['employee', '/users/assets'], 'head assets' => ['head', '/department-head/assets']] as $label => [$who, $path]) {
            $html = $this->actingAs($this->{$who})->get($path)->assertOk()->getContent();

            $main = substr($html, (int) strpos($html, '<main'));
            $main = substr($main, 0, (int) strpos($main, '</main>'));

            $this->assertStringContainsString('lg:hidden h-14', $main, "$label has no room reserved for the fixed top bar inside main");
        }

        // The shared stylesheet pushes the role headers below the bar on phones
        // and grows the icon-only controls to a thumb-sized target.
        $head = $this->actingAs($this->head)->get('/department-head/assets')->assertOk()->getContent();

        $this->assertStringContainsString('@media (max-width: 1023.98px)', $head);
        $this->assertStringContainsString('top: 3.5rem', $head);
        $this->assertStringContainsString('min-height: 2.5rem', $head);
    }

    /** The drawer's open and close controls need a real hit area on a phone. */
    public function test_the_drawer_controls_have_a_touch_sized_hit_area(): void
    {
        foreach (['employee' => '/users/assets', 'head' => '/department-head/assets'] as $who => $path) {
            $html = $this->actingAs($this->{$who})->get($path)->assertOk()->getContent();

            preg_match('/<button id="sidebarOpenBtn"[^>]*>/', $html, $open);
            preg_match('/<button id="sidebarCloseBtn"[^>]*>/', $html, $close);

            $this->assertNotEmpty($open, "$who page is missing the drawer open button");
            $this->assertNotEmpty($close, "$who page is missing the drawer close button");
            $this->assertStringContainsString('p-2', $open[0], "$who drawer open button is an unpadded icon");
            $this->assertStringContainsString('p-2', $close[0], "$who drawer close button is an unpadded icon");
        }
    }

    /**
     * On a phone a wide table drops its secondary columns rather than making the
     * office drag the table sideways; the same column stays in the markup for
     * wider screens.
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function secondaryColumns(): array
    {
        return [
            'requests / submitted by'   => ['admin', '/admin/requests', 'Submitted By', 'hidden sm:table-cell'],
            'requests / date'           => ['admin', '/admin/requests', 'Date', 'hidden sm:table-cell'],
            'audit logs / request'      => ['admin', '/admin/audit-logs', 'Request', 'hidden lg:table-cell'],
            'department assets / date'  => ['admin', '/admin/assets/department/1', 'Date Acquired', 'hidden lg:table-cell'],
            'archived disposals / code' => ['admin', '/admin/disposal/archived', 'Asset Code', 'hidden lg:table-cell'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('secondaryColumns')]
    public function test_secondary_table_columns_are_tucked_away_on_a_phone(string $who, string $path, string $header, string $class): void
    {
        $html = $this->actingAs($this->{$who})->get($path)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<th[^>]*' . preg_quote($class, '/') . '[^>]*>' . preg_quote($header, '/') . '<\/th>/',
            $html,
            "the \"$header\" column on $path is not marked $class"
        );
    }
}
