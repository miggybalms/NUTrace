<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The asset-detail screen and the admin topbar.
 *
 * The QR code the detail page shows is what the office scans to pick an asset
 * on a request form, so it has to carry the asset's *current* code — the string
 * `assets.Asset_code` is looked up by. It used to display the stored PNG from
 * the media disk instead, which is a different picture (lower error correction,
 * scaled down to fit the modal) and stops matching the moment a code changes.
 *
 * The repair banner also used to print the internal `Repair_id`, and the admin
 * profile chip opened nothing at all.
 */
class AssetDetailAndAdminHeaderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private User $head;
    private int $assetId;

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

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Demo Asset',
            'Category' => 'Info and Equipment', 'Condition' => 'Good', 'Lifecycle_Status' => 'For Repair',
            'qr_code_path' => 'assets/qr/AST-0001-1790785359.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // An open repair, so the detail page renders its repair banner.
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->employee->id, 'asset_id' => $this->assetId, 'request_type' => 'Repair',
            'status' => 'Pending', 'Note' => 'Screen flickers.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('repairs')->insert([
            'Assets_id' => $this->assetId, 'Request_id' => $requestId,
            'Repair_Description' => 'Screen flickers.', 'Repair_Date' => now(),
            'Approve_by' => 'Sir Alex', 'Repair_Cost' => 0, 'status' => 'Pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ── the repair banner ──────────────────────────────────────────────── */

    public function test_the_repair_banner_states_the_status_without_the_internal_repair_id(): void
    {
        foreach (['/department-head/assets/' . $this->assetId => $this->head,
                  '/users/assets/' . $this->assetId           => $this->employee] as $path => $actor) {
            $page = $this->actingAs($actor)->get($path)->assertOk();

            $page->assertSee('Repair Status: Pending');
            $page->assertDontSee('Repair #', false);
        }
    }

    /* ── the QR code on the asset detail screen ─────────────────────────── */

    public function test_the_detail_qr_is_drawn_from_the_current_asset_code(): void
    {
        $page = $this->actingAs($this->head)
            ->get('/department-head/assets/' . $this->assetId)
            ->assertOk();

        $html = $page->getContent();

        // The payload is the asset code the scanner looks up …
        $page->assertSee('var assetCode', false);
        $page->assertSee('"AST-0001"', false);
        $page->assertSee('correctLevel: QRCode.CorrectLevel.H', false);

        // … and it is what gets drawn. The stored PNG is only the fallback for an
        // asset that has no code at all.
        $draw   = strpos($html, 'if (assetCode) {');
        $stored = strpos($html, 'showStoredQr(holder);');

        $this->assertNotFalse($draw, 'the modal must draw the code itself');
        $this->assertNotFalse($stored, 'the stored image must stay as a fallback');
        $this->assertLessThan($stored, $draw, 'the live drawing must be preferred over the stored image');
    }

    public function test_a_scanned_code_resolves_even_when_the_case_differs(): void
    {
        // The admin scanner has always matched case-insensitively; the employee
        // and department-head scan buttons did not, so the same sticker worked in
        // one place and was "not detected" in the other.
        $this->actingAs($this->head)
            ->getJson('/department-head/assets/check-code?code=' . urlencode('ast-0001'))
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('asset.code', 'AST-0001');

        $this->actingAs($this->head)
            ->getJson('/department-head/assets/check-code?code=' . urlencode('  aSt-0001  '))
            ->assertOk()
            ->assertJsonPath('exists', true);

        $this->actingAs($this->employee)
            ->getJson('/user/assets/check-code?code=' . urlencode('ast-0001'))
            ->assertOk()
            ->assertJsonPath('exists', true);

        // A code that truly is not there still answers no.
        $this->actingAs($this->head)
            ->getJson('/department-head/assets/check-code?code=AST-NOPE')
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    /* ── the admin profile chip ─────────────────────────────────────────── */

    public function test_every_admin_page_has_one_working_profile_menu(): void
    {
        foreach (['/admin', '/admin/requests', '/admin/assets', '/admin/repair'] as $path) {
            $page = $this->actingAs($this->admin)->get($path)->assertOk();
            $html = $page->getContent();

            $this->assertSame(
                1,
                substr_count($html, 'id="admin-profile-dropdown"'),
                $path . ' must carry exactly one profile dropdown'
            );
            $this->assertSame(
                1,
                substr_count($html, 'id="admin-profile-btn"'),
                $path . ' must carry exactly one profile toggle'
            );

            // It is a real toggle (button + menu + aria state), not the static
            // chip that used to sit on the dashboard and do nothing.
            $page->assertSee('aria-haspopup="true"', false);
            $page->assertSee('aria-expanded="false"', false);
        }
    }

    public function test_the_admin_profile_menu_offers_the_signed_in_account_and_a_logout(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/requests')->assertOk();

        $page->assertSee('Sir Alex');
        $page->assertSee('alex@nu-lipa.edu.ph');
        $page->assertSee('Admin');
        $page->assertSee('admin-logout-form', false);
        $page->assertSee(route('logout'), false);
    }
}
