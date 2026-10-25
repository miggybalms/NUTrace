<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The official NU Trace mark, printed inside every asset QR code.
 *
 * Three things are locked down here:
 *
 *  1. the artwork ships with the app and every sticker-drawing screen loads the
 *     one module that paints it;
 *  2. the scanners load the reader that refuses a code without the mark, and
 *     actually call it before anything is looked up;
 *  3. whether a scan is refused is a server setting, so turning it off while
 *     old stickers are still in circulation is a deployment decision and not
 *     something a browser can decide for itself.
 */
class QrSealTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Kevin Andrew', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'alex@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'kevin@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        DB::table('assets')->insert([
            'user_id' => $this->employee->id,
            'Asset_code' => 'AST-0001',
            'Asset_name' => 'Demo asset',
            'Category' => 'Furniture',
            'Condition' => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location' => 'Faculty Room',
            'accusion_date' => '2026-10-08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assetId(): int
    {
        return (int) DB::table('assets')->where('Asset_code', 'AST-0001')->value('id');
    }

    public function test_the_mark_module_is_served_as_javascript(): void
    {
        $response = $this->get('/js/nu-trace-mark.js');

        $response->assertOk();
        $this->assertStringContainsString('javascript', strtolower((string) $response->headers->get('Content-Type')));

        // A page fetched after a deployment must run that deployment's reader,
        // not yesterday's cached copy.
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));

        // The route streams the resource file, so what it serves is what the
        // browser runs.
        $served = (string) file_get_contents(resource_path('js/nu-trace-mark.js'));

        $this->assertStringContainsString('NUTraceQrMark', $served);
        $this->assertStringContainsString('checkScan', $served);
        // The mark's position is read from the corners a decoder returns.
        $this->assertStringContainsString('topLeftCorner', $served);
        // And it is recognised in grey as well as in colour, because the
        // office's label printer has a black ribbon and no colour at all.
        $this->assertStringContainsString('matchPrint', $served);
        $this->assertStringContainsString('PRINT_MATCH', $served);
        $this->assertStringContainsString('artworkGrid', $served);
    }

    public function test_the_official_artwork_ships_with_the_app(): void
    {
        $path = public_path('images/nu-trace-mark.jpg');

        $this->assertFileExists($path, 'the uploaded NU Trace logo must be part of the app');

        $bytes = (string) file_get_contents($path);

        // JPEG magic: the sticker uses the supplied artwork itself, not a
        // stand-in or a generated badge.
        $this->assertSame("\xFF\xD8\xFF", substr($bytes, 0, 3));
        $this->assertGreaterThan(20000, strlen($bytes));
    }

    public function test_a_scan_has_to_find_the_mark_by_default(): void
    {
        $this->assertTrue(config('qr.require_seal'));

        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('requireSeal: true', $html);
        $this->assertStringContainsString('images\\/nu-trace-mark.jpg', $html);
    }

    public function test_the_deployment_can_keep_old_stickers_scannable_while_they_are_reprinted(): void
    {
        config(['qr.require_seal' => false]);

        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('requireSeal: false', $html);
    }

    public function test_every_scanning_screen_loads_the_readers_it_needs(): void
    {
        $pages = [
            '/admin',
            '/admin/pullout',
        ];

        foreach ($pages as $page) {
            $html = $this->actingAs($this->admin)->get($page)->assertOk()->getContent();

            $this->assertStringContainsString('nu-trace-mark.js', $html, $page.' must load the mark reader');
            // And must not be left on a copy cached before the last deployment.
            $this->assertMatchesRegularExpression('/nu-trace-mark\.js\?v=\d+/', $html, $page.' must version the mark reader');
            $this->assertStringContainsString('jsqr', strtolower($html), $page.' must load the decoder that locates the code');
            $this->assertStringContainsString('NUTraceQrMark.checkScan(', $html, $page.' must check the mark');
        }

        // The employee side scans too, and checks the same way.
        $this->actingAs($this->employee)
            ->get('/user/request-asset')
            ->assertOk()
            ->assertSee('NUTraceQrMark.checkScan(', false);
    }

    public function test_a_refused_scan_says_so_and_never_reads_an_asset(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        // The message comes from the module, and a refused scan returns before
        // the lookup - the scanner is not stopped and nothing is fetched.
        $this->assertStringContainsString('NUTraceQrMark.checkScan(payload)', $html);
        $this->assertStringContainsString('Scan refused — nothing was looked up.', $html);
    }

    public function test_the_printed_sticker_carries_the_mark_in_its_centre(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001')
            ->assertOk()
            ->getContent();

        // The mark is part of the sticker's own markup, centred on its code, and
        // is a plain <img> from a root-relative source - the artwork must never
        // depend on a host, a scheme or an SVG <image> that a browser can drop.
        $this->assertStringContainsString('class="trace-label__seal"', $html);
        $this->assertStringContainsString('src="/images/nu-trace-mark.jpg"', $html);
        $this->assertStringContainsString('.trace-label__seal {', $html);
        $this->assertStringContainsString('position: absolute;', $html);
    }

    public function test_the_registry_and_the_replacement_preview_draw_a_sealed_code(): void
    {
        $registry = $this->actingAs($this->admin)->get('/admin/assets/registry')->assertOk()->getContent();

        // The shared drawing path paints the mark, which is what makes the
        // registry's own download contain it too.
        $this->assertStringContainsString('NUTraceQrMark.sealHolder(holder)', $registry);
        $this->assertStringContainsString('nu-trace-mark.js', $registry);

        $this->actingAs($this->admin)
            ->get('/admin/replacement')
            ->assertOk()
            ->assertSee('NUTraceQrMark.dataUrl', false);
    }

    public function test_the_asset_page_draws_the_sticker_instead_of_showing_a_bare_code(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/'.$this->assetId())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('asset-qr-seal', $html);
        $this->assertStringContainsString('NUTraceQrMark.render(payload', $html);
    }
}
