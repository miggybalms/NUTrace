<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetLabel;
use App\Support\AssetQr;
use App\Support\QrPayload;
use App\Support\QrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The printed asset sticker and the sheet it comes from.
 *
 * Two properties matter and are locked down here:
 *
 *  1. Printing respects the filters. The sheet applies the *same* filter set as
 *     the department page (App\Support\DepartmentAssets), so a printout can
 *     only ever contain assets the filtered page was showing.
 *  2. The sticker carries the asset's own identifier and always fills its
 *     box — the code is printed on its own centred line, the QR encodes that
 *     same code, and one sticker prints on a 50mm × 25mm page — the die-cut
 *     label on the Zebra ZD230 roll.
 */
class AssetLabelPrintTest extends TestCase
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

        $this->makeAsset('AST-0001', 'Demo asset', 'Active', 'Faculty Room', 'General and Office Equipment');
        $this->makeAsset('AST-0002', 'Office chair', 'Active', 'Storage Room', 'Furniture');
        $this->makeAsset('AST-0003', 'Work table', 'Acquired', 'Faculty Room', 'Furniture');
    }

    private function makeAsset(
        string $code,
        string $name,
        string $status,
        string $location,
        string $category,
        array $overrides = []
    ): int {
        return (int) DB::table('assets')->insertGetId($overrides + [
            'user_id'          => $this->employee->id,
            'Asset_code'       => $code,
            'Asset_name'       => $name,
            'Category'         => $category,
            'Condition'        => 'Good',
            'Lifecycle_Status' => $status,
            'asset_location'   => $location,
            'accusion_date'    => '2026-10-08',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_the_label_sheet_is_not_public(): void
    {
        $this->get('/admin/assets/department/1/labels')->assertRedirect('/');
        $this->get('/admin/assets/labels?codes=AST-0001')->assertRedirect('/');
        $this->get('/admin/assets/label-preview?code=AST-0001')->assertRedirect('/');
    }

    public function test_a_department_sheet_prints_every_asset_it_lists(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/assets/department/1/labels');

        $response->assertOk()
            ->assertSee('data-asset-code="AST-0001"', false)
            ->assertSee('data-asset-code="AST-0002"', false)
            ->assertSee('data-asset-code="AST-0003"', false)
            // A batch defaults to the Zebra roll: one 50 × 25mm sticker per page.
            ->assertSee('size: 50mm 25mm', false);
    }

    public function test_a_batch_prints_one_sticker_per_roll_label_by_default(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels')
            ->assertOk()
            ->getContent();

        // The page is the sticker, with no margin, and each sticker starts a new
        // label — this is what makes the ZD230 feed one die-cut label per sticker.
        $this->assertStringContainsString('@page { size: 50mm 25mm; margin: 0; }', $html);
        $this->assertStringContainsString('break-after: page;', $html);
        $this->assertStringContainsString('--label-h: 25mm;', $html);

        // Two layouts are offered, and the roll is the one selected.
        $this->assertStringContainsString('layout=sheet', $html);
        $this->assertStringContainsString('Label roll (50 × 25)', $html);
    }

    public function test_the_a4_layout_is_opt_in(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?layout=sheet');

        $response->assertOk()
            ->assertSee('size: A4', false)
            ->assertDontSee('size: 50mm 25mm', false);
    }

    public function test_one_asset_always_prints_on_its_own_roll_label(): void
    {
        // Even when the A4 layout is asked for, a single sticker keeps the roll
        // page: there is nothing to arrange on a sheet.
        $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001&layout=sheet')
            ->assertOk()
            ->assertSee('size: 50mm 25mm', false);
    }

    public function test_the_location_filter_decides_what_is_printed(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?location=Faculty%20Room');

        $response->assertOk()
            ->assertSee('data-asset-code="AST-0001"', false)
            ->assertSee('data-asset-code="AST-0003"', false)
            ->assertDontSee('data-asset-code="AST-0002"', false);
    }

    public function test_status_category_and_search_filters_all_narrow_the_printout(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?status=active')
            ->assertOk()
            ->assertSee('data-asset-code="AST-0001"', false)
            ->assertDontSee('data-asset-code="AST-0003"', false);

        $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?category=Furniture')
            ->assertOk()
            ->assertSee('data-asset-code="AST-0002"', false)
            ->assertDontSee('data-asset-code="AST-0001"', false);

        // Search matches the accountable person, the name and the code.
        $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?search=chair')
            ->assertOk()
            ->assertSee('data-asset-code="AST-0002"', false)
            ->assertDontSee('data-asset-code="AST-0001"', false);
    }

    public function test_filters_combine_instead_of_overruling_each_other(): void
    {
        // Faculty Room + Furniture = the table only.
        $response = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?location=Faculty%20Room&category=Furniture');

        $response->assertOk()
            ->assertSee('data-asset-code="AST-0003"', false)
            ->assertDontSee('data-asset-code="AST-0001"', false)
            ->assertDontSee('data-asset-code="AST-0002"', false);
    }

    public function test_one_asset_prints_on_a_single_label_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001');

        $response->assertOk()
            ->assertSee('size: 50mm 25mm', false)
            ->assertSee('data-asset-code="AST-0001"', false)
            ->assertDontSee('data-asset-code="AST-0002"', false)
            // The code is printed in its own centred line, above the cells.
            ->assertSee('class="trace-label__code">AST-0001', false)
            ->assertSee('Faculty Room', false);
    }

    public function test_the_same_filter_set_prints_the_same_number_of_labels_as_the_page_lists(): void
    {
        $page = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1?location=Faculty%20Room');

        $page->assertOk();

        $listed = $page->viewData('assets')->pluck('id')->all();
        $this->assertSame(['AST-0001', 'AST-0003'], $listed);

        $sheet = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?location=Faculty%20Room')
            ->assertOk();

        foreach ($listed as $code) {
            $sheet->assertSee('data-asset-code="'.$code.'"', false);
        }

        $sheet->assertDontSee('data-asset-code="AST-0002"', false);
    }

    public function test_the_department_page_exposes_the_location_filter_and_the_print_action(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/assets/department/1');

        $response->assertOk()
            ->assertSee('name="location"', false)
            ->assertSee('All Locations', false)
            ->assertSee('openLabelSheet()', false);
    }

    public function test_an_empty_selection_says_so_instead_of_printing_a_blank_sheet(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?location=Nowhere');

        $response->assertOk()
            ->assertSee('Nothing to print', false)
            ->assertDontSee('class="trace-label"', false);
    }

    public function test_the_registry_prints_its_just_registered_batch_from_the_same_sheet(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/assets/labels?codes=AST-0002,AST-0003');

        $response->assertOk()
            ->assertSee('data-asset-code="AST-0002"', false)
            ->assertSee('data-asset-code="AST-0003"', false)
            ->assertDontSee('data-asset-code="AST-0001"', false)
            ->assertSee('size: 50mm 25mm', false);

        // The A4 sheet is still reachable from the same batch.
        $this->actingAs($this->admin)
            ->get('/admin/assets/labels?codes=AST-0002,AST-0003&layout=sheet')
            ->assertOk()
            ->assertSee('size: A4', false);
    }

    public function test_the_registry_prints_an_asset_that_is_not_saved_yet(): void
    {
        $response = $this->actingAs($this->admin)->get(
            '/admin/assets/label-preview?code=AST-NEW-9&name=Testing&location=Faculty%20Room&category=Furniture&acquired=2026-10-09'
        );

        $response->assertOk()
            ->assertSee('size: 50mm 25mm', false)
            ->assertSee('AST-NEW-9', false)
            ->assertSee('Testing', false)
            ->assertSee('Faculty Room', false)
            ->assertSee('10/9/2026', false);
    }

    public function test_a_preview_without_a_code_prints_nothing_rather_than_a_blank_sticker(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/assets/label-preview')
            ->assertOk()
            ->assertSee('Nothing to print', false)
            ->assertDontSee('class="trace-label"', false);
    }

    public function test_the_qr_is_drawn_from_the_asset_code_that_is_printed_on_the_sticker(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001')
            ->assertOk()
            ->getContent();

        // The code the sticker shows is the code the QR encodes: the same value
        // the scanner looks up, never a re-derived identifier.
        $this->assertStringContainsString('class="trace-label__qr" data-asset-code="AST-0001"', $html);
        $this->assertStringContainsString('class="trace-label__code">AST-0001', $html);
    }

    /** Put one signing key in the ring and make it active. */
    private function useSigningKey(): void
    {
        $key = QrSigner::generate(QrSigner::ED25519, 'V1');

        config([
            'qr.algorithm' => 'auto',
            'qr.keys' => 'V1:' . QrSigner::ED25519 . ':' . $key['secret'],
            'qr.active_key_id' => 'V1',
        ]);

        QrSigner::flush();
        \App\Support\QrSeal::flush();
        AssetQr::clearCache();
    }

    public function test_a_reprint_carries_the_sealed_payload_the_asset_already_holds(): void
    {
        $this->useSigningKey();

        // The record holds a sealed payload minted when the sticker was first
        // printed. A reprint has to carry that same payload, so the paper and
        // the record agree.
        $payload = (string) AssetQr::payloadFor('AST-0002');
        $this->assertStringStartsWith('NUT2-', $payload);

        DB::table('assets')->where('Asset_code', 'AST-0002')->update(['qr_payload' => $payload]);

        $single = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0002')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-qr-payload="'.$payload.'"', $single);
        $this->assertStringNotContainsString('data-qr-payload="AST-0002"', $single);

        // The registry's batch sheet reads the same record and prints the same
        // sticker.
        $batch = $this->actingAs($this->admin)
            ->get('/admin/assets/labels?codes=AST-0002')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-qr-payload="'.$payload.'"', $batch);
    }

    public function test_a_reprint_replaces_an_old_readable_payload_with_a_sealed_one(): void
    {
        $this->useSigningKey();

        // A sticker printed before sealing stored a readable payload. Printing
        // that again would put the asset code back on the paper, so the sheet
        // mints a sealed one instead for the same asset.
        $readable = QrPayload::build('AST-0002', 'V1', str_repeat("\x01", 64));
        DB::table('assets')->where('Asset_code', 'AST-0002')->update(['qr_payload' => $readable]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0002')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($readable, $html);

        preg_match('/data-qr-payload="([^"]+)"/', $html, $matches);
        $printed = $matches[1] ?? '';

        $this->assertStringStartsWith('NUT2-', $printed);
        $this->assertStringNotContainsString('AST-0002', $printed);
        $this->assertSame('AST-0002', QrPayload::parse($printed)['code']);
    }

    public function test_a_stored_payload_for_another_code_is_never_printed(): void
    {
        // This test is about the *fallback*: pin an install that cannot mint a
        // fresh payload, so the sticker has to carry its own plain code. (A
        // deployment with a keyring seals a new one instead, which is also
        // its own code and is covered by QrSignatureTest.)
        config(['qr.keys' => '', 'qr.active_key_id' => null]);
        QrSigner::flush();
        AssetQr::clearCache();

        // A payload naming a different asset would put the wrong sticker on the
        // wrong object, so the asset's own code is printed instead.
        $foreign = QrPayload::build('AST-0001', 'V2', str_repeat("\x02", 64));
        DB::table('assets')->where('Asset_code', 'AST-0002')->update(['qr_payload' => $foreign]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0002')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($foreign, $html);
        $this->assertStringContainsString('data-qr-payload="AST-0002"', $html);
    }

    public function test_the_pages_that_print_still_render(): void
    {
        // The registry and the replacement page lost their own sticker builders
        // in favour of the shared sheet, so both are smoke-tested here.
        $this->actingAs($this->admin)->get('/admin/assets/registry')->assertOk();
        $this->actingAs($this->admin)->get('/admin/replacement')->assertOk();
    }

    public function test_the_stored_qr_image_is_used_when_the_asset_has_one(): void
    {
        $this->makeAsset('AST-0004', 'Laptop', 'Active', 'Faculty Room', 'General and Office Equipment', [
            'qr_code_path' => 'assets/qr/AST-0004-1.png',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0004')
            ->assertOk()
            ->assertSee('assets/qr/AST-0004-1.png', false);
    }

    public function test_the_shipped_seal_is_a_transparent_png(): void
    {
        $path = public_path('images/nu-seal.png');

        $this->assertFileExists($path, 'the official seal must ship with the app as the bundled copy');

        $bytes = (string) file_get_contents($path);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($bytes, 0, 8), 'the seal must be a PNG');

        // IHDR: width, height, bit depth, colour type. Colour type 6 is RGBA, so
        // this is the assertion that the seal's background is transparent and
        // not the white card the artwork was supplied on.
        $ihdr = unpack('Nwidth/Nheight/Cdepth/Ctype', substr($bytes, 16, 18));

        $this->assertSame(6, $ihdr['type'], 'the seal must carry an alpha channel');
        $this->assertSame(8, $ihdr['depth']);
        $this->assertGreaterThan(200, $ihdr['width']);
        $this->assertGreaterThan(200, $ihdr['height']);
    }

    public function test_the_label_sheet_falls_back_to_the_bundled_seal(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alt="NU seal"', $html);
        $this->assertStringContainsString('nu-seal.png', $html);

        // The published copy lives on the media disk. Until the file is uploaded
        // there the URL 404s, and the sticker has to fall back to the bundled
        // copy instead of printing a header with no seal in it.
        $this->assertStringContainsString("this.onerror=null;this.src='/images/nu-seal.png'", $html);
    }

    public function test_the_seal_keeps_its_own_proportions_in_its_slot(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001')
            ->getContent();

        // Height-driven with max-width, never a forced square: the supplied
        // artwork is 318x383, so stretching it would be immediately visible.
        $this->assertStringContainsString('--logo-size: 3.6mm', $html);
        $this->assertStringContainsString('height: var(--logo-size)', $html);
        $this->assertStringContainsString('width: auto', $html);
        $this->assertStringContainsString('max-width: 100%', $html);
    }

    public function test_the_qr_keeps_a_four_module_quiet_zone_of_its_own(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001')
            ->getContent();

        // The code is drawn as an SVG whose viewBox carries four modules of
        // white on every side. A quiet zone inside the artwork cannot be
        // covered by a caption, a rule or the edge of the label box.
        $this->assertStringContainsString('var quiet = 4;', $html);
        $this->assertStringContainsString("var total = count + quiet * 2;", $html);
        $this->assertStringContainsString('shape-rendering="crispEdges"', $html);
        $this->assertStringContainsString('fill="#ffffff"', $html);

        // The QR column is the square and nothing else: no caption, no border
        // and no padding eating into the 20mm.
        $partial = view('partials.asset_label', ['label' => [
            'code' => 'AST-0001', 'name' => 'Demo asset', 'location' => 'Faculty Room',
            'category' => 'Furniture', 'acquired' => '10/8/2026', 'qr' => null,
            'qr_payload' => 'NUT1-AST-0001-V1-ABCDEF', 'logo' => null,
        ]])->render();

        $qr = substr($partial, strpos($partial, 'class="trace-label__qr"'));
        $this->assertStringNotContainsString('trace-label__cells', $qr);
        $this->assertStringNotContainsString('<span class="scan"', $qr);
        $this->assertStringContainsString('class="trace-label__qr-draw"', $qr);
    }

    public function test_the_sticker_shows_the_official_seal_only_when_one_is_available(): void
    {
        $fields = [
            'code' => 'AST-0001', 'name' => 'Demo asset', 'location' => 'Faculty Room',
            'category' => 'Furniture', 'acquired' => '2026-10-08',
        ];

        // With the seal: the header carries it.
        $withSeal = view('partials.asset_label', [
            'label' => $fields + ['qr' => null, 'logo' => '/images/nu-seal.png'],
        ])->render();

        $this->assertStringContainsString('class="trace-label__mark"', $withSeal);
        $this->assertStringContainsString('src="/images/nu-seal.png"', $withSeal);

        // Without it: the slot stays empty instead of printing a broken image,
        // and no other artwork is substituted.
        $withoutSeal = view('partials.asset_label', ['label' => $fields + ['qr' => null, 'logo' => null]])->render();

        $this->assertStringNotContainsString('<img', $withoutSeal);
        $this->assertStringContainsString('ASSET MANAGEMENT OFFICE', $withoutSeal);
    }

    public function test_a_label_never_prints_a_value_that_could_break_the_sticker_box(): void
    {
        $label = AssetLabel::fromInput([
            'code' => 'AST-0001',
            'name' => "Demo   asset\nwith   newlines",
            'location' => str_repeat('Very long room name ', 10),
        ]);

        $this->assertSame('Demo asset with newlines', $label['name']);
        $this->assertLessThanOrEqual(61, mb_strlen($label['location']));
        $this->assertSame('—', $label['acquired']);
        $this->assertSame('AST-0001', $label['code']);
    }

    public function test_the_sticker_keeps_its_fixed_50_by_25_box(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001')
            ->getContent();

        // Fixed geometry at the size of the die-cut roll, and a square QR that
        // cannot be stretched. 50 × 25mm is the ZD230's 1.969" × 0.984" stock:
        // the page has to be that size or Chrome scales the sticker down.
        $this->assertStringContainsString('--label-w: 50mm', $html);
        $this->assertStringContainsString('--label-h: 25mm', $html);
        $this->assertStringContainsString('--qr-size: 21mm', $html);

        // The artwork is drawn 1mm inside the stock, so the border is never on
        // the die line where the thermal head cannot reach it.
        $this->assertStringContainsString('--label-inset: 1mm', $html);
        $this->assertStringContainsString('width: calc(var(--label-w) - 2 * var(--label-inset))', $html);
        $this->assertStringContainsString('height: calc(var(--label-h) - 2 * var(--label-inset))', $html);
        $this->assertStringContainsString('margin: var(--label-inset) auto;', $html);
        $this->assertStringContainsString('aspect-ratio: 1 / 1', $html);
        $this->assertStringContainsString('object-fit: contain', $html);
        $this->assertStringContainsString('overflow: hidden', $html);

        // The header and footer are fixed bands, so the 13.6mm between them is
        // always available to the code and fields however long they are.
        $this->assertStringContainsString('--head-h: 6.6mm', $html);
        $this->assertStringContainsString('--foot-h: 2.2mm', $html);
        $this->assertStringContainsString('height: var(--head-h)', $html);
        $this->assertStringContainsString('height: var(--foot-h)', $html);

        // The QR is a full-height column of its own, so it is not squeezed by
        // the text bands — it is the largest square the 25mm stock allows.
        $this->assertStringContainsString('grid-template-columns: 1fr var(--qr-col);', $html);
        $this->assertStringContainsString('--qr-col: calc(var(--qr-size) + 0.7mm);', $html);

        // Nothing the sticker exists to carry ends in an ellipsis: the code
        // shrinks to fit its line, and a field wraps instead of being cut.
        // (The ellipsis rules stay as the no-JS fallback only.)
        $this->assertStringContainsString('text-overflow: ellipsis', $html);
        $this->assertStringContainsString('white-space: nowrap', $html);
        $this->assertStringContainsString('overflow-wrap: anywhere', $html);
        $this->assertStringContainsString('font-size: var(--cell-pt, 4.1pt)', $html);
        $this->assertStringContainsString('function fitLabelText', $html);
        $this->assertStringContainsString('fitLabels();', $html);
    }

    public function test_the_sticker_size_is_configurable(): void
    {
        // A different roll only needs config (or ASSET_LABEL_WIDTH_MM /
        // ASSET_LABEL_HEIGHT_MM in .env), not a change to any view.
        config(['asset_label.width_mm' => 60, 'asset_label.height_mm' => 30]);

        $html = $this->actingAs($this->admin)
            ->get('/admin/assets/department/1/labels?asset=AST-0001')
            ->getContent();

        $this->assertStringContainsString('@page { size: 60mm 30mm; margin: 0; }', $html);
        $this->assertStringContainsString('--label-w: 60mm;', $html);
        $this->assertStringContainsString('--label-h: 30mm;', $html);
    }

    public function test_a_nonsensical_size_falls_back_to_the_loaded_stock(): void
    {
        config(['asset_label.width_mm' => 0, 'asset_label.height_mm' => 'wide']);

        $this->assertSame(50.0, AssetLabel::widthMm());
        $this->assertSame(25.0, AssetLabel::heightMm());
    }

    public function test_the_qr_never_grows_taller_than_the_sticker(): void
    {
        // A 20mm QR in a 22mm-tall sticker would be clipped by the label box,
        // and a clipped finder pattern never scans.
        config(['asset_label.height_mm' => 22]);

        $this->assertLessThanOrEqual(AssetLabel::heightMm() - 2 * AssetLabel::insetMm(), AssetLabel::qrMm());
    }
}
