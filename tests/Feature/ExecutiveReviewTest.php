<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TransferReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

        Storage::fake('public');

        $this->actingAs($from)->post('/user/requests/store', [
            'request_type'      => 'Transfer',
            'asset_ids'         => $assetIds,
            'assign_to_user_id' => $to->id,
            'notes'             => 'These should move to the new custodian.',
            'attachment'        => $this->requestPhoto(),
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

    /** The verification code the most recent email carried. */
    private function latestCode(): string
    {
        preg_match_all('/verification code is: (\d{6})/', $this->allEmails(), $matches);

        $this->assertNotEmpty($matches[1], 'No verification code was found in the emails that were sent.');

        return $matches[1][count($matches[1]) - 1];
    }

    /**
     * The code the invitation email for this link carried.
     *
     * Read out of the message a person would read, not assumed: the invitation
     * is the email that carries it, and a resend sends another one, so the newest
     * message holding this link wins.
     */
    private function invitationCode(string $token): string
    {
        $code = null;

        foreach ($this->sentEmails() as $body) {
            if (str_contains($body, $token) && preg_match('/verification code is: (\d{6})/', $body, $matches)) {
                $code = $matches[1];
            }
        }

        $this->assertNotNull($code, 'The invitation email carried no verification code.');

        return $code;
    }

    /** The To: addresses of the newest email that was sent. */
    private function lastEmailRecipients(): array
    {
        $message = Mail::mailer()->getSymfonyTransport()->messages()->last();

        $this->assertNotNull($message, 'No email was sent.');

        return array_map(fn ($address) => $address->getAddress(), $message->getOriginalMessage()->getTo());
    }

    /**
     * Verify the mailbox the way the recipient does: with the code the
     * invitation email carried, in one step, without asking for another one.
     */
    private function verifyMailbox(string $token): string
    {
        $code = $this->invitationCode($token);

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

    public function test_every_code_goes_only_to_the_mailbox_the_office_typed(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'new.director@nu-lipa.edu.ph');

        $token = $this->latestToken();

        // The invitation email carries the code, and it went to the one address
        // the office entered — not the deployment's usual address, and not
        // anything a reader of the link could type in.
        $this->assertStringContainsString('verification code is:', $this->allEmails());
        $this->assertSame(['new.director@nu-lipa.edu.ph'], $this->lastEmailRecipients());

        // A replacement code goes to the same place and nowhere else. The
        // cooldown is moved on rather than waited out, so the send really happens.
        DB::table('transfer_reviews')->where('request_id', $requestId)->update(['code_sent_at' => now()->subHour()]);

        $this->post("/executive-review/{$token}/code")->assertRedirect("/executive-review/{$token}");

        $this->assertNotSame($this->invitationCode($token), $this->latestCode());
        $this->assertSame(['new.director@nu-lipa.edu.ph'], $this->lastEmailRecipients());
    }

    /**
     * The invitation email is the whole flow: the link and the code the link
     * needs, in one message. Before this the recipient had to open the page and
     * ask for a code before they could do anything with what they had been sent.
     */
    public function test_the_invitation_email_carries_the_code_that_opens_the_review(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        $token = $this->latestToken();

        // One message, and no second request needed to act on it.
        $this->assertCount(1, $this->sentEmails(), 'The invitation should not need a second email to be usable.');

        $code = $this->invitationCode($token);

        // Written down hashed, never in a usable form — the same treatment a
        // code asked for from the page gets.
        $row = $this->reviewRow($requestId);

        $this->assertNotSame($code, (string) $row->code_hash);
        $this->assertTrue(Hash::check($code, (string) $row->code_hash));
        $this->assertNotNull($row->code_expires_at);

        $this->post("/executive-review/{$token}/verify", ['code' => $code])
            ->assertRedirect("/executive-review/{$token}");

        $this->get("/executive-review/{$token}")->assertOk()->assertSee('AST-0001');

        // And using it spends it: nothing usable is left behind.
        $this->assertNull($this->reviewRow($requestId)->code_hash);
    }

    /**
     * The code in the invitation can be lost or expired, so the page can send a
     * replacement — and that replacement really is a new code, not the same one
     * sent twice.
     */
    public function test_a_replacement_code_arrives_by_email_and_works(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        $token = $this->latestToken();
        $first = $this->invitationCode($token);

        // The invitation's code has expired, and the cooldown left by its own
        // send is behind us, so the page really sends a new one.
        DB::table('transfer_reviews')->where('request_id', $requestId)->update([
            'code_expires_at' => now()->subMinute(),
            'code_sent_at'    => now()->subHour(),
        ]);

        $this->post("/executive-review/{$token}/code")->assertRedirect("/executive-review/{$token}");

        $this->assertCount(2, $this->sentEmails());

        $replacement = $this->latestCode();

        $this->assertNotSame($first, $replacement);

        // The code the invitation carried is not a way in any more...
        $this->post("/executive-review/{$token}/verify", ['code' => $first])->assertRedirect("/executive-review/{$token}");
        $this->assertSame(TransferReviews::STATUS_AWAITING, $this->reviewRow($requestId)->status);

        // ...and the replacement is.
        $this->post("/executive-review/{$token}/verify", ['code' => $replacement])->assertRedirect("/executive-review/{$token}");
        $this->get("/executive-review/{$token}")->assertOk()->assertSee('AST-0001');
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

        // The old link is dead, and everything that opens the new one — the
        // invitation email that carries its code included — goes to the new
        // mailbox only.
        $this->get("/executive-review/{$oldLink}")->assertStatus(404);
        $this->assertSame(['dr.cruz@nu-lipa.edu.ph'], $this->lastEmailRecipients());

        // The code works on the new link, so the new director can really read
        // the transfer — the flow was not merely pointed somewhere new.
        $this->post("/executive-review/{$newLink}/verify", ['code' => $this->invitationCode($newLink)])
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

    /* ═══════ The handover: who is accountable, and what is on hold ═══════ */

    /**
     * The whole point of a transfer is the handover, and a handover that is not
     * written down is not one. `assets.user_id` says who is supposed to have the
     * asset; `asset_accountability` is what the official disposal form, the
     * asset's lifecycle history and the "who held this, when" question all read.
     */
    public function test_the_receiving_employee_becomes_accountable_for_the_assets(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        // While the review is open nothing has changed hands: the assets are
        // still the sending employee's, to account for and to hand over.
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])->assertRedirect();

        foreach ([$this->assetA, $this->assetB] as $assetId) {
            $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $assetId)->value('user_id'));

            $rows = DB::table('asset_accountability')->where('asset_id', $assetId)->orderBy('id')->get();

            $this->assertCount(2, $rows, 'The handover never reached the accountability history.');

            // The employee who gave it away is closed out…
            $this->assertSame($this->employee->id, (int) $rows[0]->user_id);
            $this->assertSame(0, (int) $rows[0]->Is_Current);

            // …and the receiving employee is the one accountable for it now.
            $this->assertSame($this->recipient->id, (int) $rows[1]->user_id);
            $this->assertSame(1, (int) $rows[1]->Is_Current);
            $this->assertSame($requestId, (int) $rows[1]->request_id);
            $this->assertSame('These should move to the new custodian.', $rows[1]->transfer_reason);
        }

        // And the receiving employee is told, because a handover nobody hears
        // about is how "who is responsible for this?" ends without an answer.
        $this->assertSame(1, DB::table('notifications')
            ->where('user_id', $this->recipient->id)
            ->where('title', 'Assets Transferred To You')
            ->count());
    }

    public function test_an_ordinary_approval_writes_the_same_handover(): void
    {
        // The office releases a transfer itself, with no review involved. The
        // assets change hands through the same code, so the paperwork must come
        // out identical — this is the path that used to move `assets.user_id`
        // and stop there.
        $requestId = $this->submitTransfer([$this->assetA]);

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        $current = DB::table('asset_accountability')
            ->where('asset_id', $this->assetA)
            ->where('Is_Current', 1)
            ->get();

        $this->assertCount(1, $current, 'The asset has no, or more than one, current accountable holder.');
        $this->assertSame($this->recipient->id, (int) $current->first()->user_id);

        $this->assertSame(0, DB::table('asset_accountability')
            ->where('asset_id', $this->assetA)
            ->where('user_id', $this->employee->id)
            ->where('Is_Current', 1)
            ->count());
    }

    public function test_the_transfer_page_cannot_move_assets_the_review_is_holding(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        // Settling the same relocation by hand from the Transfer page is another
        // door to the same assets. It must refuse while the decision is open,
        // or the review would be answering a question that had already moved on.
        $response = $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id' => $this->employee->id,
            'to_user_id'   => $this->admin2->id,
            'asset_ids'    => [$this->assetA],
            'reason'       => 'Employee Relocation',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transfer');

        $this->assertStringContainsString('on hold', (string) session('errors')->first('transfer'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        // Nothing was recorded: no second transfer, and the review still stands.
        $this->assertSame(1, DB::table('requests')->where('request_type', 'Transfer')->count());
        $this->assertSame(TransferReviews::STATUS_AWAITING, $this->reviewRow($requestId)->status);
    }

    public function test_another_approval_cannot_take_the_assets_the_review_is_holding(): void
    {
        $first = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($first, 'executive.director@nu-lipa.edu.ph');

        // A second transfer request for the same asset, to somebody else.
        $second = $this->submitTransfer([$this->assetA], $this->admin2, $this->employee);

        $refused = $this->actingAs($this->admin)->postJson("/admin/requests/{$second}/approve");

        $refused->assertStatus(422);
        $this->assertStringContainsString('on hold', (string) $refused->json('message'));

        // Forwarding the second request is refused too: one decision per asset,
        // and the first review has to finish.
        $forwarded = $this->actingAs($this->admin)->postJson("/admin/requests/{$second}/approve", [
            'forward_for_review' => true,
            'recipient_email'    => 'executive.director@nu-lipa.edu.ph',
        ]);

        $forwarded->assertStatus(422);
        $this->assertStringContainsString('on hold', (string) $forwarded->json('message'));

        // Refused before anything was written, on both attempts.
        $this->assertSame('Pending', DB::table('requests')->where('id', $second)->value('status'));
        $this->assertSame(1, DB::table('transfer_reviews')->count());
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    public function test_the_hold_ends_when_the_decision_is_recorded(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", [
            'decision'         => 'reject',
            'rejection_reason' => 'Not needed this year.',
        ])->assertRedirect();

        // A decided review holds nothing: the assets are the employee's own
        // again, unreviewed but nobody else's to keep.
        $this->assertSame([], TransferReviews::assetsOnHold([$this->assetA]));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        // And the office can settle it by hand now.
        $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id' => $this->employee->id,
            'to_user_id'   => $this->recipient->id,
            'asset_ids'    => [$this->assetA],
            'reason'       => 'Employee Relocation',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    public function test_an_expired_invitation_holds_nothing(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        DB::table('transfer_reviews')->where('request_id', $requestId)->update(['expires_at' => now()->subHour()]);

        // A link that can no longer take a decision cannot keep an asset out of
        // everybody else's hands either.
        $this->assertSame([], TransferReviews::assetsOnHold([$this->assetA]));
    }

    /* ═══════ Whose asset is this, and who decides ═══════ */

    /**
     * A Department Head requests on behalf of their department: the employee who
     * typed the request is not the employee holding the assets, and never was.
     * Read against the requester alone every one of those requests is "out of
     * date" from the moment it is submitted, and the transfer can never be
     * decided at all — which is what the office met on the live system.
     */
    public function test_a_department_head_request_can_be_decided_though_the_head_never_held_the_asset(): void
    {
        DB::table('employee_numbers')->insert([
            'id'              => 9,
            'Employee_number' => 'EMP-9',
            'Full_Name'       => 'Ada Reyes',
            'Department_id'   => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $head = $this->account(9, 1, 'head@nu-lipa.edu.ph', 'Department Head');

        Storage::fake('public');

        // The asset is held by another employee of the head's department.
        $this->actingAs($head)->post('/user/requests/store', [
            'request_type'      => 'Transfer',
            'asset_ids'         => [$this->assetA],
            'assign_to_user_id' => $this->recipient->id,
            'notes'             => 'The projector should move to the new custodian.',
            'attachment'        => $this->requestPhoto(),
        ])->assertRedirect();

        $requestId = (int) DB::table('requests')->max('id');

        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        // What the invitation describes is where the asset really was when the
        // office asked — the department's employee, not the head who asked.
        $recorded = TransferReviews::handedOverBy($this->reviewRow($requestId));
        $this->assertSame($this->employee->id, $recorded[$this->assetA] ?? null);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $this->assertSame(
            TransferReviews::STATUS_DECIDED,
            $this->reviewRow($requestId)->status,
            'The decision was refused with: ' . session('executive_review_error')
        );

        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        // Written down like any other handover, and the head is told because the
        // head is who asked.
        $this->assertSame($this->recipient->id, (int) DB::table('asset_accountability')
            ->where('asset_id', $this->assetA)->where('Is_Current', 1)->value('user_id'));

        $this->assertSame('Transfer Approved',
            DB::table('notifications')->where('user_id', $head->id)->orderByDesc('id')->value('title'));
    }

    /**
     * The office is told which assets will not move, at the moment it asks: an
     * invitation it sends for an asset the receiving employee already holds
     * changes nothing for that asset, and the answer to the approval says so.
     */
    public function test_the_office_is_told_which_assets_will_not_change_hands(): void
    {
        $requestId = $this->submitTransfer([$this->assetA, $this->assetB]);

        // One of the two reaches the receiving employee before the office acts.
        DB::table('assets')->where('id', $this->assetB)->update(['user_id' => $this->recipient->id]);

        $response = $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve", [
            'forward_for_review' => true,
            'recipient_email'    => 'executive.director@nu-lipa.edu.ph',
        ])->assertOk();

        $this->assertTrue((bool) $response->json('forwarded'));

        $message = (string) $response->json('message');

        $this->assertStringContainsString('AST-0002', $message);
        $this->assertStringContainsString('already held by Rico Bautista', $message);
        $this->assertStringNotContainsString('AST-0001', $message, 'The asset that will really move is not part of the note.');

        // The invitation itself describes where the assets were when it went out.
        $recorded = TransferReviews::handedOverBy($this->reviewRow($requestId));
        $this->assertSame($this->employee->id, $recorded[$this->assetA] ?? null);
        $this->assertSame($this->recipient->id, $recorded[$this->assetB] ?? null);
    }

    /**
     * An asset the receiving employee already holds is not a stale review: there
     * is simply nothing left to move for it. The office can still ask for the
     * request to be closed, and the decision says plainly that nothing had to
     * move — instead of refusing with "out of date" and leaving the request
     * Approved for ever with a link nobody can spend.
     */
    public function test_an_asset_already_held_by_the_recipient_is_decided_with_nothing_to_move(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        // The asset reaches the receiving employee through some other door while
        // the invitation is open.
        DB::table('assets')->where('id', $this->assetA)->update(['user_id' => $this->recipient->id]);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        // The page says it before anything is pressed, so nobody has to learn it
        // from a refused decision.
        $this->get("/executive-review/{$token}")
            ->assertOk()
            ->assertSee('Already with the receiving employee')
            ->assertSee('already held by Rico Bautista');

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $row = $this->reviewRow($requestId);

        $this->assertSame(
            TransferReviews::STATUS_DECIDED,
            $row->status,
            'The decision was refused with: ' . session('executive_review_error')
        );
        $this->assertSame(TransferReviews::DECISION_APPROVED, $row->decision);
        $this->assertNull($row->executed_at, 'Nothing changed hands, so nothing was executed.');

        // The asset is where the transfer wanted it, and no second transfer is
        // claimed on its history.
        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame(0, DB::table('audit_logs')->where('request_id', $requestId)->where('action_type', 'TRANSFER')->count());

        // The person who decided is told what happened, not what was asked.
        $this->assertStringContainsString('already held by', (string) session('executive_review_notice'));
    }

    /**
     * A rejection changes nothing, so it stays recordable however the assets
     * have moved on since. Refusing it leaves the office with a request that can
     * never be closed and a link that can never be spent.
     */
    public function test_a_transfer_whose_assets_have_moved_on_can_still_be_rejected(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        DB::table('assets')->where('id', $this->assetA)->update(['user_id' => $this->admin->id]);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", [
            'decision'         => 'reject',
            'rejection_reason' => 'The asset is no longer with the employee who offered to hand it over.',
        ])->assertRedirect("/executive-review/{$token}");

        $this->assertSame(
            TransferReviews::STATUS_DECIDED,
            $this->reviewRow($requestId)->status,
            'The rejection was refused with: ' . session('executive_review_error')
        );
        $this->assertSame('Rejected', DB::table('requests')->where('id', $requestId)->value('status'));

        // Nobody was moved: whoever holds it now keeps it.
        $this->assertSame($this->admin->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    /**
     * The one case an approval must still refuse: an asset that has passed into
     * a *third* employee's hands — neither the employee who held it when the
     * office asked nor the one it was going to. Approving would take custody
     * from somebody this transfer is not about, so the refusal says who has it.
     */
    public function test_an_approval_is_refused_when_a_third_employee_has_taken_the_asset(): void
    {
        $requestId = $this->submitTransfer([$this->assetA]);
        $this->forwardForReview($requestId, 'executive.director@nu-lipa.edu.ph');

        DB::table('assets')->where('id', $this->assetA)->update(['user_id' => $this->admin->id]);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        // Said on the page as well, before the decision is attempted.
        $this->get("/executive-review/{$token}")
            ->assertOk()
            ->assertSee('Somebody else has taken custody')
            ->assertSee('Lito Uy');

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])
            ->assertRedirect("/executive-review/{$token}");

        $message = (string) session('executive_review_error');

        $this->assertStringContainsString('out of date', $message);
        $this->assertStringContainsString('AST-0001', $message);
        $this->assertStringContainsString('Lito Uy', $message, 'The refusal does not name who holds the asset now.');

        // Nothing was recorded and nothing moved.
        $this->assertSame(TransferReviews::STATUS_VERIFIED, $this->reviewRow($requestId)->status);
        $this->assertSame($this->admin->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }
}
