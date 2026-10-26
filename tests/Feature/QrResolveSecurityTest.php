<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetQr;
use App\Support\QrMobileToken;
use App\Support\QrPayload;
use App\Support\QrSeal;
use App\Support\QrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The phone's resolver is a credentialed endpoint, not an open oracle.
 *
 * What this file locks down:
 *
 *   1. nothing is resolved without a token - including for a browser that
 *      carries a valid session cookie, which is what makes the route's CSRF
 *      exemption safe rather than a hole;
 *   2. the token is minted only from a real password, expires, and cannot be
 *      edited; a wrong password and an unknown address answer identically;
 *   3. the asset code - the one protected thing the endpoint used to hand out -
 *      is released only to an account the app's own permission rule already
 *      lets see that asset;
 *   4. a revoked, forged or unknown sticker never carries the code, and a
 *      legacy sticker keeps working;
 *   5. floods are limited per account (not per address), and a rejected request
 *      writes no scan event.
 */
class QrResolveSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private User $otherEmployee;

    private User $head;

    private User $otherDepartmentHead;

    private int $assetId;

    private int $otherAssetId;

    protected function setUp(): void
    {
        parent::setUp();

        // A per-test array store: no counter can leak from one test into the
        // next, and no `cache` table is needed.
        config(['qr.resolve.limiter_store' => 'array']);

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Other Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Office Admin', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Employee One', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Employee Two', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'Employee_number' => 'EMP-5', 'Full_Name' => 'Other Head', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = $this->account(1, 1, 'admin@nu-lipa.edu.ph', 'Admin');
        $this->head = $this->account(2, 1, 'head@nu-lipa.edu.ph', 'Department Head');
        $this->employee = $this->account(3, 1, 'employee@nu-lipa.edu.ph', 'Employee');
        $this->otherEmployee = $this->account(4, 1, 'other@nu-lipa.edu.ph', 'Employee');
        $this->otherDepartmentHead = $this->account(5, 2, 'head2@nu-lipa.edu.ph', 'Department Head');

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id,
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

        $this->otherAssetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->otherEmployee->id,
            'Asset_code' => 'AST-0002',
            'Asset_name' => 'Someone else\'s asset',
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
            'qr.resolve.per_minute' => 60,
            'qr.resolve.per_day' => 2000,
            'qr.resolve.login_per_minute' => 10,
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

    private function account(int $employeeId, int $departmentId, string $email, string $role): User
    {
        return User::create([
            'employee_numbers_id' => $employeeId,
            'department_id' => $departmentId,
            'email' => $email,
            'password' => Hash::make('secret-password'),
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function payload(string $code = 'AST-0001'): string
    {
        $payload = AssetQr::payloadFor($code);

        $this->assertIsString($payload);

        return (string) $payload;
    }

    private function tokenFor(User $user): string
    {
        return QrMobileToken::issue($user)['token'];
    }

    private function resolveAs(?User $user, string $scanned): \Illuminate\Testing\TestResponse
    {
        $headers = $user ? ['Authorization' => 'Bearer ' . $this->tokenFor($user)] : [];

        return $this->withHeaders($headers)->postJson('/qr/resolve', ['payload' => $scanned]);
    }

    /* ── authentication ──────────────────────────────────────────────── */

    public function test_the_resolver_rejects_an_anonymous_request(): void
    {
        $this->postJson('/qr/resolve', ['payload' => $this->payload()])
            ->assertStatus(401)
            ->assertJsonMissingPath('code');

        $this->assertSame(0, DB::table('qr_scan_events')->count());
    }

    public function test_a_browser_session_cannot_stand_in_for_the_token(): void
    {
        // The route is outside the CSRF group on purpose, so this is the test
        // that keeps that safe: a cookie is not a credential here.
        $this->actingAs($this->admin)
            ->postJson('/qr/resolve', ['payload' => $this->payload()])
            ->assertStatus(401);

        $this->assertSame(0, DB::table('qr_scan_events')->count());
    }

    public function test_the_phone_exchanges_its_credentials_for_a_token(): void
    {
        $this->postJson('/qr/session', [
            'email' => 'ADMIN@nu-lipa.edu.ph',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $this->admin->id)
            ->assertJsonPath('user.role', 'Admin')
            ->assertJsonStructure(['token', 'expires_at']);

        // Issuing a credential is not a scan: nothing is written to the ledger.
        $this->assertSame(0, DB::table('qr_scan_events')->count());
    }

    public function test_a_wrong_password_and_an_unknown_address_answer_identically(): void
    {
        $wrong = $this->postJson('/qr/session', [
            'email' => 'admin@nu-lipa.edu.ph',
            'password' => 'not-the-password',
        ])->assertStatus(422)->json('message');

        $unknown = $this->postJson('/qr/session', [
            'email' => 'nobody@nu-lipa.edu.ph',
            'password' => 'not-the-password',
        ])->assertStatus(422)->json('message');

        $this->assertSame($wrong, $unknown);
    }

    public function test_an_inactive_account_cannot_get_a_token(): void
    {
        $this->admin->update(['status' => 'Inactive']);

        $this->postJson('/qr/session', [
            'email' => 'admin@nu-lipa.edu.ph',
            'password' => 'secret-password',
        ])->assertStatus(422);
    }

    public function test_a_tampered_token_is_refused(): void
    {
        $token = $this->tokenFor($this->admin);
        $tampered = substr($token, 0, -2) . (str_ends_with($token, 'AA') ? 'BB' : 'AA');

        $this->withHeader('Authorization', 'Bearer ' . $tampered)
            ->postJson('/qr/resolve', ['payload' => $this->payload()])
            ->assertStatus(401);
    }

    public function test_an_expired_token_is_refused(): void
    {
        // Issued 31 days ago with a 30-day life: valid signature, past expiry.
        $token = QrMobileToken::issue($this->admin, now()->subDays(31))['token'];

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/qr/resolve', ['payload' => $this->payload()])
            ->assertStatus(401);
    }

    /* ── data exposure ───────────────────────────────────────────────── */

    public function test_the_identifier_is_released_only_to_an_account_allowed_to_see_the_asset(): void
    {
        $owner = $this->resolveAs($this->employee, $this->payload())->assertOk();
        $owner->assertJsonPath('status', 'valid')->assertJsonPath('code', 'AST-0001');

        $admin = $this->resolveAs($this->admin, $this->payload())->assertOk();
        $admin->assertJsonPath('code', 'AST-0001');

        // Same company, different custodian: the sticker is genuine, but the
        // identifier is not this account's to receive.
        $other = $this->resolveAs($this->otherEmployee, $this->payload())->assertOk();
        $other->assertJsonPath('status', 'valid');
        $other->assertJsonPath('code', null);
    }

    public function test_a_department_head_sees_only_their_own_department(): void
    {
        // AST-0001 is held by an employee in department 1.
        $this->resolveAs($this->head, $this->payload())
            ->assertOk()
            ->assertJsonPath('code', 'AST-0001');

        $this->resolveAs($this->otherDepartmentHead, $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'valid')
            ->assertJsonPath('code', null);
    }

    public function test_the_response_carries_no_asset_details(): void
    {
        $body = $this->resolveAs($this->admin, $this->payload())->assertOk()->json();

        $this->assertSame([
            'status', 'label', 'tone', 'detail', 'signed', 'sealed', 'key_id', 'code', 'duplicate',
        ], array_keys($body));

        $this->assertArrayNotHasKey('asset', $body);
        $this->assertStringNotContainsString('Demo asset', json_encode($body));
        $this->assertStringNotContainsString('Faculty Room', json_encode($body));
    }

    /* ── verification still authoritative ───────────────────────────── */

    public function test_a_revoked_sticker_returns_no_code(): void
    {
        DB::table('assets')->where('id', $this->assetId)->update(['qr_revoked_at' => now()]);

        $this->resolveAs($this->admin, $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'asset_revoked')
            ->assertJsonPath('code', null);
    }

    public function test_a_modified_sticker_is_refused_and_returns_no_code(): void
    {
        $payload = $this->payload();
        $tampered = substr($payload, 0, 25) . ($payload[25] === 'A' ? 'B' : 'A') . substr($payload, 26);

        $response = $this->resolveAs($this->admin, $tampered)->assertOk();

        $this->assertContains($response->json('status'), [
            \App\Support\QrVerification::INVALID_SIGNATURE,
            \App\Support\QrVerification::MALFORMED,
        ]);
        $this->assertNull($response->json('code'));
    }

    public function test_a_legacy_plain_code_still_resolves_for_its_owner(): void
    {
        $this->resolveAs($this->employee, 'AST-0001')
            ->assertOk()
            ->assertJsonPath('status', 'legacy_unsigned')
            ->assertJsonPath('code', 'AST-0001');
    }

    public function test_a_genuine_scan_is_recorded_once(): void
    {
        $this->resolveAs($this->employee, $this->payload())->assertOk();

        $this->assertSame(1, DB::table('qr_scan_events')->count());
    }

    public function test_the_same_sticker_from_a_second_account_is_still_flagged(): void
    {
        $this->resolveAs($this->employee, $this->payload())
            ->assertOk()
            ->assertJsonPath('duplicate.suspected', false);

        $this->resolveAs($this->admin, $this->payload())
            ->assertOk()
            ->assertJsonPath('duplicate.suspected', true);
    }

    /* ── rate limiting ───────────────────────────────────────────────── */

    public function test_a_flood_is_limited_and_told_when_to_retry(): void
    {
        config(['qr.resolve.per_minute' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->resolveAs($this->admin, $this->payload())->assertOk();
        }

        $blocked = $this->resolveAs($this->admin, $this->payload())
            ->assertStatus(429)
            ->assertJsonPath('retry_after', fn ($value) => is_int($value) && $value > 0);

        $this->assertNotNull($blocked->headers->get('Retry-After'));
    }

    public function test_one_account_hitting_the_limit_does_not_block_another(): void
    {
        config(['qr.resolve.per_minute' => 2]);

        $this->resolveAs($this->admin, $this->payload())->assertOk();
        $this->resolveAs($this->admin, $this->payload())->assertOk();
        $this->resolveAs($this->admin, $this->payload())->assertStatus(429);

        $this->resolveAs($this->employee, $this->payload())->assertOk();
    }

    public function test_a_limited_request_writes_no_scan_event(): void
    {
        config(['qr.resolve.per_minute' => 1]);

        $this->resolveAs($this->admin, $this->payload())->assertOk();
        $this->resolveAs($this->admin, $this->payload())->assertStatus(429);

        $this->assertSame(1, DB::table('qr_scan_events')->count());
    }

    public function test_the_real_file_limiter_store_works_on_this_deployment(): void
    {
        // The suite normally uses the array store so counters cannot leak. This
        // case exercises the store the deployment actually uses, which is the
        // one that has to exist on Railway (`storage/framework/cache`).
        config([
            'qr.resolve.limiter_store' => 'file',
            'qr.resolve.per_minute' => 1,
        ]);

        $store = \Illuminate\Support\Facades\Cache::store('file');
        $limiter = new \Illuminate\Cache\RateLimiter($store);
        $minuteKey = 'qr-resolve:minute:' . $this->admin->id;
        $dayKey = 'qr-resolve:day:' . $this->admin->id;

        // The file store outlives a single test (and a previous CI run), so
        // start from a known-empty window rather than inheriting a counter.
        $limiter->clear($minuteKey);
        $limiter->clear($dayKey);

        try {
            $this->resolveAs($this->admin, $this->payload())->assertOk();
            $this->resolveAs($this->admin, $this->payload())->assertStatus(429);
        } finally {
            $limiter->clear($minuteKey);
            $limiter->clear($dayKey);
        }
    }

    public function test_the_login_route_is_rate_limited(): void
    {
        config(['qr.resolve.login_per_minute' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/qr/session', [
                'email' => 'admin@nu-lipa.edu.ph',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/qr/session', [
            'email' => 'admin@nu-lipa.edu.ph',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
