<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TransferReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The Executive Director's transfer review.
 *
 * The Executive Director has no NU Trace account. They are emailed a
 * single-purpose link, prove they hold the mailbox it was sent to, decide on
 * exactly that transfer — approve all of it, approve part of it, or reject it
 * with a reason — and the link is spent.
 *
 * Every test here goes through the real doors: the employee's request form, the
 * office's approve endpoint, the real mailed link, the real public routes. The
 * link is read back out of the email the system actually sent, so "the token is
 * unpredictable and only its hash is stored" is verified against the message a
 * person would receive rather than against an assumption about it.
 */
class ExecutiveReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $admin2;
    private User $employee;
    private User $recipient;

    private int $assetA;
    private int $assetB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Accountancy', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Nena Santos', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Rico Bautista', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Lito Uy', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Dina Cruz', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin      = $this->account(3, 1, 'amo@nu-lipa.edu.ph', 'Admin');
        $this->admin2     = $this->account(4, 2, 'amo2@nu-lipa.edu.ph', 'Admin');
        $this->employee   = $this->account(1, 1, 'nena@nu-lipa.edu.ph', 'Employee');
        $this->recipient  = $this->account(2, 2, 'rico@nu-lipa.edu.ph', 'Employee');

        $this->assetA = $this->asset('AST-0001', 'Dell Laptop');
        $this->assetB = $this->asset('AST-0002', 'Epson Projector');

        // The address this deployment usually sends to, and a sender. The
        // address is only a convenience: every test that cares who was asked
        // types its own, the way the office does, because a change of Executive
        // Director must not need a deployment.
        config([
            'transfer_review.email' => 'executive.director@nu-lipa.edu.ph',
            'mail.from.address'     => 'nutrace@nu-lipa.edu.ph',
            'mail.from.name'        => 'NU Trace',
        ]);
    }

    private function account(int $employeeNumberId, int $departmentId, string $email, string $role, string $status = 'Active'): User
    {
        return User::create([
            'employee_numbers_id' => $employeeNumberId,
            'department_id'       => $departmentId,
            'email'               => $email,
            'password'            => Hash::make('secret-password'),
            'role'                => $role,
            'status'              => $status,
        ]);
    }

    private function asset(string $code, string $name, ?int $ownerId = null): int
    {
        return (int) DB::table('assets')->insertGetId([
            'user_id'          => $ownerId ?? $this->employee->id,
            'Asset_code'       => $code,
            'Asset_name'       => $name,
            'Category'         => 'Info and Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location'   => 'Registrar Office',
            'serial_Number'    => 'SN-' . $code,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /** Submit a transfer request through the employee's own form. */
    private function submitTransfer(array $assetIds, ?User $to = null, ?User $from = null): int
    {
        $from = $from ?? $this->employee;
        $to   = $to ?? $this->recipient;

        $this->actingAs($from)->post('/user/requests/store', [
            'request_type'      => 'Transfer',
            'asset_ids'         => $assetIds,
            'assign_to_user_id' => $to->id,
            'notes'             => 'These should move to the new custodian.',
        ])->assertRedirect();

        return (int) DB::table('requests')->max('id');
    }

    /**
     * The office's approval, forwarded to the Executive Director.
     *
     * The address is typed by the office, exactly as the Requests page sends it;
     * when a test does not name one, the send goes through with none at all so
     * the fallback is what gets exercised. The invitation itself is read out of
     * the email the system actually sent.
     */
    private function forwardForReview(int $requestId, ?string $to = null): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve", array_filter([
                'forward_for_review' => true,
                'recipient_email'    => $to,
            ], fn ($value) => $value !== null));

        $response->assertOk();

        // If the invitation was refused, or the mail refused it, say so here
        // rather than letting the next assertion report a missing email.
        $this->assertTrue(
            (bool) $response->json('forwarded'),
            'The invitation was not sent: ' . $response->json('message')
        );

        $this->assertTrue(
            (bool) $response->json('delivered'),
            'The invitation email was not delivered: ' . $response->json('message')
        );

        // The Executive Director opens the link from their own browser, which
        // holds no NU Trace session at all. Signing the office out here is what
        // makes every test below an honest test of that.
        auth()->logout();
    }

    /** Every plain-text body the mailer has been handed, oldest first. */
    private function sentEmails(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($sent) => (string) ($sent->getOriginalMessage()->getTextBody() ?? ''))
            ->values()
            ->all();
    }

    /** Every plain-text body, joined, so a message is never read out of order. */
    private function allEmails(): string
    {
        $bodies = $this->sentEmails();

        $this->assertNotEmpty($bodies, 'No email was sent.');

        return implode("\n", $bodies);
    }

    /**
     * The link the most recent invitation email carried.
     *
     * Searched across every message rather than the last one: by the time a
     * test asks, the most recent email may be the verification code, which
     * carries no link.
     */
    private function latestToken(): string
    {
        preg_match_all('#/executive-review/([A-Za-z0-9]{64})#', $this->allEmails(), $matches);

        $this->assertNotEmpty($matches[1], 'No review link was found in the emails that were sent.');

        return $matches[1][count($matches[1]) - 1];
    }

    /** The verification code the most recent code email carried. */
    private function latestCode(): string
    {
        preg_match_all('/code is: (\d{6})/', $this->allEmails(), $matches);

        $this->assertNotEmpty($matches[1], 'No verification code was found in the emails that were sent.');

        return $matches[1][count($matches[1]) - 1];
    }

    /** Ask for a code and use it, the way the recipient would. */
    private function verifyMailbox(string $token): string
    {
        $this->post("/executive-review/{$token}/code")
            ->assertRedirect("/executive-review/{$token}");

        $code = $this->latestCode();

        $this->post("/executive-review/{$token}/verify", ['code' => $code])
            ->assertRedirect("/executive-review/{$token}");

        return $code;
    }

    private function reviewRow(int $requestId): object
    {
        $row = DB::table('transfer_reviews')->where('request_id', $requestId)->orderByDesc('id')->first();

        $this->assertNotNull($row, "No invitation was recorded for request #{$requestId}.");

        return $row;
    }

    /* ═══════ 1. Only an approved transfer can be sent for review ═══════ */

    public function test_a_pending_transfer_cannot_be_sent_to_the_executive_director(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/review/resend")
            ->assertStatus(422);

        $this->assertSame(0, DB::table('transfer_reviews')->count());
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
    }

    public function test_a_request_that_is_not_a_transfer_cannot_be_sent(): void
    {
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->employee->id, 'asset_id' => $this->assetA, 'request_type' => 'Repair',
            'status' => 'Approved', 'Note' => 'Screen flickers.', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/review/resend")
            ->assertStatus(422);

        $this->assertSame(0, DB::table('transfer_reviews')->count());
    }

    public function test_approving_a_transfer_without_forwarding_it_still_asks_nobody(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();

        $this->assertSame(0, DB::table('transfer_reviews')->count());
        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    /* ═══════ 2. The link is unpredictable, scoped and expiring ═══════ */

    public function test_the_emailed_link_is_unpredictable_and_only_its_hash_is_stored(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        $token = $this->latestToken();        $row   = $this->reviewRow($requestId);

        // 64 random characters, and not the token itself: a dump of this table
        // cannot be turned back into a working link.
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $row->token_hash);
        $this->assertStringNotContainsString($token, (string) $row->token_hash);

        // Scoped to one request, and it expires.
        $this->assertSame($requestId, (int) $row->request_id);
        $this->assertSame(TransferReviews::STATUS_AWAITING, $row->status);
        $this->assertTrue(\Illuminate\Support\Carbon::parse($row->expires_at)->isFuture());
        $this->assertSame('executive.director@nu-lipa.edu.ph', $row->recipient_email);

        // The invitation email carries the link and nothing about the assets.
        $body = $this->allEmails();
        $this->assertStringContainsString($token, $body);
        $this->assertStringNotContainsString('AST-0001', $body);
        $this->assertStringNotContainsString('Dell Laptop', $body);

        // And it greets the office rather than a person by name: a name written
        // into the invitation would be the previous director's the day after the
        // appointment changes.
        $this->assertStringContainsString('Dear Executive Director,', $body);
    }

    public function test_a_token_that_was_never_issued_opens_nothing(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $this->get('/executive-review/' . str_repeat('a', 64))->assertStatus(404);

        // Not even by guessing a neighbour of a real token.
        $this->get('/executive-review/' . substr($this->latestToken(), 0, 63) . 'Z')->assertStatus(404);
    }

    public function test_an_expired_link_stops_working(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        DB::table('transfer_reviews')->where('request_id', $requestId)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->get("/executive-review/{$token}")
            ->assertOk()
            ->assertSee('expired', false)
            ->assertDontSee('AST-0001');

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame(TransferReviews::STATUS_VERIFIED, $this->reviewRow($requestId)->status);

        // And the expiry cannot be side-stepped by asking for a fresh code.
        $this->post("/executive-review/{$token}/code")->assertRedirect("/executive-review/{$token}");
        $this->assertNull($this->reviewRow($requestId)->code_hash);
    }

    /* ═══════ 3. The mailbox is proved before anything is shown ═══════ */

    public function test_transfer_details_stay_hidden_until_the_mailbox_is_verified(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();

        // The gate: it says a code was sent, and shows no asset, no custodian
        // and no transfer reference.
        $this->get("/executive-review/{$token}")
            ->assertOk()
            ->assertSee('Confirm')
            ->assertDontSee('AST-0001')
            ->assertDontSee('Dell Laptop')
            ->assertDontSee('Epson Projector')
            ->assertDontSee('Nena Santos')
            ->assertDontSee('TR-');

        // Even a fully formed decision body is refused while unverified.
        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame(TransferReviews::STATUS_AWAITING, $this->reviewRow($requestId)->status);
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        // Typing the official address into a field is not proof of identity:
        // there is no such field, and the code goes only where it was sent.
        $this->post("/executive-review/{$token}/verify", ['code' => '123456'])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame(TransferReviews::STATUS_AWAITING, $this->reviewRow($requestId)->status);

        // Proof: the code that reached the official mailbox.
        $this->verifyMailbox($token);

        $this->get("/executive-review/{$token}")
            ->assertOk()
            ->assertSee('AST-0001')
            ->assertSee('Dell Laptop')
            ->assertSee('Transfer Review');
    }

    public function test_the_code_is_emailed_only_to_the_mailbox_the_office_typed(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'new.director@nu-lipa.edu.ph');

        $this->post('/executive-review/' . $this->latestToken() . '/code')->assertRedirect();

        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $codeMail = $messages->last()->getOriginalMessage();

        $recipients = array_map(fn ($address) => $address->getAddress(), $codeMail->getTo());

        // The one address the office entered for this invitation — not the
        // deployment's usual address, and not anything a reader of the link
        // could type in.
        $this->assertSame(['new.director@nu-lipa.edu.ph'], $recipients);
    }

    public function test_a_code_cannot_be_guessed_within_the_allowance(): void
    {
        config(['transfer_review.code_attempts' => 3]);

        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->post("/executive-review/{$token}/code")->assertRedirect();

        for ($try = 0; $try < 3; $try++) {
            $this->post("/executive-review/{$token}/verify", ['code' => '000000']);
        }

        // The code is thrown away once the allowance is spent, so guessing
        // cannot continue against one invitation.
        $this->assertNull($this->reviewRow($requestId)->code_hash);
        $this->assertSame(TransferReviews::STATUS_AWAITING, $this->reviewRow($requestId)->status);
    }

    public function test_verification_does_not_create_a_nu_trace_session(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        // The verified browser can read its one review...
        $this->get("/executive-review/{$token}")->assertOk()->assertSee('AST-0001');

        // ...and is still nobody: a private area stays closed, and no account
        // was invented for the Executive Director.
        $this->assertNull(auth()->user());
        $this->get('/admin/requests')->assertRedirect('/');
        $this->assertSame(0, DB::table('users')->where('email', 'executive.director@nu-lipa.edu.ph')->count());
    }

    public function test_the_review_page_submits_the_assets_that_were_ticked(): void
    {
        // The markup has to carry the selection, not just draw it: an asset box
        // outside the form, or one with no name, is ticked by the reviewer and
        // submitted as nothing at all.
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $page = $this->get("/executive-review/{$token}")->assertOk();
        $html = $page->getContent();

        $this->assertStringContainsString('name="asset_ids[]"', $html);

        // Each box sits inside the decision form, between its open and close.
        $formOpens = strpos($html, 'id="decision-form"');
        $firstBox  = strpos($html, 'name="asset_ids[]"');
        $formCloses = strpos($html, '</form>');

        $this->assertNotFalse($formOpens);
        $this->assertNotFalse($firstBox);
        $this->assertNotFalse($formCloses);
        $this->assertTrue($formOpens < $firstBox && $firstBox < $formCloses, 'The asset boxes must be inside the decision form.');
    }

    public function test_the_office_can_forward_a_transfer_that_is_still_pending(): void
    {
        // The forward option is offered while the request is Pending — that is
        // the only moment the office chooses between completing the transfer
        // and sending it to the Executive Director.
        $requestId = $this->submitTransfer([$this->assetA]);

        $panel = TransferReviews::panel($requestId);

        $this->assertTrue($panel['can_forward']);
        $this->assertNull($panel['forward_problem']);
        $this->assertFalse($panel['can_send'], 'An invitation cannot be issued before the office approves the request.');
        $this->assertFalse($panel['eligible']);
    }

    public function test_the_review_panel_answers_the_same_shape_of_question_always(): void
    {
        // The Requests page reads one payload and shows one dialog. When there
        // is nothing to read — no such request — the answer still has to carry
        // the keys the dialog asks about, rather than leaving them undefined.
        $panel = TransferReviews::panel(0);

        foreach ([
            'tables_ready', 'recipient', 'default_recipient', 'recipient_email', 'forward_default',
            'outstanding', 'eligible', 'problem', 'can_send', 'can_forward', 'forward_problem',
            'can_revoke', 'history',
        ] as $key) {
            $this->assertArrayHasKey($key, $panel, "The panel payload is missing '{$key}'.");
        }

        $this->assertFalse($panel['can_forward']);
        $this->assertFalse($panel['can_send']);
        $this->assertFalse($panel['can_revoke']);
    }

    public function test_a_transfer_that_cannot_be_reviewed_is_never_approved_as_if_it_were(): void
    {
        // A transfer request with nobody to transfer to cannot be reviewed, and
        // asking for a review of it must not quietly execute the transfer
        // instead: nothing is recorded until the request can be handled.
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->employee->id, 'asset_id' => $this->assetA, 'request_type' => 'Transfer',
            'status' => 'Pending', 'Note' => 'No assignee recorded.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('request_items')->insert([
            'request_id' => $requestId, 'asset_id' => $this->assetA,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve", ['forward_for_review' => true])
            ->assertStatus(422);

        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame(0, DB::table('transfer_reviews')->count());
    }

    /* ═══════ 4. One link, one request ═══════ */

    public function test_a_link_shows_only_the_request_it_was_issued_for(): void
    {
        // Two transfers, each sent for review on its own link.
        $first  = $this->submitTransfer([$this->assetA], $this->recipient);
        $second = $this->submitTransfer([$this->assetB], $this->admin);

        $this->forwardForReview($first);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $page = $this->get("/executive-review/{$token}");

        $page->assertOk()
            ->assertSee('AST-0001')
            ->assertSee('Dell Laptop')
            ->assertDontSee('AST-0002')
            ->assertDontSee('Epson Projector')
            ->assertDontSee('TR-0002');   // the other transfer is not even named

        // Only the request that was forwarded has an invitation, so nothing
        // about the other one is reachable through this link at all.
        $this->assertSame(1, DB::table('transfer_reviews')->count());
        $this->assertSame(0, DB::table('transfer_reviews')->where('request_id', $second)->count());
        $this->assertSame(1, DB::table('transfer_reviews')->where('request_id', $first)->count());
    }

    /* ═══════ 5. Resending replaces the outstanding link ═══════ */

    public function test_resending_an_invitation_revokes_the_previous_link(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $first = $this->latestToken();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/review/resend")
            ->assertOk();

        $second = $this->latestToken();

        $this->assertNotSame($first, $second);

        // The old link is dead — not merely told it is old, but unable to reach
        // anything.
        $this->get("/executive-review/{$first}")->assertStatus(404);

        $this->verifyMailbox($second);
        $this->get("/executive-review/{$second}")->assertOk()->assertSee('AST-0001');

        // And the resend did not leave two live invitations behind.
        $this->assertSame(1, DB::table('transfer_reviews')->where('request_id', $requestId)
            ->whereIn('status', [TransferReviews::STATUS_AWAITING, TransferReviews::STATUS_VERIFIED])->count());
    }

    public function test_the_office_can_withdraw_an_invitation(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/review/revoke", ['reason' => 'The request was withdrawn by the department.'])
            ->assertOk();

        // The withdrawn link is not even recognisable as a link any more: its
        // token is gone, so the page cannot say "this was withdrawn" to somebody
        // who happened to guess the URL.
        $this->get("/executive-review/{$token}")->assertStatus(404);
        $this->assertSame(TransferReviews::STATUS_REVOKED, $this->reviewRow($requestId)->status);
        $this->assertNull($this->reviewRow($requestId)->token_hash);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])->assertStatus(404);

        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        // The withdrawal is on the record, with the reason the office gave.
        $this->assertSame(1, DB::table('audit_logs')
            ->where('request_id', $requestId)
            ->where('action_type', 'UPDATE')
            ->where('notes', 'like', '%invitation withdrawn%')
            ->count());
    }

    public function test_only_the_asset_management_office_can_send_or_withdraw_an_invitation(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $this->actingAs($this->employee)
            ->postJson("/admin/requests/{$requestId}/review/resend")
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->postJson("/admin/requests/{$requestId}/review/revoke")
            ->assertStatus(403);

        // A signed-out visitor cannot reach the office's endpoints either.
        auth()->logout();
        $this->postJson("/admin/requests/{$requestId}/review/resend")->assertStatus(401);

        $this->assertSame(TransferReviews::STATUS_AWAITING, $this->reviewRow($requestId)->status);
    }

    /* ═══════ 6. The decision itself ═══════ */

    public function test_the_executive_director_can_approve_the_whole_transfer(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $row = $this->reviewRow($requestId);

        $this->assertSame(TransferReviews::STATUS_DECIDED, $row->status);
        $this->assertSame(TransferReviews::DECISION_APPROVED, $row->decision);
        $this->assertSame(2, (int) $row->approved_count);
        $this->assertSame(0, (int) $row->declined_count);
        $this->assertNotNull($row->decided_at);

        // Both assets changed hands, through the same execution the office's own
        // approval uses.
        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));

        // One TRANSFER entry per asset that really moved, and no more: the
        // review itself never writes one.
        $this->assertSame(2, DB::table('audit_logs')->where('request_id', $requestId)
            ->where('action_type', 'TRANSFER')->whereNotNull('asset_id')->count());
        $this->assertSame(2, DB::table('transfer_review_items')->where('review_id', $row->id)->where('decision', TransferReviews::ITEM_APPROVED)->count());
    }

    public function test_a_partial_approval_moves_only_the_assets_that_were_approved(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", [
            'decision'  => 'approve_selected',
            'asset_ids' => [$this->assetB],
        ])->assertRedirect("/executive-review/{$token}");

        $row = $this->reviewRow($requestId);

        $this->assertSame(TransferReviews::DECISION_PARTIAL, $row->decision);
        $this->assertSame(1, (int) $row->approved_count);
        $this->assertSame(1, (int) $row->declined_count);

        // The approved one moved; the other did not, and stays with its holder.
        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        // Per-asset decisions are recorded, so the outcome is readable later.
        $items = DB::table('transfer_review_items')->where('review_id', $row->id)->pluck('decision', 'asset_id');
        $this->assertSame(TransferReviews::ITEM_DECLINED, $items[$this->assetA]);
        $this->assertSame(TransferReviews::ITEM_APPROVED, $items[$this->assetB]);

        // And the partial approval is named as such: nothing claims the whole
        // transfer went through.
        $this->assertSame('Transfer Partially Approved',
            DB::table('notifications')->where('user_id', $this->employee->id)->orderByDesc('id')->value('title'));
    }

    public function test_a_partial_approval_that_selects_nothing_is_refused(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_selected', 'asset_ids' => []])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame(TransferReviews::STATUS_VERIFIED, $this->reviewRow($requestId)->status);
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    public function test_rejection_needs_a_reason_and_changes_no_ownership(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        // Without a reason nothing is recorded at all.
        $this->post("/executive-review/{$token}/decide", ['decision' => 'reject', 'rejection_reason' => '  '])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame(TransferReviews::STATUS_VERIFIED, $this->reviewRow($requestId)->status);
        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));

        // With one, the request is closed through the existing status logic.
        $this->post("/executive-review/{$token}/decide", [
            'decision'         => 'reject',
            'rejection_reason' => 'The receiving department has no budget line for this asset.',
        ])->assertRedirect("/executive-review/{$token}");

        $request = DB::table('requests')->where('id', $requestId)->first();
        $row     = $this->reviewRow($requestId);

        $this->assertSame('Rejected', $request->status, 'The decision was refused with: ' . session('executive_review_error'));
        $this->assertSame('The receiving department has no budget line for this asset.', $request->admin_remarks);
        $this->assertSame(TransferReviews::DECISION_REJECTED, $row->decision);
        $this->assertSame('The receiving department has no budget line for this asset.', $row->rejection_reason);

        // No asset changed hands, and no TRANSFER entry claims one did.
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));
        $this->assertSame(0, DB::table('audit_logs')->where('request_id', $requestId)->where('action_type', 'TRANSFER')->count());
    }

    public function test_a_decided_link_cannot_decide_again(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_selected', 'asset_ids' => [$this->assetA]])
            ->assertRedirect("/executive-review/{$token}");

        // A replayed POST, with a different decision, is refused: the assets it
        // would have moved are not touched a second time.
        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));
        $this->assertSame(1, (int) $this->reviewRow($requestId)->approved_count);

        // The closing page repeats the decision instead of offering a form.
        $this->get("/executive-review/{$token}")
            ->assertOk()
            ->assertSee('This Review Is Complete')
            ->assertDontSee('Submit decision');
    }

    public function test_a_stale_decision_is_refused_when_the_assets_have_since_moved(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        // Something else moved one of the assets while the link was open.
        DB::table('assets')->where('id', $this->assetB)->update(['user_id' => $this->admin->id]);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame(TransferReviews::STATUS_VERIFIED, $this->reviewRow($requestId)->status);
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame($this->admin->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));

        // The office can issue a fresh invitation, and it says so.
        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/review/resend")
            ->assertOk();

        $this->assertNotSame($token, $this->latestToken());
    }

    /* ═══════ 7. Approval is not completion ═══════ */

    public function test_forwarding_records_an_approval_and_moves_nothing(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve", ['forward_for_review' => true])
            ->assertOk();

        // The office's approval is recorded...
        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertTrue((bool) $response->json('forwarded'));

        // ...and nothing else is. No asset changed hands, no transfer evidence
        // was written, and no completion is claimed anywhere.
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));
        $this->assertSame(0, DB::table('audit_logs')->where('request_id', $requestId)->where('action_type', 'TRANSFER')->count());
        $this->assertStringNotContainsString('complete', strtolower((string) $response->json('message')));

        $requesterNotice = DB::table('notifications')->where('user_id', $this->employee->id)->orderByDesc('id')->first();
        $this->assertSame('Transfer Approved — Awaiting the Executive Director', $requesterNotice->title);
        $this->assertStringContainsString('stay with their current custodian', $requesterNotice->message);

        // Sending the invitation is not a decision either.
        $this->assertSame(TransferReviews::STATUS_AWAITING, $this->reviewRow($requestId)->status);
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    public function test_opening_the_link_is_not_an_approval(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();

        $this->get("/executive-review/{$token}")->assertOk();
        $this->verifyMailbox($token);
        $this->get("/executive-review/{$token}")->assertOk();

        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame(TransferReviews::STATUS_VERIFIED, $this->reviewRow($requestId)->status);
        $this->assertSame(0, DB::table('transfer_review_items')->where('review_id', $this->reviewRow($requestId)->id)->count());
    }

    public function test_a_normal_transfer_approval_still_executes_immediately(): void
    {
        // The existing behaviour, untouched: without the forward option the
        // office's approval transfers the assets the way it always did.
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();

        $this->assertSame('Approved', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));
        $this->assertSame(2, DB::table('audit_logs')->where('request_id', $requestId)->where('action_type', 'TRANSFER')->count());
        $this->assertSame(0, DB::table('transfer_reviews')->count());
    }

    public function test_a_request_with_no_address_to_send_to_is_refused_before_anything_is_recorded(): void
    {
        config(['transfer_review.email' => '']);

        $requestId = $this->submitTransfer([$this->assetA]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve", [
                'forward_for_review' => true,
                'recipient_email'    => '   ',
            ])
            ->assertStatus(422);

        // Refused before anything was recorded, so the request is still the
        // office's to decide either way — and no invitation was left behind with
        // nowhere to go.
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame(0, DB::table('transfer_reviews')->count());
    }

    /* ═══════ 9. The office chooses who is asked ═══════ */

    public function test_the_office_types_the_address_the_invitation_goes_to(): void
    {
        config(['transfer_review.email' => '']);   // nothing configured at all

        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'dr.cruz@nu-lipa.edu.ph');

        $row = $this->reviewRow($requestId);

        $this->assertSame('dr.cruz@nu-lipa.edu.ph', $row->recipient_email);

        // The link was actually mailed to that mailbox. The body carries the link
        // rather than the address — an address in the body would be decoration,
        // and the envelope is what decides who receives it.
        $invitation = Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->first(fn ($message) => str_contains((string) $message->getTextBody(), '/executive-review/'));

        $this->assertNotNull($invitation, 'No invitation email was sent.');
        $this->assertSame(
            ['dr.cruz@nu-lipa.edu.ph'],
            array_map(fn ($address) => $address->getAddress(), $invitation->getTo())
        );
        $this->assertStringContainsString($this->latestToken(), (string) $invitation->getTextBody());
    }

    public function test_a_new_executive_director_can_be_asked_without_a_deployment(): void
    {
        // The director changes and the link that went to the old mailbox has to
        // stop working: a resend to the new address is the whole answer, with no
        // environment variable and no developer involved.
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'dr.villamor@nu-lipa.edu.ph');

        $oldLink = $this->latestToken();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/review/resend", [
                'recipient_email' => 'dr.cruz@nu-lipa.edu.ph',
            ])
            ->assertOk();

        $newLink = $this->latestToken();

        $this->assertNotSame($oldLink, $newLink);
        $this->assertSame('dr.cruz@nu-lipa.edu.ph', $this->reviewRow($requestId)->recipient_email);

        // The old link is dead, and the code goes to the new mailbox only.
        $this->get("/executive-review/{$oldLink}")->assertStatus(404);

        $this->post("/executive-review/{$newLink}/code")->assertRedirect();

        $codeMail   = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $recipients = array_map(fn ($address) => $address->getAddress(), $codeMail->getTo());

        $this->assertSame(['dr.cruz@nu-lipa.edu.ph'], $recipients);

        // The code works on the new link, so the new director can really read
        // the transfer — the flow was not merely pointed somewhere new.
        $this->post("/executive-review/{$newLink}/verify", ['code' => $this->latestCode()])
            ->assertRedirect("/executive-review/{$newLink}");

        $this->get("/executive-review/{$newLink}")->assertOk()->assertSee('AST-0001');
    }

    public function test_a_resend_without_an_address_goes_back_to_the_last_one_used(): void
    {
        // The office does not have to re-type the address on every send: what it
        // typed last time is the suggestion, and it is only a suggestion.
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'dr.cruz@nu-lipa.edu.ph');

        $panel = TransferReviews::panel($requestId);

        $this->assertSame('dr.cruz@nu-lipa.edu.ph', $panel['recipient_email']);
        $this->assertSame('executive.director@nu-lipa.edu.ph', $panel['default_recipient']);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/review/resend")
            ->assertOk();

        $this->assertSame('dr.cruz@nu-lipa.edu.ph', $this->reviewRow($requestId)->recipient_email);
    }

    public function test_an_address_that_is_not_an_address_is_refused(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);

        foreach (['not-an-address', 'dr.cruz@@nu-lipa.edu.ph', 'dr.cruz@'] as $nonsense) {
            $this->actingAs($this->admin)
                ->postJson("/admin/requests/{$requestId}/approve", [
                    'forward_for_review' => true,
                    'recipient_email'    => $nonsense,
                ])
                ->assertStatus(422);
        }

        // Nothing was approved on the strength of a typo, and no invitation was
        // created for an address that cannot receive it.
        $this->assertSame('Pending', DB::table('requests')->where('id', $requestId)->value('status'));
        $this->assertSame(0, DB::table('transfer_reviews')->count());
    }

    /* ═══════ 8. Notifications and the audit trail ═══════ */

    public function test_the_decision_notifies_the_requester_and_the_office(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'reject', 'rejection_reason' => 'Not this year.'])
            ->assertRedirect("/executive-review/{$token}");

        $requester = DB::table('notifications')->where('user_id', $this->employee->id)->orderByDesc('id')->first();
        $this->assertSame('Transfer Rejected', $requester->title);
        $this->assertStringContainsString('TR-', $requester->message);
        $this->assertStringContainsString('Not this year.', $requester->message);
        $this->assertStringContainsString('stay with their current custodian', $requester->message);

        // The whole office is told, not just the Admin who emailed the link: the
        // decision arrives with no NU Trace account behind it, so there is
        // nobody at the office who already knows.
        foreach ([$this->admin, $this->admin2] as $officeAccount) {
            $office = DB::table('notifications')->where('user_id', $officeAccount->id)->orderByDesc('id')->first();

            $this->assertNotNull($office);
            $this->assertSame('Transfer Rejected by the Executive Director', $office->title);
            $this->assertStringContainsString('TR-', $office->message);
        }
    }

    public function test_the_audit_trail_records_the_review_without_the_token_or_the_code(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $token = $this->latestToken();
        $code  = $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $audit = DB::table('audit_logs')->where('request_id', $requestId)->orderBy('id')->get();

        // Invitation, verification and decision are all on the record.
        $text = $audit->pluck('notes')->implode(' | ') . ' ' . $audit->pluck('action_description')->implode(' | ');
        $this->assertStringContainsString('invitation', strtolower($text));
        $this->assertStringContainsString('verified', strtolower($text));
        $this->assertStringContainsString('Executive Director', $text);

        // The link and the code are secrets: they never reach the log.
        foreach ($audit as $entry) {
            $this->assertStringNotContainsString($token, (string) $entry->notes);
            $this->assertStringNotContainsString($token, (string) $entry->action_description);
            $this->assertStringNotContainsString($code, (string) $entry->notes);
            $this->assertStringNotContainsString($code, (string) $entry->action_description);
        }

        // Every entry is one of the action types the audit log accepts.
        foreach ($audit as $entry) {
            $this->assertContains($entry->action_type, ['CREATE', 'UPDATE', 'REPAIR', 'REPLACEMENT', 'DISPOSAL', 'TRANSFER', 'LOGIN', 'APPROVAL']);
        }

        // The token is nowhere in the database at all — only its hash.
        $this->assertSame(0, DB::table('transfer_reviews')->where('token_hash', $token)->count());
        $this->assertSame(0, DB::table('transfer_reviews')->where('code_hash', $code)->count());
    }

    public function test_the_admin_request_page_offers_the_review_to_the_office_only(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId);

        $this->actingAs($this->admin)
            ->get('/admin/requests')
            ->assertOk()
            ->assertSee('Executive Director Review')
            ->assertSee('executive.director@nu-lipa.edu.ph');

        // A signed-out visitor is still sent to the landing page: the review
        // feature added no public administrative surface.
        auth()->logout();
        $this->get('/admin/requests')->assertRedirect('/');
    }
}
