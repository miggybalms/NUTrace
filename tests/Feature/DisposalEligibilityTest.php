<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DisposalEligibility;
use App\Support\Disposals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The three-year waiting period between approving a disposal and disposing.
 *
 * The rule these tests exist to prove: approval starts a clock, reaching the
 * end of that clock only makes the request *eligible*, and the office still has
 * to confirm the finalization - while nothing at all may retire the asset
 * before the clock runs out, whichever screen is used.
 */
class DisposalEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private User $head;
    private int $assetId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Juan Dela Cruz', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Dept Head', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'juan@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->head = User::create([
            'employee_numbers_id' => 3, 'department_id' => 1, 'email' => 'head@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Department Head', 'status' => 'Active',
        ]);

        $this->assetId = $this->makeAsset('AST-0001', 'Laptop');
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    private function makeAsset(string $code, string $name = 'Printer'): int
    {
        return (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => $code, 'Asset_name' => $name,
            'Category' => 'Info and Equipment', 'Condition' => 'Fair', 'Lifecycle_Status' => 'Active',
            'serial_Number' => 'SN-' . $code, 'asset_location' => 'Registrar Office',
            'accusion_date' => now()->subYears(2)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Submit a Disposal request the way the user form does.
     *
     * @param  array<int, int>  $assetIds
     */
    private function submitDisposalRequest(array $assetIds = [], string $status = 'Pending'): int
    {
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id'      => $this->employee->id,
            'asset_id'     => null,
            'request_type' => 'Disposal',
            'status'       => $status,
            'Note'         => 'The unit is damaged beyond repair.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        foreach ($assetIds ?: [$this->assetId] as $assetId) {
            DB::table('request_items')->insert([
                'request_id' => $requestId, 'asset_id' => $assetId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $requestId;
    }

    private function approve(int $requestId): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();
    }

    /** Approve a fresh request and hand back [request id, form id]. */
    private function approveAndForm(array $assetIds = []): array
    {
        $requestId = $this->submitDisposalRequest($assetIds);
        $this->approve($requestId);

        return [$requestId, (int) DB::table('disposal_forms')->where('request_id', $requestId)->value('id')];
    }

    /** Take a form all the way to a recorded signed copy. */
    private function takeToSigned(int $formId): void
    {
        $items = [];

        foreach (DB::table('disposal_form_items')->where('form_id', $formId)->orderBy('id')->get() as $item) {
            $items[(int) $item->id] = [
                'tag_no'         => $item->tag_no ?: ('TAG-' . $item->id),
                'quantity'       => 1,
                'description'    => $item->description ?: ('Item ' . $item->id),
                'condition_code' => 'F',
                'reason_code'    => 'B',
            ];
        }

        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/save", [
            'office_department'      => 'Facilities',
            'expected_disposal_date' => now()->addWeek()->toDateString(),
            'method_key'             => 'scrap',
            'items'                  => $items,
        ])->assertRedirect("/admin/disposal/forms/{$formId}");

        $this->actingAs($this->admin)->post('/admin/disposal/forms/print', ['ids' => [$formId]])->assertOk();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/signed", ['received_date' => now()->toDateString()])
            ->assertRedirect("/admin/disposal/forms/{$formId}");
    }

    private function finalize(int $formId, ?string $date = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/finalize", [
            'actual_disposal_date' => $date ?: now()->toDateString(),
            'confirm'              => '1',
        ]);
    }

    private function requestRow(int $requestId): object
    {
        return DB::table('requests')->where('id', $requestId)->first();
    }

    private function form(int $formId): object
    {
        return DB::table('disposal_forms')->where('id', $formId)->first();
    }

    /* ═══════════ 1. the approval timestamp ═══════════ */

    public function test_approval_records_the_authoritative_approval_timestamp(): void
    {
        $requestId = $this->submitDisposalRequest();

        $this->assertNull($this->requestRow($requestId)->approved_at, 'submitting must not start the clock');

        $this->approve($requestId);

        $approvedAt = $this->requestRow($requestId)->approved_at;

        $this->assertNotNull($approvedAt, 'approving must record when it happened');
        $this->assertSame(
            now()->toDateTimeString(),
            Carbon::parse($approvedAt)->toDateTimeString(),
            'the stamp is the moment of approval, not a date typed into a form'
        );
    }

    public function test_a_second_approval_attempt_cannot_move_the_timestamp(): void
    {
        [$requestId] = $this->approveAndForm();

        $original = $this->requestRow($requestId)->approved_at;

        $this->travel(1)->years();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertStatus(422);

        $this->assertSame($original, $this->requestRow($requestId)->approved_at);
    }

    /* ═══════════ 2. three-year eligibility ═══════════ */

    public function test_a_request_approved_today_is_not_eligible_for_three_years(): void
    {
        [$requestId] = $this->approveAndForm();

        $state = DisposalEligibility::state($this->requestRow($requestId));

        $this->assertSame(DisposalEligibility::STATE_WAITING, $state['state']);
        $this->assertSame(3, DisposalEligibility::years());
        $this->assertSame(
            Carbon::parse($this->requestRow($requestId)->approved_at)->addYears(3)->toDateTimeString(),
            $state['eligible_at']->toDateTimeString()
        );
        $this->assertGreaterThan(1000, $state['remaining_days']);
    }

    public function test_the_waiting_period_ends_exactly_three_calendar_years_after_approval(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 09:30:00'));

        [$requestId] = $this->approveAndForm();

        $state = DisposalEligibility::state($this->requestRow($requestId));

        $this->assertSame('2029-10-25 09:30:00', $state['eligible_at']->toDateTimeString());
    }

    public function test_a_leap_day_approval_falls_due_on_the_first_of_march(): void
    {
        // 29 February 2028 is a leap day; three years later is not a leap year.
        $this->travelTo(Carbon::parse('2028-02-29 10:00:00'));

        [$requestId] = $this->approveAndForm();

        $state = DisposalEligibility::state($this->requestRow($requestId));

        $this->assertSame('2031-03-01 10:00:00', $state['eligible_at']->toDateTimeString());
    }

    public function test_the_clock_runs_from_approval_not_from_form_preparation(): void
    {
        [$requestId, $formId] = $this->approveAndForm();

        // Two years later the office prepares, prints and files the form. None
        // of that may restart the clock.
        $this->travel(2)->years();
        $this->takeToSigned($formId);

        $state = DisposalEligibility::state($this->requestRow($requestId));

        $this->assertSame(DisposalEligibility::STATE_WAITING, $state['state']);
        $this->assertSame(
            Carbon::parse($this->requestRow($requestId)->approved_at)->addYears(3)->toDateTimeString(),
            $state['eligible_at']->toDateTimeString(),
            'the eligible date is approval + 3 years, whatever the form did in between'
        );
    }

    /* ═══════════ 3. finalization is refused before eligibility ═══════════ */

    public function test_finalization_is_refused_before_the_waiting_period_ends(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        $this->finalize($formId)
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('error', function ($message) {
                return str_contains($message, 'waiting period')
                    && str_contains($message, 'final disposal becomes available on');
            });

        // Nothing moved: the form is still signed, the asset is still held, no
        // disposal record exists.
        $this->assertSame('signed', $this->form($formId)->status);
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(0, DB::table('disposals')->count());

        // And the service refuses on its own, for any caller.
        $result = \App\Support\DisposalForms::finalize($formId, ['confirm' => 1], $this->admin);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('cannot be finalized yet', $result['message']);
    }

    public function test_the_boundary_moment_itself_is_eligible(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 09:00:00'));

        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        // One second early: refused.
        $this->travelTo(Carbon::parse('2029-10-25 08:59:59'));
        $this->finalize($formId)->assertSessionHas('error');

        // The exact eligibility instant: allowed.
        $this->travelTo(Carbon::parse('2029-10-25 09:00:00'));
        $this->finalize($formId)->assertRedirect('/admin/disposal?tab=forms');

        $this->assertSame(Disposals::DISPOSED_STATUS, DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    public function test_finalization_after_the_waiting_period_records_the_disposal(): void
    {
        [$requestId, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        $this->travel(3)->years();

        $this->finalize($formId)->assertRedirect('/admin/disposal?tab=forms');

        $this->assertSame('finalized', $this->form($formId)->status);
        $this->assertSame(Disposals::DISPOSED_STATUS, DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(1, DB::table('disposals')->where('Request_id', $requestId)->count());

        $notification = DB::table('notifications')
            ->where('user_id', $this->employee->id)
            ->where('title', 'Disposal Completed')
            ->first();
        $this->assertNotNull($notification, 'the requester is still told when the disposal is completed');
    }

    public function test_the_signed_form_is_still_required_after_the_waiting_period(): void
    {
        [, $formId] = $this->approveAndForm();

        $this->travel(4)->years();

        $this->finalize($formId)
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'cannot be finalized yet'));

        $this->assertSame(0, DB::table('disposals')->count());
    }

    public function test_an_employee_cannot_finalize_even_after_the_waiting_period(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);
        $this->travel(4)->years();

        foreach ([$this->employee, $this->head] as $account) {
            $this->actingAs($account)
                ->post("/admin/disposal/forms/{$formId}/finalize", ['confirm' => '1'])
                ->assertStatus(403);
        }

        $this->assertSame('signed', $this->form($formId)->status);
        $this->assertSame(0, DB::table('disposals')->count());
    }

    /* ═══════════ 4. the eligibility notification ═══════════ */

    public function test_the_office_is_told_once_when_the_waiting_period_ends(): void
    {
        [$requestId] = $this->approveAndForm();

        // Before the period ends: silence.
        $this->travel(2)->years();
        $this->artisan('disposal:check-eligibility')->assertSuccessful();

        $this->assertSame(0, DB::table('notifications')->where('title', 'Disposal Eligibility Reached')->count());

        // At the end of the period: exactly one notice, naming the request and
        // the eligibility date, and saying plainly that nothing was disposed of.
        $this->travel(1)->years();

        $eligible = DisposalEligibility::state($this->requestRow($requestId))['eligible_at'];

        $this->artisan('disposal:check-eligibility')->assertSuccessful();

        $notifications = DB::table('notifications')->where('title', 'Disposal Eligibility Reached')->get();

        $this->assertCount(1, $notifications);
        $this->assertSame($this->admin->id, (int) $notifications->first()->user_id);
        $this->assertStringContainsString('#' . $requestId, $notifications->first()->message);
        $this->assertStringContainsString($eligible->format('F j, Y'), $notifications->first()->message);
        $this->assertStringContainsString('NOT been disposed of', $notifications->first()->message);
        $this->assertStringContainsString('/admin/disposal?tab=forms', $notifications->first()->message);

        // The request is stamped as handled, and the log records it once.
        $this->assertNotNull($this->requestRow($requestId)->disposal_eligibility_notified_at);

        $audit = DB::table('audit_logs')->where('notes', 'like', 'Disposal eligibility reached%')->get();
        $this->assertCount(1, $audit);
        $this->assertSame($requestId, (int) $audit->first()->request_id);

        // Running it again - tomorrow, next month, manually - must not repeat.
        $this->travel(1)->months();
        $this->artisan('disposal:check-eligibility')->assertSuccessful();
        $this->travel(1)->months();
        $this->artisan('disposal:check-eligibility')->assertSuccessful();

        $this->assertCount(1, DB::table('notifications')->where('title', 'Disposal Eligibility Reached')->get());
        $this->assertCount(1, DB::table('audit_logs')->where('notes', 'like', 'Disposal eligibility reached%')->get());
    }

    public function test_the_command_notifies_nothing_for_an_ineligible_or_rejected_request(): void
    {
        [$pendingId] = $this->approveAndForm();

        $rejectedId = $this->submitDisposalRequest();
        DB::table('requests')->where('id', $rejectedId)->update(['status' => 'Rejected']);

        $this->travel(4)->years();

        $this->artisan('disposal:check-eligibility')->assertSuccessful();

        $notified = DB::table('notifications')
            ->where('title', 'Disposal Eligibility Reached')
            ->pluck('reference_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($pendingId, $notified, 'the approved, old request is eligible');
        $this->assertNotContains($rejectedId, $notified, 'a rejected request is never announced');
    }

    public function test_the_manual_run_respects_the_eligibility_date(): void
    {
        [$requestId] = $this->approveAndForm();

        // `--request` narrows the run; it does not skip the date.
        $this->artisan('disposal:check-eligibility', ['--request' => $requestId])->assertSuccessful();

        $this->assertSame(0, DB::table('notifications')->where('title', 'Disposal Eligibility Reached')->count());
        $this->assertNull($this->requestRow($requestId)->disposal_eligibility_notified_at);
    }

    /* ═══════════ 5. legacy records and duplicates ═══════════ */

    public function test_a_legacy_request_without_an_approval_timestamp_is_not_blocked(): void
    {
        [$requestId, $formId] = $this->approveAndForm();

        // A request approved before this feature existed: no stamp, no invented
        // start date, and the office can still complete what it started.
        DB::table('requests')->where('id', $requestId)->update(['approved_at' => null]);

        $this->takeToSigned($formId);

        $state = DisposalEligibility::state($this->requestRow($requestId));
        $this->assertSame(DisposalEligibility::STATE_UNKNOWN, $state['state']);

        $this->finalize($formId)->assertRedirect('/admin/disposal?tab=forms');

        $this->assertSame('finalized', $this->form($formId)->status);
    }

    public function test_one_asset_cannot_be_disposed_of_twice_through_two_requests(): void
    {
        [$firstRequest, $firstForm] = $this->approveAndForm();
        $this->takeToSigned($firstForm);
        $this->travel(3)->years();
        $this->finalize($firstForm)->assertRedirect('/admin/disposal?tab=forms');

        // A second request for the same object, approved afterwards.
        [$secondRequest, $secondForm] = $this->approveAndForm();
        $this->takeToSigned($secondForm);
        $this->travel(4)->years();

        $this->finalize($secondForm)
            ->assertRedirect("/admin/disposal/forms/{$secondForm}")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'disposal record'));

        $this->assertSame(1, DB::table('disposals')->where('Asset_id', $this->assetId)->count());
        $this->assertNotSame($firstRequest, $secondRequest);
    }

    /* ═══════════ 6. no other screen can retire the asset early ═══════════ */

    public function test_another_disposal_screen_cannot_retire_an_asset_inside_the_waiting_period(): void
    {
        [$requestId] = $this->approveAndForm();

        $this->travel(6)->months();

        // The asset status card.
        $this->actingAs($this->admin)
            ->postJson("/admin/api/assets/{$this->assetId}/status", ['status' => 'Disposal'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // The repair screen's "send to disposal".
        $this->actingAs($this->admin)
            ->postJson('/admin/disposals/create', [
                'asset_id' => $this->assetId,
                'reason'   => 'Beyond repair',
            ])
            ->assertStatus(422);

        // The legacy untied disposal record.
        $this->actingAs($this->admin)
            ->postJson('/admin/disposal/record', [
                'asset_id' => $this->assetId,
                'reason'   => 'Obsolete',
            ])
            ->assertStatus(422);

        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(0, DB::table('disposals')->count());
        $this->assertSame('draft', $this->form((int) DB::table('disposal_forms')->where('request_id', $requestId)->value('id'))->status);
    }

    public function test_another_disposal_screen_still_works_for_an_asset_with_no_waiting_request(): void
    {
        // No approved disposal request covers this asset, so nothing holds it
        // back: the existing behaviour is untouched.
        $this->actingAs($this->admin)
            ->postJson("/admin/disposals/create", [
                'asset_id' => $this->assetId,
                'reason'   => 'Beyond repair',
            ])
            ->assertOk();

        $this->assertSame(Disposals::DISPOSED_STATUS, DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(1, DB::table('disposals')->count());
    }

    /* ═══════════ 7. the interface explains it ═══════════ */

    public function test_the_form_explains_the_waiting_period_and_why_finalization_is_unavailable(): void
    {
        [$requestId, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        $state = DisposalEligibility::state($this->requestRow($requestId));

        $html = $this->actingAs($this->admin)
            ->get("/admin/disposal/forms/{$formId}")
            ->assertOk()
            ->assertSee('Three-Year Waiting Period')
            ->assertSee('Awaiting Eligibility Date')
            ->assertSee('Disposal request')
            ->assertSee($state['eligible_at']->format('M j, Y'))
            ->getContent();

        $this->assertStringContainsString('three-year waiting period ends on', $html);
    }

    public function test_the_queue_shows_which_requests_are_waiting_and_which_are_eligible(): void
    {
        [$waitingRequest, $waitingForm] = $this->approveAndForm();
        $this->takeToSigned($waitingForm);

        [$eligibleRequest, $eligibleForm] = $this->approveAndForm([$this->makeAsset('AST-0002')]);
        $this->takeToSigned($eligibleForm);

        // Only the second request's clock runs out.
        DB::table('requests')->where('id', $eligibleRequest)->update([
            'approved_at' => now()->subYears(4)->toDateTimeString(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/disposal?tab=forms')
            ->assertOk()
            ->assertSee('Awaiting eligibility')
            ->assertSee('Eligible for final disposal');

        $this->assertNotSame($waitingRequest, $eligibleRequest);
    }
}
