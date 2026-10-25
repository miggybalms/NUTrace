<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetQr;
use App\Support\QrPayload;
use App\Support\QrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Signed asset QR codes: what a scan is allowed to conclude.
 *
 * The rules this file locks down, in the order a scan is resolved:
 *
 *   1. a genuine sticker for an asset in the database verifies
 *   2. a payload whose code or signature was edited is refused, and its
 *      identifier is not looked up (a forgery must not borrow real details)
 *   3. a payload signed by a key this install does not hold is reported as
 *      such, while a retired key kept as a public key still verifies its
 *      stickers - rotation must not invalidate paper already on assets
 *   4. a plain asset code (a sticker printed before signing) still works, and
 *      is always labelled unsigned - never verified
 *   5. an install with no keyring says "verification unavailable", never "fake"
 *   6. only the department that holds an asset sees its details after a scan
 *   7. a revoked sticker is refused until it is reissued
 *   8. the private key never appears in a page, a response, or the payload
 */
class QrSignatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $head;

    private User $employee;

    private User $outsider;

    private int $ownAsset;

    private int $colleagueAsset;

    private int $outsideAsset;

    /** The active key's secret material, to prove it never leaks. */
    private string $secret = '';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Other Dept', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = $this->makeUser(1, 1, 'alex@nu-lipa.edu.ph', 'Admin');
        $this->employee = $this->makeUser(2, 1, 'nena@nu-lipa.edu.ph', 'Employee');
        $this->head = $this->makeUser(3, 1, 'head@nu-lipa.edu.ph', 'Department Head');
        $this->outsider = $this->makeUser(4, 2, 'other@nu-lipa.edu.ph', 'Employee');

        $this->ownAsset = $this->makeAsset('AST-0001', $this->employee);
        $this->colleagueAsset = $this->makeAsset('AST-0002', $this->head);
        $this->outsideAsset = $this->makeAsset('AST-2001', $this->outsider);

        $this->useSigningKey('V1');
    }

    /* ── helpers ───────────────────────────────────────────────────────── */

    private function makeUser(int $employeeNumberId, int $departmentId, string $email, string $role): User
    {
        return User::create([
            'employee_numbers_id' => $employeeNumberId,
            'department_id' => $departmentId,
            'email' => $email,
            'password' => Hash::make('secret-password'),
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function makeAsset(string $code, User $owner): int
    {
        return (int) DB::table('assets')->insertGetId([
            'user_id' => $owner->id,
            'Asset_code' => $code,
            'Asset_name' => 'Demo asset',
            'Category' => 'General and Office Equipment',
            'Condition' => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location' => 'Faculty Room',
            'accusion_date' => '2026-10-08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Put one signing key in the ring and make it active. */
    private function useSigningKey(string $id = 'V1', string $algorithm = QrSigner::ED25519): array
    {
        $key = QrSigner::generate($algorithm, $id);
        $this->secret = $key['secret'];

        config([
            'qr.algorithm' => 'auto',
            'qr.keys' => $id . ':' . $algorithm . ':' . $key['secret'],
            'qr.active_key_id' => $id,
        ]);

        QrSigner::flush();
        AssetQr::clearCache();

        return $key;
    }

    private function payloadFor(string $code): string
    {
        $payload = AssetQr::payloadFor($code);
        $this->assertIsString($payload, 'Expected a signed payload for ' . $code);

        return (string) $payload;
    }

    /** A signed payload for a code that does not have to exist in the database. */
    private function signedPayload(string $code): string
    {
        $signed = QrSigner::sign(QrPayload::message($code, (string) QrSigner::activeKeyId()));

        $this->assertIsArray($signed, 'Expected the keyring to sign ' . $code);

        return QrPayload::build($code, $signed['key_id'], $signed['signature']);
    }

    private function verifyAs(User $user, string $scanned): array
    {
        return $this->actingAs($user)
            ->getJson('/qr/verify?payload=' . urlencode($scanned))
            ->assertOk()
            ->json();
    }

    private function scanAs(User $user, string $scanned): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->getJson('/admin/assets/scan?code=' . urlencode($scanned));
    }

    /** The QR service's answer, so a test never needs the network. */
    private function fakeQrService(): void
    {
        Http::fake([
            'api.qrserver.com/*' => Http::response("\x89PNG\r\n\x1a\n" . str_repeat("\0", 300), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    /* ── 1. a genuine sticker ──────────────────────────────────────────── */

    public function test_a_signed_sticker_for_an_existing_asset_verifies(): void
    {
        $payload = $this->payloadFor('AST-0001');

        // The payload stays inside the QR's alphanumeric character set, which
        // is what keeps the code small enough for a 20mm sticker - and it no
        // longer carries the asset code in the open, so a camera or a
        // QR-generator website has nothing to read off the paper.
        $this->assertMatchesRegularExpression('/^NUT2-V1-[A-Z2-7]+-[A-Z2-7]+$/', $payload);
        $this->assertStringNotContainsString('AST-0001', $payload);
        $this->assertTrue($this->verifyAs($this->admin, $payload)['sealed']);

        $result = $this->verifyAs($this->admin, $payload);

        $this->assertSame('valid', $result['status']);
        $this->assertSame('Verified sticker', $result['label']);
        $this->assertTrue($result['signed']);
        $this->assertSame('V1', $result['key_id']);
        $this->assertSame('AST-0001', $result['code']);
        $this->assertTrue($result['permitted']);
        $this->assertSame('AST-0001', $result['asset']['asset_code']);
        $this->assertSame('Demo asset', $result['asset']['name']);
    }

    public function test_the_asset_details_come_from_the_database_not_from_the_payload(): void
    {
        $payload = $this->payloadFor('AST-0001');

        // Rename the asset after the sticker was printed.
        DB::table('assets')->where('id', $this->ownAsset)->update(['Asset_name' => 'Renamed after printing']);

        $result = $this->verifyAs($this->admin, $payload);

        $this->assertSame('valid', $result['status']);
        $this->assertSame('Renamed after printing', $result['asset']['name']);
    }

    public function test_the_scan_endpoint_still_reports_the_asset_it_always_did(): void
    {
        $response = $this->scanAs($this->admin, $this->payloadFor('AST-0001'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.asset_code', 'AST-0001')
            ->assertJsonPath('data.department', 'Demo Department')
            ->assertJsonPath('data.owner', 'Aling Nena')
            ->assertJsonPath('verification.status', 'valid');
    }

    /**
     * The lookup must name the departments columns the way PostgreSQL folds
     * them.
     *
     * `departments` is migrated with a quoted "Name" column. Raw SQL that says
     * `departments.Name` is folded to `name` by PostgreSQL, the query errors,
     * and the scan used to answer "Asset not found" for every asset in the
     * database. SQLite resolves identifiers case-insensitively, so the rest of
     * this file kept passing while the deployed site could not scan at all;
     * this test inspects the SQL instead of the result.
     */
    public function test_the_scan_lookup_names_the_department_columns_as_postgresql_stores_them(): void
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->scanAs($this->admin, 'AST-0001')->assertOk();

        $lookup = collect($queries)->first(
            fn (string $sql) => str_contains($sql, 'Asset_code') && str_contains($sql, 'asset_files')
        );

        $this->assertNotNull($lookup, 'A scan must look the asset up by its code.');
        $this->assertStringContainsString('"departments"."Name"', $lookup);
        $this->assertStringContainsString('"holder_departments"."Name"', $lookup);
        $this->assertStringNotContainsString('departments.Name', $lookup);
    }

    /* ── 2. altered payloads ───────────────────────────────────────────── */

    /** Swap one character of a payload's sealed body, leaving its shape intact. */
    private function tamperBody(string $payload): string
    {
        $parts = explode('-', $payload);
        $body = $parts[2];
        $body[0] = $body[0] === 'A' ? 'B' : 'A';
        $parts[2] = $body;

        return implode('-', $parts);
    }

    /**
     * Swap one character inside a payload's signature, leaving its body and its
     * encoding intact. (The final character is avoided: base32's trailing bits
     * must stay zero, so flipping it would make the signature unreadable
     * rather than wrong.)
     */
    private function tamperSignature(string $payload): string
    {
        $position = strlen($payload) - 10;

        return substr($payload, 0, $position)
            . (substr($payload, $position, 1) === 'A' ? 'B' : 'A')
            . substr($payload, $position + 1);
    }

    public function test_an_edited_sealed_body_is_refused_and_never_looked_up(): void
    {
        $tampered = $this->tamperBody($this->payloadFor('AST-0001'));

        $result = $this->verifyAs($this->admin, $tampered);

        $this->assertSame('invalid_signature', $result['status']);
        // Other assets exist, but a payload that fails its check must not be
        // rewarded with any asset's details.
        $this->assertNull($result['asset']);
        $this->assertFalse($result['permitted']);

        $this->scanAs($this->admin, $tampered)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('verification.status', 'invalid_signature');
    }

    /**
     * One asset's genuine body under another asset's sticker: the seal is
     * intact and the signature is real, but they belong to different stickers,
     * so the payload must be refused rather than resolving to either asset.
     */
    public function test_a_body_grafted_onto_another_sticker_is_refused(): void
    {
        $mine = explode('-', $this->payloadFor('AST-0001'));
        $theirs = explode('-', $this->payloadFor('AST-0002'));

        $grafted = implode('-', [$mine[0], $mine[1], $theirs[2], $mine[3]]);

        $result = $this->verifyAs($this->admin, $grafted);

        $this->assertSame('invalid_signature', $result['status']);
        $this->assertNull($result['asset']);
        $this->assertFalse($result['permitted']);
    }

    public function test_an_edited_signature_is_refused(): void
    {
        $this->assertSame('invalid_signature', $this->verifyAs($this->admin, $this->tamperSignature($this->payloadFor('AST-0001')))['status']);
    }

    public function test_a_fabricated_signature_is_refused(): void
    {
        $result = $this->verifyAs($this->admin, QrPayload::build('AST-0001', 'V1', random_bytes(64)));

        $this->assertSame('invalid_signature', $result['status']);
        $this->assertNull($result['asset']);
    }

    public function test_a_sticker_signed_by_a_key_this_install_does_not_hold_is_named(): void
    {
        // Signed with V2, then the keyring is replaced by V1 alone (a key that
        // was rotated out without keeping its public half).
        $this->useSigningKey('V2');
        $payload = $this->payloadFor('AST-0001');
        $this->useSigningKey('V1');

        $result = $this->verifyAs($this->admin, $payload);

        $this->assertSame('unknown_key', $result['status']);
        $this->assertSame('V2', $result['key_id']);

        $this->scanAs($this->admin, $payload)->assertStatus(422);
    }

    public function test_a_retired_key_kept_as_a_public_key_still_verifies_printed_stickers(): void
    {
        $key = $this->useSigningKey('V1');
        $payload = $this->payloadFor('AST-0001');

        // Rotation: the secret is dropped, the public half is kept.
        config([
            'qr.keys' => 'V1:' . QrSigner::ED25519_PUBLIC . ':' . $key['public'],
            'qr.active_key_id' => 'V1',
        ]);
        QrSigner::flush();
        AssetQr::clearCache();

        $this->assertSame('valid', $this->verifyAs($this->admin, $payload)['status']);

        // ...and with nothing left that can sign, a new sticker falls back to the
        // plain code rather than minting an unsigned payload.
        $this->assertNull(AssetQr::payloadFor('AST-0001'));
        $this->assertSame('AST-0001', AssetQr::textFor('AST-0001'));

        // A sticker naming a key that is not in the ring at all is still
        // reported as unknown, not as unavailable.
        $this->assertSame('unknown_key', $this->verifyAs($this->admin, str_replace('-V1-', '-V9-', $payload))['status']);
    }

    /* ── 3. unknown assets, damaged and foreign codes ───────────────────── */

    public function test_a_genuine_payload_for_an_unknown_code_reports_no_asset(): void
    {
        $payload = $this->signedPayload('AST-9999');

        $result = $this->verifyAs($this->admin, $payload);

        $this->assertSame('unknown_asset', $result['status']);
        $this->assertTrue($result['signed']);
        $this->assertNull($result['asset']);

        $this->scanAs($this->admin, $payload)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Asset not found')
            ->assertJsonPath('verification.status', 'unknown_asset');
    }

    public function test_damaged_and_foreign_codes_are_named_for_what_they_are(): void
    {
        $cases = [
            'NUT1-AST-0001-V1-XXXX' => 'malformed',      // truncated signature
            'NUT1-' . str_repeat('AST-0001-', 60) => 'malformed', // oversized
            'NUT3-AST-0001-V1-AAAA' => 'unsupported',    // a payload version we do not know
            'https://example.com/asset/AST-0001' => 'unsupported', // not a NU Trace sticker
        ];

        foreach ($cases as $scanned => $expected) {
            $this->assertSame($expected, $this->verifyAs($this->admin, $scanned)['status'], $scanned);
        }

        // A damaged sticker of ours is refused by the scanner; a foreign code is
        // simply not found, and is never accused of being forged.
        $this->scanAs($this->admin, 'NUT1-AST-0001-V1-XXXX')->assertStatus(422);
        $this->scanAs($this->admin, 'https://example.com/asset/AST-0001')
            ->assertStatus(404)
            ->assertJsonPath('verification.status', 'unsupported')
            ->assertJsonPath('message', 'This is not a NU Trace sticker.');
    }

    /* ── 4. unsigned legacy stickers ───────────────────────────────────── */

    public function test_a_plain_code_still_works_and_is_labelled_unsigned(): void
    {
        $result = $this->verifyAs($this->admin, 'ast-0001');

        $this->assertSame('legacy_unsigned', $result['status']);
        $this->assertFalse($result['signed']);
        $this->assertNull($result['key_id']);
        $this->assertSame('AST-0001', $result['code']);

        $this->scanAs($this->admin, 'AST-0001')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.asset_code', 'AST-0001')
            ->assertJsonPath('verification.status', 'legacy_unsigned');
    }

    public function test_the_request_pages_accept_both_kinds_of_sticker_for_their_own_assets(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/user/assets/check-code?code=' . urlencode('AST-0001'))
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('verification.status', 'legacy_unsigned');

        $this->actingAs($this->employee)
            ->getJson('/user/assets/check-code?code=' . urlencode($this->payloadFor('AST-0001')))
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('verification.status', 'valid');

        // Another department's sticker, even a genuine one, is not this
        // account's asset - and its signature is still reported.
        $this->actingAs($this->employee)
            ->getJson('/user/assets/check-code?code=' . urlencode($this->payloadFor('AST-2001')))
            ->assertOk()
            ->assertJsonPath('exists', false)
            ->assertJsonPath('verification.status', 'valid');

        // A sticker that failed its check is refused with the reason.
        $this->actingAs($this->employee)
            ->getJson('/user/assets/check-code?code=' . urlencode(QrPayload::build('AST-0001', 'V1', random_bytes(64))))
            ->assertOk()
            ->assertJsonPath('exists', false)
            ->assertJsonPath('verification.status', 'invalid_signature')
            ->assertJsonPath('message', 'The signature does not match this payload. The sticker has been altered or was not produced by this system.');
    }

    /* ── 5. an install with no keys ────────────────────────────────────── */

    public function test_without_a_keyring_a_sticker_is_unavailable_never_fake(): void
    {
        config(['qr.keys' => '', 'qr.active_key_id' => null]);
        QrSigner::flush();
        AssetQr::clearCache();

        $unsigned = 'NUT1-AST-0001-V1-' . QrPayload::encodeSignature(random_bytes(64));
        $result = $this->verifyAs($this->admin, $unsigned);

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame('Verification unavailable', $result['label']);
        $this->assertStringContainsString('neither confirmed nor rejected', $result['detail']);

        // The scan still resolves the asset by the code inside the payload: a
        // missing key is a server problem, not an accusation.
        $this->scanAs($this->admin, $unsigned)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.asset_code', 'AST-0001')
            ->assertJsonPath('verification.status', 'unavailable');

        // And a new sticker is simply the plain code.
        $this->assertSame('AST-0001', AssetQr::textFor('AST-0001'));
    }

    /* ── 6. departmental scope ─────────────────────────────────────────── */

    public function test_a_sticker_is_only_shown_to_the_department_that_holds_the_asset(): void
    {
        $own = $this->verifyAs($this->head, $this->payloadFor('AST-0001'));
        $this->assertSame('valid', $own['status']);
        $this->assertTrue($own['permitted']);
        $this->assertSame('AST-0001', $own['asset']['asset_code']);

        $outside = $this->verifyAs($this->head, $this->payloadFor('AST-2001'));
        $this->assertSame('valid', $outside['status']);
        $this->assertFalse($outside['permitted']);
        $this->assertNull($outside['asset']);

        $colleague = $this->verifyAs($this->employee, $this->payloadFor('AST-0002'));
        $this->assertSame('valid', $colleague['status']);
        $this->assertFalse($colleague['permitted']);

        $mine = $this->verifyAs($this->employee, $this->payloadFor('AST-0001'));
        $this->assertTrue($mine['permitted']);
    }

    public function test_the_department_head_check_code_endpoint_still_enforces_the_department(): void
    {
        $this->actingAs($this->head)
            ->getJson('/department-head/assets/check-code?code=' . urlencode($this->payloadFor('AST-0001')))
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('verification.status', 'valid');

        $this->actingAs($this->head)
            ->getJson('/department-head/assets/check-code?code=' . urlencode($this->payloadFor('AST-2001')))
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    /* ── 7. revocation ─────────────────────────────────────────────────── */

    public function test_a_revoked_sticker_is_refused_until_it_is_reissued(): void
    {
        $this->fakeQrService();
        $payload = $this->payloadFor('AST-0001');

        $this->actingAs($this->head)
            ->post('/admin/assets/' . $this->ownAsset . '/qr/revoke')
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->post('/admin/assets/' . $this->ownAsset . '/qr/revoke')
            ->assertRedirect();

        $this->assertNotNull(DB::table('assets')->where('id', $this->ownAsset)->value('qr_revoked_at'));

        $this->assertSame('asset_revoked', $this->verifyAs($this->admin, $payload)['status']);
        $this->scanAs($this->admin, $payload)
            ->assertStatus(422)
            ->assertJsonPath('verification.status', 'asset_revoked');

        $this->actingAs($this->admin)
            ->post('/admin/assets/' . $this->ownAsset . '/qr/reissue')
            ->assertRedirect();

        $this->assertNull(DB::table('assets')->where('id', $this->ownAsset)->value('qr_revoked_at'));
        $this->assertSame('valid', $this->verifyAs($this->admin, $payload)['status']);

        // The reissued sticker is a sealed QR payload, not a readable code.
        $stored = (string) DB::table('assets')->where('id', $this->ownAsset)->value('qr_payload');
        $this->assertStringStartsWith('NUT2-V1-', $stored);
        $this->assertStringNotContainsString('AST-', $stored);
    }

    /* ── 8. keys, printing, registration ───────────────────────────────── */

    public function test_the_private_key_never_reaches_a_response_or_a_page(): void
    {
        $this->fakeQrService();
        $payload = $this->payloadFor('AST-0001');

        $verification = $this->actingAs($this->admin)->getJson('/qr/verify?payload=' . urlencode($payload));
        $this->assertStringNotContainsString($this->secret, $verification->getContent());

        $this->actingAs($this->admin)->post('/admin/assets/' . $this->ownAsset . '/qr/reissue')->assertRedirect();

        $pages = [
            '/admin/assets/labels?codes=AST-0001',
            '/admin/assets/' . $this->ownAsset,
            '/admin/assets/department/1',
            '/admin/assets/registry',
        ];

        foreach ($pages as $page) {
            $html = $this->actingAs($this->admin)->get($page)->assertOk()->getContent();

            $this->assertStringNotContainsString($this->secret, $html, $page . ' must not contain the signing key');
        }
    }

    public function test_the_label_sheet_prints_the_signed_payload(): void
    {
        $payload = $this->payloadFor('AST-0001');

        $this->actingAs($this->admin)
            ->get('/admin/assets/labels?codes=AST-0001')
            ->assertOk()
            ->assertSee('data-qr-payload="' . $payload . '"', false)
            ->assertDontSee('unsigned code');
    }

    public function test_the_label_sheet_tells_the_office_which_stickers_are_still_unsigned(): void
    {
        config(['qr.keys' => '', 'qr.active_key_id' => null]);
        QrSigner::flush();
        AssetQr::clearCache();

        $this->actingAs($this->admin)
            ->get('/admin/assets/labels?codes=AST-0001')
            ->assertOk()
            ->assertSee('data-qr-payload="AST-0001"', false)
            ->assertSee('unsigned code');
    }

    public function test_registering_an_asset_stores_a_signed_sticker(): void
    {
        $this->fakeQrService();

        $this->actingAs($this->admin)->post('/admin/assets', [
            'name' => 'Signed laptop',
            'category' => 'computer',
            'condition' => 'good',
            'asset_code_mode' => 'custom',
            'custom_codes_text' => 'NU-9001',
            'quantity' => 1,
            'acquisition_date' => '2026-10-15',
            'assigned_to' => $this->admin->id,
        ])->assertRedirect('/admin/assets/registry');

        $row = DB::table('assets')->where('Asset_code', 'NU-9001')->first();

        $this->assertNotNull($row);
        $this->assertStringStartsWith('NUT2-V1-', (string) $row->qr_payload);
        $this->assertStringNotContainsString('NU-9001', (string) $row->qr_payload);
        $this->assertNotNull($row->qr_code_path);

        // The image that was stored encodes that same payload, at the error
        // correction level a long signed payload needs.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'data=NUT2-V1-')
            && str_contains($request->url(), 'ecc=M'));
    }

    public function test_the_payload_endpoint_is_for_the_asset_management_office_only(): void
    {
        $this->actingAs($this->head)
            ->getJson('/admin/assets/qr-payload?code=AST-0001')
            ->assertStatus(403);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/assets/qr-payload?codes=AST-0001,AST-0002')
            ->assertOk()
            ->assertJsonPath('signed', true);

        $payloads = $response->json('payloads');

        $this->assertSame($this->payloadFor('AST-0001'), $payloads['AST-0001']);
        $this->assertSame($this->payloadFor('AST-0002'), $payloads['AST-0002']);
    }

    public function test_a_guest_can_neither_verify_nor_revoke(): void
    {
        $this->getJson('/qr/verify?payload=' . urlencode($this->payloadFor('AST-0001')))->assertStatus(401);
        $this->post('/admin/assets/' . $this->ownAsset . '/qr/revoke')->assertRedirect('/');
    }

    public function test_the_ecdsa_fallback_signs_and_verifies_the_same_payload_shape(): void
    {
        $this->useSigningKey('E1', QrSigner::ECDSA_P256);

        $payload = $this->payloadFor('AST-0001');

        $this->assertStringStartsWith('NUT2-E1-', $payload);
        $this->assertMatchesRegularExpression('/^[A-Z0-9.\-\/_]+$/', $payload);
        $this->assertSame('valid', $this->verifyAs($this->admin, $payload)['status']);

        $this->assertSame('invalid_signature', $this->verifyAs($this->admin, $this->tamperSignature($payload))['status']);
    }
}
