<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetQr;
use App\Support\AssetTag;
use App\Support\NutTag;
use App\Support\QrVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * NUTAG-1 stickers: the symbol ordinary QR readers cannot see.
 *
 * These tests cover the half that decides whether a sticker is genuine: the
 * token is stored once per code, the check value is an HMAC the server alone
 * can produce, and a tag is only ever resolved to an asset whose live row says
 * so. The other half - that a QR app finds no code in the image - is measured
 * with a real QR decoder in scripts/nutag-check.js.
 */
class AssetTagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        AssetTag::clearCache();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Asset Office', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Tag Admin', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1,
            'department_id' => 1,
            'email' => 'admin@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'),
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        DB::table('assets')->insert([
            'user_id' => $this->admin->id,
            'Asset_code' => 'AST-TAG-0001',
            'Asset_name' => 'Tagged Laptop',
            'Category' => 'Info and Equipment',
            'Condition' => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location' => 'IT Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_one_code_gets_one_stable_tag(): void
    {
        $first = AssetTag::textFor('AST-TAG-0001');
        $second = AssetTag::textFor('AST-TAG-0001');

        $this->assertNotNull($first);
        $this->assertSame($first, $second, 'a reprint must carry the same identity, not a new one');
        $this->assertSame(1, DB::table('asset_tags')->count());
        $this->assertSame(1, preg_match('/^NUTAG1-[0-9A-F]{32}-[0-9A-F]{16}$/', (string) $first));
    }

    public function test_the_tag_carries_no_asset_code_or_url(): void
    {
        $text = (string) AssetTag::textFor('AST-TAG-0001');

        // Everything on the paper is opaque: no code, no owner, no link. That
        // is what a scanner website gets to see - nothing it can use.
        $this->assertStringNotContainsString('AST-TAG', $text);
        $this->assertStringNotContainsString('http', strtolower($text));
        $this->assertStringNotContainsString('Laptop', $text);
    }

    public function test_a_genuine_tag_resolves_to_its_asset(): void
    {
        $result = AssetTag::verify((string) AssetTag::textFor('AST-TAG-0001'), $this->admin);

        $this->assertSame(QrVerification::VALID, $result['status']);
        $this->assertSame('AST-TAG-0001', $result['code']);
        $this->assertSame('Tagged Laptop', $result['asset']['name']);
    }

    public function test_a_changed_check_value_is_refused_without_looking_the_asset_up(): void
    {
        $text = (string) AssetTag::textFor('AST-TAG-0001');
        [$prefix, $token, $mac] = explode('-', $text);

        $tampered = $prefix.'-'.$token.'-'.($mac[0] === 'A' ? 'B' : 'A').substr($mac, 1);

        $result = AssetTag::verify($tampered, $this->admin);

        $this->assertSame(QrVerification::INVALID_SIGNATURE, $result['status']);
        $this->assertNull($result['asset'], 'an altered tag must not be dressed up with a real asset');
        $this->assertNull($result['code']);
    }

    public function test_a_token_this_system_never_issued_is_refused(): void
    {
        $token = random_bytes(NutTag::TOKEN_BYTES);
        $text = NutTag::text($token, NutTag::mac($token, AssetTag::key()));

        $result = AssetTag::verify($text, $this->admin);

        $this->assertSame(QrVerification::UNKNOWN_ASSET, $result['status']);
        $this->assertNull($result['asset']);
    }

    public function test_a_revoked_sticker_is_refused(): void
    {
        $text = (string) AssetTag::textFor('AST-TAG-0001');

        DB::table('assets')->where('Asset_code', 'AST-TAG-0001')->update(['qr_revoked_at' => now()]);

        $result = AssetTag::verify($text, $this->admin);

        $this->assertSame(QrVerification::REVOKED, $result['status']);
    }

    public function test_the_qr_verifier_accepts_a_tag(): void
    {
        // Every screen that already verifies scanned text (dashboard lookup,
        // pullout, request forms) goes through this method; a tag must not need
        // a second code path there.
        $result = QrVerification::verify((string) AssetTag::textFor('AST-TAG-0001'), $this->admin);

        $this->assertSame(QrVerification::VALID, $result['status']);
        $this->assertSame('AST-TAG-0001', $result['code']);
    }

    public function test_the_verification_endpoint_resolves_a_tag(): void
    {
        $text = (string) AssetTag::textFor('AST-TAG-0001');

        $this->actingAs($this->admin)
            ->get('/tag/verify?tag='.urlencode($text))
            ->assertOk()
            ->assertJsonPath('status', QrVerification::VALID)
            ->assertJsonPath('code', 'AST-TAG-0001');
    }

    public function test_the_verification_endpoint_refuses_a_tampered_tag(): void
    {
        $text = (string) AssetTag::textFor('AST-TAG-0001');
        [$prefix, $token, $mac] = explode('-', $text);

        $this->actingAs($this->admin)
            ->get('/tag/verify?token='.$token.'&mac='.($mac[0] === 'A' ? 'B' : 'A').substr($mac, 1))
            ->assertOk()
            ->assertJsonPath('status', QrVerification::INVALID_SIGNATURE);
    }

    public function test_the_sticker_text_is_a_tag_and_the_symbol_draws_every_dark_cell(): void
    {
        $this->assertStringStartsWith('NUTAG1-', AssetQr::stickerText('AST-TAG-0001'));

        $cells = NutTag::matrixFromText((string) AssetTag::textFor('AST-TAG-0001'));
        $dark = 0;

        foreach ($cells as $row) {
            foreach ($row as $cell) {
                $dark += $cell ? 1 : 0;
            }
        }

        $svg = NutTag::svg($cells);

        $this->assertSame($dark, substr_count($svg, 'h1v1h-1z'));
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg);
    }

    public function test_a_nutag_is_written_and_read_back_through_its_matrix(): void
    {
        $text = (string) AssetTag::textFor('AST-TAG-0001');

        $read = NutTag::read(NutTag::matrixFromText($text));

        $this->assertSame(NutTag::fromText($text), $read);
    }

    /**
     * No screen may hand a new sticker to a QR renderer or a QR service.
     *
     * Both would put the tag's text in an image URL and draw it as an ordinary
     * QR, which is precisely what a phone or a scanning website must never get
     * from a NU Trace sticker.
     */
    public function test_no_screen_turns_a_tag_into_a_qr(): void
    {
        AssetQr::clearCache();

        $pages = [
            '/admin/assets/registry',
            '/admin/replacement',
            '/admin/assets/1',
            '/admin/assets/labels?codes=AST-TAG-0001',
        ];

        foreach ($pages as $page) {
            $html = $this->actingAs($this->admin)->get($page)->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'data=NUTAG1-',
                $html,
                $page.' must not pass a sticker to the QR service'
            );
        }

        // The printed sticker carries the symbol itself, not a payload for a
        // browser-side QR library to draw later.
        $this->actingAs($this->admin)
            ->get('/admin/assets/labels?codes=AST-TAG-0001')
            ->assertOk()
            ->assertSee('trace-label__qr--tag', false)
            ->assertDontSee('data-qr-payload="NUTAG1-', false);
    }
}
