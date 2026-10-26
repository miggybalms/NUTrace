<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetQr;
use App\Support\QrPayload;
use App\Support\QrSeal;
use App\Support\QrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The sealed sticker (version 2): nothing readable on the paper, and the copy
 * question answered honestly.
 *
 * The rules this file locks down:
 *
 *   1. a sticker's text no longer contains the asset code - a camera, a phone
 *      scanner or a QR-generator website shows an opaque blob
 *   2. only this system can turn that blob back into a code, and only the
 *      identifier travels: no asset details come back to a client without a
 *      session (the phone asks and then looks the asset up itself)
 *   3. a sticker sealed with a different payload key is "unavailable", not
 *      "fake": a second deployment must never libel genuine paper
 *   4. the old readable format still verifies, so stickers printed before this
 *      change keep working
 *   5. one sticker presented from two sources inside the window is flagged as
 *      a possible copy - a warning, never a refusal
 */
class QrOpaqueStickerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $head;

    private int $assetId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'alex@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->head = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Department Head', 'status' => 'Active',
        ]);

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->admin->id,
            'Asset_code' => 'AST-0001',
            'Asset_name' => 'Demo asset',
            'Category' => 'General and Office Equipment',
            'Condition' => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location' => 'Faculty Room',
            'accusion_date' => '2026-10-08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $key = QrSigner::generate(QrSigner::ED25519, 'V1');

        config([
            'qr.algorithm' => 'auto',
            'qr.keys' => 'V1:' . QrSigner::ED25519 . ':' . $key['secret'],
            'qr.active_key_id' => 'V1',
            'qr.duplicate_window_minutes' => 30,
        ]);

        QrSigner::flush();
        QrSeal::flush();
        AssetQr::clearCache();
    }

    protected function tearDown(): void
    {
        QrSigner::flush();
        QrSeal::flush();
        AssetQr::clearCache();

        parent::tearDown();
    }

    private function payload(string $code = 'AST-0001'): string
    {
        $payload = AssetQr::payloadFor($code);

        $this->assertIsString($payload, 'Expected a sealed payload for ' . $code);

        return (string) $payload;
    }

    private function verifyAs(User $user, string $scanned): array
    {
        return $this->actingAs($user)
            ->getJson('/qr/verify?payload=' . urlencode($scanned))
            ->assertOk()
            ->json();
    }

    /** The phone's credential: a signed bearer token, never a session cookie. */
    private function tokenFor(User $user): string
    {
        return \App\Support\QrMobileToken::issue($user)['token'];
    }

    /* ── 1. nothing readable on the paper ──────────────────────────────── */

    public function test_a_sticker_carries_no_readable_asset_code(): void
    {
        $payload = $this->payload('AST-0001');

        $this->assertStringStartsWith('NUT2-V1-', $payload);
        $this->assertStringNotContainsString('AST-0001', $payload);
        $this->assertStringNotContainsString('AST', $payload);

        // Every character is still QR alphanumeric mode, which is what keeps
        // the 20mm sticker readable.
        $this->assertMatchesRegularExpression('/^[A-Z0-9-]+$/', $payload);
    }

    public function test_the_server_reads_the_sealed_code_back(): void
    {
        $parsed = QrPayload::parse($this->payload('AST-0001'));

        $this->assertSame('signed', $parsed['type']);
        $this->assertSame(2, $parsed['version']);
        $this->assertTrue($parsed['sealed']);
        $this->assertSame('AST-0001', $parsed['code']);
        $this->assertSame('V1', $parsed['key_id']);
    }

    /* ── 2. the phone asks, and gets the identifier only ───────────────── */

    public function test_the_phone_endpoint_opens_a_sticker_for_a_signed_in_account(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($this->admin))
            ->postJson('/qr/resolve', ['payload' => $this->payload('AST-0001')])
            ->assertOk()
            ->assertJsonPath('status', 'valid')
            ->assertJsonPath('code', 'AST-0001')
            ->assertJsonPath('sealed', true)
            ->assertJsonPath('duplicate.suspected', false);

        // The phone scopes the asset itself, so the endpoint never returns
        // details: nothing about the owner, the department or the item.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Demo asset', $body);
        $this->assertStringNotContainsString('Faculty Room', $body);
        $this->assertArrayNotHasKey('asset', $response->json());
    }

    public function test_the_phone_endpoint_refuses_an_empty_payload(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($this->admin))
            ->postJson('/qr/resolve', ['payload' => ''])
            ->assertStatus(422);
    }

    /* ── 3. a different payload key is "unavailable", never "fake" ─────── */

    public function test_a_sticker_sealed_elsewhere_is_unavailable_not_fake(): void
    {
        $payload = $this->payload('AST-0001');

        // Another install: same signing keys (already shared, as they must be),
        // different payload key.
        config(['qr.payload_key' => 'base64:' . base64_encode(random_bytes(32))]);
        QrSeal::flush();

        $result = $this->verifyAs($this->admin, $payload);

        $this->assertSame('unavailable', $result['status']);
        $this->assertStringContainsString('neither confirmed nor rejected', $result['detail']);
        $this->assertNull($result['asset']);

        // The signature is still judged, and it is still genuine: only the
        // code could not be opened.
        $this->assertTrue($result['sealed']);
        $this->assertSame('V1', $result['key_id']);
    }

    public function test_the_payload_key_with_or_without_the_base64_marker_is_the_same_key(): void
    {
        // `qr:keygen --payload` prints `base64:<body>`; Railway asks for a
        // value without a `Name=`. Both spellings must derive the same seal,
        // or one install could not read the other's stickers.
        $raw = random_bytes(32);

        config(['qr.payload_key' => 'base64:' . base64_encode($raw)]);
        QrSeal::flush();
        AssetQr::clearCache();
        $prefixed = $this->payload('AST-0001');

        config(['qr.payload_key' => base64_encode($raw)]);
        QrSeal::flush();
        AssetQr::clearCache();

        $this->assertSame('AST-0001', QrPayload::parse($prefixed)['code']);
        $this->assertSame($prefixed, $this->payload('AST-0001'));
    }

    /* ── 4. stickers printed before this change still work ─────────────── */

    public function test_a_readable_version_one_sticker_still_verifies(): void
    {
        $signed = QrSigner::sign(QrPayload::message('AST-0001', 'V1'));
        $this->assertIsArray($signed);

        $legacy = QrPayload::build('AST-0001', $signed['key_id'], $signed['signature']);

        $this->assertStringContainsString('AST-0001', $legacy);

        $result = $this->verifyAs($this->admin, $legacy);

        $this->assertSame('valid', $result['status']);
        $this->assertFalse($result['sealed']);
        $this->assertSame('AST-0001', $result['code']);
    }

    /* ── 5. one sticker, two places ────────────────────────────────────── */

    public function test_the_same_sticker_from_a_second_source_is_flagged(): void
    {
        $payload = $this->payload('AST-0001');

        $first = $this->verifyAs($this->admin, $payload);
        $this->assertFalse($first['duplicate']['suspected']);

        // A second account scans the same sticker while the first is recent.
        $second = $this->verifyAs($this->head, $payload);

        $this->assertSame('valid', $second['status']);
        $this->assertTrue($second['duplicate']['suspected']);
        $this->assertSame(1, $second['duplicate']['others']);
        $this->assertSame(30, $second['duplicate']['window_minutes']);
        // The scan still resolves: this warns, it does not block.
        $this->assertSame('AST-0001', $second['asset']['asset_code']);
    }

    public function test_a_scan_before_the_window_is_not_a_duplicate(): void
    {
        $payload = $this->payload('AST-0001');

        // Another account scanned it two hours ago - outside the 30 minute
        // window, so this is a normal rescan, not a copy in use.
        DB::table('qr_scan_events')->insert([
            'fingerprint' => \App\Support\QrScans::fingerprint($payload),
            'asset_id' => $this->assetId,
            'asset_code' => 'AST-0001',
            'viewer_id' => $this->head->id,
            'source_key' => 'user:' . $this->head->id,
            'status' => 'valid',
            'seen_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $result = $this->verifyAs($this->admin, $payload);

        $this->assertFalse($result['duplicate']['suspected']);
    }

    public function test_two_different_stickers_are_not_the_same_sticker(): void
    {
        DB::table('assets')->insert([
            'user_id' => $this->admin->id,
            'Asset_code' => 'AST-0002',
            'Asset_name' => 'Office chair',
            'Category' => 'Furniture',
            'Condition' => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location' => 'Storage Room',
            'accusion_date' => '2026-10-08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->verifyAs($this->admin, $this->payload('AST-0001'));
        $other = $this->verifyAs($this->head, $this->payload('AST-0002'));

        $this->assertFalse($other['duplicate']['suspected']);
    }

    public function test_the_scan_endpoint_passes_the_warning_to_the_scanner(): void
    {
        $payload = $this->payload('AST-0001');

        // The admin scanner is admin-only, so this is a second administrator
        // at a different session - the "same sticker, someone else" case.
        DB::table('employee_numbers')->insert([
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Second Admin', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $second = User::create([
            'employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'admin2@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->actingAs($this->admin)->getJson('/admin/assets/scan?code=' . urlencode($payload))->assertOk();

        $this->actingAs($second)
            ->getJson('/admin/assets/scan?code=' . urlencode($payload))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.asset_code', 'AST-0001')
            ->assertJsonPath('verification.duplicate.suspected', true);
    }
}
