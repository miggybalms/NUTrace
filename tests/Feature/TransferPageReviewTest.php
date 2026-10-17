<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetTransfer;
use App\Support\TransferReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The Executive Director's review, started from the Transfer page.
 *
 * The office does not only answer employee requests: on the Transfer page it can
 * hand a relocation it set up itself to the Executive Director, who decides
 * through the same emailed link. This is the same review machinery read through
 * a second door, so what is tested here is the second door — that nothing moves
 * when the office asks, that the invitation reaches the mailbox that was typed,
 * that a duplicate send does not ask twice, and that the Transfer page's own
 * Executive Review tab can answer "what exactly did we send?" without going back
 * to the Requests page.
 *
 * Every test goes through the real routes and reads the link out of the email
 * the system actually sent.
 */
class TransferPageReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
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
        ]);

        $this->admin     = $this->account(3, 1, 'amo@nu-lipa.edu.ph', 'Admin');
        $this->employee  = $this->account(1, 1, 'nena@nu-lipa.edu.ph', 'Employee');
        $this->recipient = $this->account(2, 2, 'rico@nu-lipa.edu.ph', 'Employee');

        $this->assetA = $this->asset('AST-0001', 'Dell Laptop');
        $this->assetB = $this->asset('AST-0002', 'Epson Projector');

        config([
            'transfer_review.email' => 'executive.director@nu-lipa.edu.ph',
            'mail.from.address'     => 'nutrace@nu-lipa.edu.ph',
            'mail.from.name'        => 'NU Trace',
        ]);
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    private function account(int $employeeNumberId, int $departmentId, string $email, string $role): User
    {
        return User::create([
            'employee_numbers_id' => $employeeNumberId,
            'department_id'       => $departmentId,
            'email'               => $email,
            'password'            => Hash::make('secret-password'),
            'role'                => $role,
            'status'              => 'Active',
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

    /** Every plain-text body the mailer was handed, oldest first. */
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

    /** The link the most recent invitation email carried. */
    private function latestToken(): string
    {
        preg_match_all('#/executive-review/([A-Za-z0-9]{64})#', $this->allEmails(), $matches);

        $this->assertNotEmpty($matches[1], 'No review link was found in the emails that were sent.');

        return $matches[1][count($matches[1]) - 1];
    }

    /** The code the invitation email for this link carried. */
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

    /** Verify the mailbox the way the recipient does, in one step. */
    private function verifyMailbox(string $token): void
    {
        $this->post("/executive-review/{$token}/verify", ['code' => $this->invitationCode($token)])
            ->assertRedirect("/executive-review/{$token}");
    }

    /**
     * Send a relocation to the Executive Director the way the Transfer page does.
     *
     * @param  array<int, int>  $assetIds
     * @return object  the requests row that now holds the held transfer
     */
    private function sendForReview(array $assetIds, ?User $to = null, string $email = 'executive.director@nu-lipa.edu.ph'): object
    {
        $response = $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id'           => $this->employee->id,
            'to_user_id'             => ($to ?? $this->recipient)->id,
            'asset_ids'              => $assetIds,
            'reason'                 => 'Employee Relocation',
            'notes'                  => 'These should move to the new custodian.',
            'send_for_review'        => 1,
            'review_recipient_email' => $email,
        ]);

        $response->assertRedirect();

        $request = DB::table('requests')->where('request_type', 'Transfer')->orderByDesc('id')->first();

        $this->assertNotNull($request, 'The transfer was never recorded as a request.');

        return $request;
    }

    private function reviewRow(int $requestId): object
    {
        $row = DB::table('transfer_reviews')->where('request_id', $requestId)->orderByDesc('id')->first();

        $this->assertNotNull($row, "No invitation was recorded for request #{$requestId}.");

        return $row;
    }

    private function boardRow(int $reviewId): ?array
    {
        foreach (TransferReviews::board() as $row) {
            if ($row['id'] === $reviewId) {
                return $row;
            }
        }

        return null;
    }

    /* ═══════ 1. The office can ask from the Transfer page ═══════ */

    public function test_the_transfer_page_can_send_a_transfer_to_the_executive_director(): void
    {
        $request = $this->sendForReview([$this->assetA, $this->assetB]);

        // The transfer is recorded the way every other transfer is: an approved
        // Transfer request with its assets, so the review, the audit trail and
        // the Requests page all read it in the shape they already know.
        $this->assertSame('Approved', $request->status);
        $this->assertSame($this->employee->id, (int) $request->user_id);
        $this->assertSame($this->recipient->id, (int) $request->assign_to_user_id);
        $this->assertSame('Employee Relocation', $request->Note);

        $this->assertSame([$this->assetA, $this->assetB], DB::table('request_items')
            ->where('request_id', $request->id)->orderBy('id')->pluck('asset_id')
            ->map(fn ($id) => (int) $id)->all());

        // Nothing changed hands. This is the promise the invitation email makes,
        // and the whole point of the review: both assets are still the sending
        // employee's, with no handover written anywhere.
        foreach ([$this->assetA, $this->assetB] as $assetId) {
            $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $assetId)->value('user_id'));
        }

        $this->assertSame(0, DB::table('audit_logs')->where('action_type', 'TRANSFER')->count());
        $this->assertSame(0, DB::table('asset_accountability')->count());

        // The invitation went to the address the office typed, and only its hash
        // was written down.
        $review = $this->reviewRow((int) $request->id);
        $token  = $this->latestToken();

        $this->assertSame('awaiting_verification', $review->status);
        $this->assertSame('executive.director@nu-lipa.edu.ph', $review->recipient_email);
        $this->assertSame(hash('sha256', $token), $review->token_hash);
        $this->assertSame(['executive.director@nu-lipa.edu.ph'], $this->lastEmailRecipients());

        // The email carries the link and the code, names the transfer it is
        // about, and promises nothing beyond that: the assets themselves are
        // read on the page the link opens, behind the mailbox proof.
        $this->assertStringContainsString(
            'TR-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT),
            $this->allEmails()
        );
        $this->assertStringContainsString('Nothing is approved, and no asset changes hands', $this->allEmails());

        // The employee whose assets are on hold is told, and told the truth:
        // they still hold them.
        $this->assertSame(1, DB::table('notifications')
            ->where('user_id', $this->employee->id)
            ->where('title', 'Transfer Awaiting the Executive Director')
            ->count());
    }

    public function test_the_office_response_says_the_assets_have_not_moved_and_opens_the_review(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id'           => $this->employee->id,
            'to_user_id'             => $this->recipient->id,
            'asset_ids'              => [$this->assetA],
            'reason'                 => 'Employee Relocation',
            'send_for_review'        => 1,
            'review_recipient_email' => 'executive.director@nu-lipa.edu.ph',
        ]);

        $review = DB::table('transfer_reviews')->orderByDesc('id')->first();

        $response->assertRedirect('/admin/transfer?review=' . $review->id);
        $response->assertSessionHas('success');

        $message = (string) session('success');

        $this->assertStringContainsString('executive.director@nu-lipa.edu.ph', $message);
        $this->assertStringContainsString('Nothing has changed hands', $message);

        // The page the office lands on holds the review it just sent, with the
        // details it was sent about — no trip to the Requests page.
        $page = $this->actingAs($this->admin)->get('/admin/transfer?review=' . $review->id);
        $page->assertOk();
        $page->assertSee('TR-' . str_pad((string) $review->request_id, 4, '0', STR_PAD_LEFT));
        $page->assertSee('executive.director@nu-lipa.edu.ph');
        $page->assertSee('Awaiting decision');
        $page->assertSee('Prepared here');
        $page->assertSee('AST-0001');
    }

    /* ═══════ 2. What the Executive Director decides is what happens ═══════ */

    public function test_approving_a_transfer_sent_from_the_transfer_page_moves_the_assets(): void
    {
        $request = $this->sendForReview([$this->assetA, $this->assetB]);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])->assertRedirect();

        foreach ([$this->assetA, $this->assetB] as $assetId) {
            $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $assetId)->value('user_id'));

            $rows = DB::table('asset_accountability')->where('asset_id', $assetId)->orderBy('id')->get();

            $this->assertCount(2, $rows, 'The handover never reached the accountability history.');
            $this->assertSame($this->employee->id, (int) $rows[0]->user_id);
            $this->assertSame(0, (int) $rows[0]->Is_Current);
            $this->assertSame($this->recipient->id, (int) $rows[1]->user_id);
            $this->assertSame(1, (int) $rows[1]->Is_Current);
        }

        $decision = DB::table('transfer_reviews')->where('request_id', $request->id)->orderByDesc('id')->first();

        $this->assertSame('decided', $decision->status);
        $this->assertSame('approved', $decision->decision);
        $this->assertSame(2, (int) $decision->approved_count);

        // The report the request produces is the one an approved transfer always
        // produces, whatever door released it — one TRANSFER entry per asset.
        $this->assertSame(2, DB::table('audit_logs')
            ->where('request_id', $request->id)
            ->where('action_type', 'TRANSFER')
            ->whereNotNull('asset_id')
            ->count());

        $row = $this->boardRow((int) $decision->id);

        $this->assertNotNull($row);
        $this->assertSame('transfer_page', $row['origin']);
        $this->assertSame('Approved in full', $row['decision_label']);
        $this->assertSame('2 of 2 assets approved and now assigned to Rico Bautista.', $row['outcome']);
        $this->assertFalse($row['awaiting']);
        $this->assertFalse($row['can_revoke']);
    }

    public function test_approving_only_some_assets_leaves_the_rest_where_they_were(): void
    {
        $request = $this->sendForReview([$this->assetA, $this->assetB]);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", [
            'decision'  => 'approve_selected',
            'asset_ids' => [$this->assetA],
        ])->assertRedirect();

        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetB)->value('user_id'));

        $review = DB::table('transfer_reviews')->where('request_id', $request->id)->orderByDesc('id')->first();
        $row    = $this->boardRow((int) $review->id);

        $this->assertNotNull($row);
        $this->assertSame('partially_approved', $row['decision']);
        $this->assertSame('1 of 2 assets approved and now assigned to Rico Bautista; 1 not approved and left where they were.', $row['outcome']);

        $decisions = collect($row['assets'])->keyBy('id');

        $this->assertSame('approved', $decisions[$this->assetA]['decision']);
        $this->assertSame('Approved', $decisions[$this->assetA]['decision_label']);
        $this->assertSame('declined', $decisions[$this->assetB]['decision']);
        $this->assertSame('Not approved', $decisions[$this->assetB]['decision_label']);
    }

    public function test_rejecting_a_transfer_sent_from_the_transfer_page_transfers_nothing(): void
    {
        $request = $this->sendForReview([$this->assetA, $this->assetB]);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", [
            'decision'         => 'reject',
            'rejection_reason' => 'The receiving office has no budget line for this equipment.',
        ])->assertRedirect();

        foreach ([$this->assetA, $this->assetB] as $assetId) {
            $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $assetId)->value('user_id'));
        }

        $this->assertSame(0, DB::table('asset_accountability')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('action_type', 'TRANSFER')->count());

        $review = DB::table('transfer_reviews')->where('request_id', $request->id)->orderByDesc('id')->first();
        $row    = $this->boardRow((int) $review->id);

        $this->assertNotNull($row);
        $this->assertSame('rejected', $row['decision']);
        $this->assertSame(
            'Rejected — nothing was transferred. Reason: The receiving office has no budget line for this equipment.',
            $row['outcome']
        );
    }

    /* ═══════ 3. The hold is real, and it can be lifted ═══════ */

    public function test_an_unanswered_transfer_cannot_be_completed_by_another_door(): void
    {
        $this->sendForReview([$this->assetA]);

        // The same relocation, confirmed the ordinary way while the Executive
        // Director still has it: refused as a whole, and nothing moves.
        $response = $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id' => $this->employee->id,
            'to_user_id'   => $this->recipient->id,
            'asset_ids'    => [$this->assetA],
            'reason'       => 'Employee Relocation',
        ]);

        $response->assertSessionHasErrors('transfer');

        $this->assertStringContainsString('on hold', (string) session('errors')->first('transfer'));
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame(1, DB::table('requests')->where('request_type', 'Transfer')->count());

        // The same asset cannot be put into a second review either.
        $second = $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id'           => $this->employee->id,
            'to_user_id'             => $this->admin->id,
            'asset_ids'              => [$this->assetA],
            'reason'                 => 'Employee Relocation',
            'send_for_review'        => 1,
            'review_recipient_email' => 'executive.director@nu-lipa.edu.ph',
        ]);

        $second->assertSessionHasErrors('transfer');
        $this->assertSame(1, DB::table('transfer_reviews')->count());
    }

    public function test_the_office_can_withdraw_an_invitation_it_sent_and_release_the_assets(): void
    {
        $request = $this->sendForReview([$this->assetA]);
        $review  = $this->reviewRow((int) $request->id);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$request->id}/review/revoke", ['reason' => 'The receiving employee leaves next month.'])
            ->assertOk();

        $this->assertSame('revoked', DB::table('transfer_reviews')->where('id', $review->id)->value('status'));

        // The link is dead — its token is gone, so it is not even recognisable
        // as a link any more — and the assets are free again.
        $token = $this->latestToken();

        $this->get("/executive-review/{$token}")->assertStatus(404);
        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])->assertStatus(404);

        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id' => $this->employee->id,
            'to_user_id'   => $this->recipient->id,
            'asset_ids'    => [$this->assetA],
            'reason'       => 'Employee Relocation',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));

        $row = $this->boardRow((int) $review->id);

        $this->assertNotNull($row);
        $this->assertSame('revoked', $row['status']);
        $this->assertSame('Invitation withdrawn', $row['status_label']);
        $this->assertFalse($row['can_revoke']);
        $this->assertTrue($row['can_resend']);
    }

    public function test_an_invitation_can_be_sent_again_to_a_new_address(): void
    {
        $request = $this->sendForReview([$this->assetA]);

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$request->id}/review/resend", ['recipient_email' => 'new.director@nu-lipa.edu.ph'])
            ->assertOk();

        $reviews = DB::table('transfer_reviews')->where('request_id', $request->id)->orderBy('id')->get();

        $this->assertCount(2, $reviews, 'A resend must issue a replacement invitation, not a second live one.');
        $this->assertSame('revoked', $reviews[0]->status);
        $this->assertSame('awaiting_verification', $reviews[1]->status);
        $this->assertSame('new.director@nu-lipa.edu.ph', $reviews[1]->recipient_email);
        $this->assertSame(['new.director@nu-lipa.edu.ph'], $this->lastEmailRecipients());

        // One live link at a time: the one that was replaced no longer opens.
        preg_match_all('#/executive-review/([A-Za-z0-9]{64})#', $this->allEmails(), $matches);
        $this->get('/executive-review/' . $matches[1][0])->assertStatus(404);
        $this->get('/executive-review/' . $matches[1][count($matches[1]) - 1])->assertOk();
    }

    /* ═══════ 4. A second submit is not a second question ═══════ */

    public function test_sending_the_same_transfer_twice_asks_the_executive_director_once(): void
    {
        $request = $this->sendForReview([$this->assetA]);

        // The same confirmation, a double click or a retried POST.
        $second = $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id'           => $this->employee->id,
            'to_user_id'             => $this->recipient->id,
            'asset_ids'              => [$this->assetA],
            'reason'                 => 'Employee Relocation',
            'send_for_review'        => 1,
            'review_recipient_email' => 'executive.director@nu-lipa.edu.ph',
        ]);

        $second->assertSessionHas('notice');
        $second->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('requests')->where('request_type', 'Transfer')->count());
        $this->assertSame(1, DB::table('transfer_reviews')->count());
        $this->assertSame('awaiting_verification', $this->reviewRow((int) $request->id)->status);
    }

    /* ═══════ 5. The address is the delivery, so it is checked ═══════ */

    public function test_a_transfer_is_not_recorded_when_the_email_address_is_not_usable(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id'           => $this->employee->id,
            'to_user_id'             => $this->recipient->id,
            'asset_ids'              => [$this->assetA],
            'reason'                 => 'Employee Relocation',
            'send_for_review'        => 1,
            'review_recipient_email' => 'not-an-address',
        ]);

        $response->assertSessionHasErrors('review_recipient_email');

        $this->assertSame(0, DB::table('requests')->where('request_type', 'Transfer')->count());
        $this->assertSame(0, DB::table('transfer_reviews')->count());
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    /* ═══════ 6. The tab holds every review, whichever door sent it ═══════ */

    public function test_the_tab_lists_a_transfer_forwarded_from_the_requests_page(): void
    {
        // The employee's own request, approved by the office and forwarded.
        $this->actingAs($this->employee)->post('/user/requests/store', [
            'request_type'      => 'Transfer',
            'asset_ids'         => [$this->assetB],
            'assign_to_user_id' => $this->recipient->id,
            'notes'             => 'Handing this over after the reshuffle.',
        ])->assertRedirect();

        $requestId = (int) DB::table('requests')->max('id');

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve", [
            'forward_for_review' => true,
            'recipient_email'    => 'executive.director@nu-lipa.edu.ph',
        ])->assertOk()->assertJson(['forwarded' => true, 'delivered' => true]);

        $review = $this->reviewRow($requestId);
        $row    = $this->boardRow((int) $review->id);

        $this->assertNotNull($row, 'A transfer forwarded from Requests never reached the Transfer page tab.');
        $this->assertSame('request', $row['origin']);
        $this->assertSame('TR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT), $row['reference']);
        $this->assertSame($this->employee->id, $row['from']['id']);
        $this->assertSame($this->recipient->id, $row['to']['id']);
        $this->assertSame('Handing this over after the reshuffle.', $row['reason']);
        $this->assertTrue($row['awaiting']);
        $this->assertTrue($row['can_revoke']);
        $this->assertSame('executive.director@nu-lipa.edu.ph', $row['recipient_email']);
        $this->assertSame('Lito Uy', $row['released_by'], 'The office that released it should be on the row.');
        $this->assertSame([$this->assetB], array_column($row['assets'], 'id'));

        $page = $this->actingAs($this->admin)->get('/admin/transfer');
        $page->assertOk();
        $page->assertSee($row['reference']);
        $page->assertSee('Employee request');
        $page->assertSee('executive.director@nu-lipa.edu.ph');
        $page->assertSee('AST-0002');
    }

    public function test_the_tab_is_read_only_and_leaks_no_token(): void
    {
        $request = $this->sendForReview([$this->assetA]);
        $token   = $this->latestToken();

        $page = $this->actingAs($this->admin)->get('/admin/transfer');

        $page->assertOk();
        $page->assertDontSee($token);
        $page->assertDontSee((string) $this->reviewRow((int) $request->id)->token_hash);

        $row = $this->boardRow((int) $this->reviewRow((int) $request->id)->id);

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('token', $row);
        $this->assertStringNotContainsString($token, json_encode($row));
    }

    public function test_a_transfer_waiting_on_the_executive_director_is_not_a_completed_transfer(): void
    {
        $request = $this->sendForReview([$this->assetA, $this->assetB]);

        $reference = 'TR-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT);

        // Nothing was carried out, so the history the Transfer History tab and
        // the page's own totals are built from has nothing in it — otherwise the
        // page would report a handover the assets themselves contradict.
        $this->assertTrue(AssetTransfer::history()->isEmpty());

        $page = $this->actingAs($this->admin)->get('/admin/transfer');
        $page->assertOk();

        // The Transfer History tab is fed an empty list: the transfer appears only
        // on the Executive Review tab, as a review still waiting on a decision.
        $page->assertSee('const TRANSFER_HISTORY  = []', false);

        // Once the decision comes back approved it is a transfer like any other.
        $token = $this->latestToken();
        $this->verifyMailbox($token);
        $this->post("/executive-review/{$token}/decide", ['decision' => 'approve_all'])->assertRedirect();

        $history = AssetTransfer::history();

        $this->assertCount(1, $history);
        $this->assertSame((int) $request->id, $history[0]['id']);
        $this->assertSame(2, $history[0]['asset_count']);
        $this->assertSame($this->recipient->id, $history[0]['to']['id']);

        $page = $this->actingAs($this->admin)->get('/admin/transfer');
        $page->assertOk();
        $this->assertStringContainsString('"reference":"' . $reference . '"', $page->getContent());
    }

    public function test_a_rejected_transfer_never_appears_in_the_transfer_history(): void
    {
        $this->sendForReview([$this->assetA]);

        $token = $this->latestToken();
        $this->verifyMailbox($token);

        $this->post("/executive-review/{$token}/decide", [
            'decision'         => 'reject',
            'rejection_reason' => 'Not this term.',
        ])->assertRedirect();

        $this->assertTrue(AssetTransfer::history()->isEmpty());
        $this->assertSame($this->employee->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
    }

    public function test_a_direct_transfer_still_completes_without_asking_anybody(): void
    {
        $this->actingAs($this->admin)->post('/admin/transfer', [
            'from_user_id' => $this->employee->id,
            'to_user_id'   => $this->recipient->id,
            'asset_ids'    => [$this->assetA],
            'reason'       => 'Employee Relocation',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->recipient->id, (int) DB::table('assets')->where('id', $this->assetA)->value('user_id'));
        $this->assertSame(0, DB::table('transfer_reviews')->count());

        // And it is not invented into the review tab.
        $this->assertSame([], TransferReviews::board());
    }
}
