<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DisposalCodes;
use App\Support\Disposals;
use App\Support\DisposalForms;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The official NU Lipa Disposal Form, stage by stage.
 *
 * The one rule this suite exists to prove: nothing short of an explicit,
 * confirmed finalization ever marks an asset as disposed of. Approving a
 * request, generating a form, saving it, printing it and filing a signed copy
 * all leave the asset's lifecycle status exactly where it was.
 */
class DisposalFormWorkflowTest extends TestCase
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

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Fair', 'Lifecycle_Status' => 'Active',
            'serial_Number' => 'SN-9F31', 'asset_location' => 'Registrar Office',
            'accusion_date' => now()->subYears(2)->toDateString(),
            'purchase_Price' => 42000.00,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    /** A second asset, for multi-asset requests. */
    private function secondAsset(string $code = 'AST-0002', string $condition = 'Good'): int
    {
        return (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => $code, 'Asset_name' => 'Printer',
            'Category' => 'Info and Equipment', 'Condition' => $condition, 'Lifecycle_Status' => 'Active',
            'serial_Number' => 'SN-0002', 'asset_location' => 'Registrar Office',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Submit a Disposal request the way the user form does.
     *
     * @param  array<int, int>  $assetIds
     */
    private function submitDisposalRequest(array $assetIds = [], string $note = 'The unit is damaged beyond repair.', string $status = 'Pending'): int
    {
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id'      => $this->employee->id,
            'asset_id'     => null,
            'request_type' => 'Disposal',
            'status'       => $status,
            'Note'         => $note,
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

    /** Approve a disposal request and hand back its generated form id. */
    private function approveAndForm(array $assetIds = []): array
    {
        $requestId = $this->submitDisposalRequest($assetIds);

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $formId = (int) DB::table('disposal_forms')->where('request_id', $requestId)->value('id');

        return [$requestId, $formId];
    }

    /**
     * The preparation payload: every required field filled in.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formPayload(int $formId, array $overrides = []): array
    {
        $items = [];

        foreach (DB::table('disposal_form_items')->where('form_id', $formId)->orderBy('id')->get() as $item) {
            $items[(int) $item->id] = [
                'tag_no'         => $item->tag_no ?: ('TAG-' . $item->id),
                'quantity'       => 1,
                'description'    => $item->description ?: ('Item ' . $item->id),
                'serial_number'  => $item->serial_number,
                'date_acquired'  => $item->date_acquired,
                'location'       => $item->location,
                'custodian'      => $item->custodian,
                'condition_code' => $item->condition_code ?: 'F',
                'reason_code'    => $item->reason_code ?: 'B',
            ];
        }

        return array_merge([
            'office_department'      => 'Facilities',
            'expected_disposal_date' => now()->addWeek()->toDateString(),
            'method_key'             => 'scrap',
            'remarks'                => 'Prepared by the Asset Management Office.',
            'items'                  => $items,
        ], $overrides);
    }

    /** Take a form all the way to a recorded signed copy. */
    private function takeToSigned(int $formId, string $methodKey = 'scrap'): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId, ['method_key' => $methodKey]))
            ->assertRedirect("/admin/disposal/forms/{$formId}");

        $this->actingAs($this->admin)
            ->post('/admin/disposal/forms/print', ['ids' => [$formId]])
            ->assertOk();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/signed", ['received_date' => now()->toDateString()])
            ->assertRedirect("/admin/disposal/forms/{$formId}");
    }

    private function form(int $formId): object
    {
        return DB::table('disposal_forms')->where('id', $formId)->first();
    }

    /* ═══════════ 1. only an approved disposal request may proceed ═══════════ */

    public function test_a_pending_disposal_request_cannot_go_to_form_preparation(): void
    {
        $requestId = $this->submitDisposalRequest();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/requests/{$requestId}/form")
            ->assertRedirect('/admin/disposal?tab=forms')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Only an approved disposal request'));

        $this->assertSame(0, DB::table('disposal_forms')->count());
    }

    public function test_a_rejected_disposal_request_cannot_go_to_form_preparation(): void
    {
        $requestId = $this->submitDisposalRequest();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/reject", ['reason' => 'Still under warranty.'])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/requests/{$requestId}/form")
            ->assertStatus(422);

        $this->assertSame(0, DB::table('disposal_forms')->count());
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    public function test_a_request_that_is_not_a_disposal_has_no_disposal_form(): void
    {
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->employee->id, 'asset_id' => $this->assetId, 'request_type' => 'Repair',
            'status' => 'Approved', 'Note' => 'Screen flickers.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/requests/{$requestId}/form")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only a disposal request has an official disposal form.');

        $this->assertSame(0, DB::table('disposal_forms')->count());
    }

    public function test_preparing_a_form_twice_reopens_the_same_one(): void
    {
        [$requestId, $formId] = $this->approveAndForm();

        // The action the queue offers for requests approved before forms were
        // generated automatically.
        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/requests/{$requestId}/form")
            ->assertOk()
            ->assertJsonPath('form_id', $formId);

        $this->assertSame(1, DB::table('disposal_forms')->where('request_id', $requestId)->count());
    }

    /* ═══════════ 2. recorded data is prefilled accurately ═══════════ */

    public function test_the_form_is_prefilled_from_the_asset_and_request_records(): void
    {
        $requestId  = $this->submitDisposalRequest([], 'Obsolete and no longer needed by the office.');
        $secondId   = $this->secondAsset();

        DB::table('request_items')->insert([
            'request_id' => $requestId, 'asset_id' => $secondId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The custodian is recorded the way the accountability ledger holds it.
        DB::table('asset_accountability')->insert([
            'asset_id' => $this->assetId, 'user_id' => $this->employee->id,
            'Assign_date' => now()->subMonths(3), 'Is_Current' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $form = DB::table('disposal_forms')->where('request_id', $requestId)->first();
        $this->assertNotNull($form);

        // The office the form is made out to, and the reason the request carries.
        $this->assertSame('Facilities', $form->office_department);
        $this->assertSame('Obsolete', $form->approved_reason);

        $items = DB::table('disposal_form_items')->where('form_id', $form->id)->orderBy('asset_id')->get();
        $this->assertCount(2, $items, 'one line per asset on the request');

        $laptop = $items->firstWhere('asset_id', $this->assetId);
        $this->assertSame('AST-0001', $laptop->tag_no);
        $this->assertSame('Laptop', $laptop->description);
        $this->assertSame('SN-9F31', $laptop->serial_number);
        $this->assertSame('Registrar Office', $laptop->location);
        $this->assertSame(now()->subYears(2)->toDateString(), (string) $laptop->date_acquired);
        $this->assertSame('Juan Dela Cruz', $laptop->custodian);
        $this->assertSame('F', $laptop->condition_code, 'a recorded condition that is on the legend is prefilled');
        $this->assertSame('O', $laptop->reason_code, '"no longer needed" is the obsolete row on the form');

        $printer = $items->firstWhere('asset_id', $secondId);
        $this->assertSame('AST-0002', $printer->tag_no);
        $this->assertSame('G', $printer->condition_code);

        // Nothing is invented: the method of disposal has never been decided
        // anywhere in the records, so the form leaves it for the office.
        $this->assertNull($form->method_key);
        $this->assertNull($form->method_code);
        $this->assertNull($form->sold_amount);
    }

    public function test_a_recorded_condition_with_no_code_on_the_form_is_left_blank(): void
    {
        // assets.Condition allows 'Excellent', which is not on the official
        // legend. Nothing may be guessed at: the office completes it.
        DB::table('assets')->where('id', $this->assetId)->update(['Condition' => 'Excellent']);

        [, $formId] = $this->approveAndForm();

        $item = DB::table('disposal_form_items')->where('form_id', $formId)->first();
        $this->assertNull($item->condition_code);
    }

    /* ═══════════ 3 / 4 / 5. completing fields, official labels, codes ═══════════ */

    public function test_an_incomplete_form_says_what_is_missing_and_stays_in_preparation(): void
    {
        [, $formId] = $this->approveAndForm();

        // The method, the expected date and the reason code are all still blank.
        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/save", ['office_department' => 'Facilities', 'items' => []])
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Expected Date of Disposal')
                && str_contains($message, 'Method of Disposal Code'));

        $this->assertSame('draft', $this->form($formId)->status);

        // The screen lists them too, and the form can still not be printed.
        $this->actingAs($this->admin)->get("/admin/disposal/forms/{$formId}")
            ->assertOk()
            ->assertSee('Still missing before this form can be printed');

        $this->actingAs($this->admin)
            ->post('/admin/disposal/forms/print', ['ids' => [$formId]])
            ->assertRedirect('/admin/disposal?tab=forms')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Nothing was printed'));

        $this->assertSame('draft', $this->form($formId)->status);
    }

    public function test_completing_the_form_makes_it_ready_to_print(): void
    {
        [, $formId] = $this->approveAndForm();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId))
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'ready to print'));

        $form = $this->form($formId);
        $this->assertSame('ready', $form->status);
        $this->assertSame('S', $form->method_code, 'the letter the form prints for Scrap');
        $this->assertNotNull($form->ready_at);

        // Saving a complete form still disposes of nothing.
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(0, DB::table('disposals')->count());

        // An audit entry records the preparation.
        $this->assertNotNull(DB::table('audit_logs')
            ->where('action_description', 'like', '%Disposal form #' . $formId . '%')
            ->where('action_description', 'like', '%was saved%')
            ->first());
    }

    public function test_a_code_that_is_not_on_the_official_form_is_refused(): void
    {
        [, $formId] = $this->approveAndForm();

        $item = DB::table('disposal_form_items')->where('form_id', $formId)->first();

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId, [
                'items' => [$item->id => [
                    'tag_no' => 'AST-0001', 'quantity' => 1, 'description' => 'Laptop',
                    'condition_code' => 'F', 'reason_code' => 'X',
                ]],
            ]))
            ->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertSame(
            '"X" is not a code on the official disposal form.',
            $errors['items.' . $item->id . '.reason_code'][0] ?? null
        );

        $this->assertNull($this->form($formId)->method_key, 'a refused save changes nothing');
    }

    public function test_the_official_legend_is_preserved_and_the_duplicated_T_is_not_silently_changed(): void
    {
        // As printed on the official form. The reason list keeps its own order
        // and wording; the method list keeps the two T rows apart by key.
        $this->assertSame([
            'P' => 'Poor',
            'F' => 'Fair',
            'G' => 'Good',
            'B' => 'Beyond economic repair',
            'O' => 'Obsolete',
            'R' => 'Replaced by upgrade',
            'S' => 'Surplus to requirement',
            'T' => 'Theft',
        ], DisposalCodes::REASON_CODES);

        $this->assertSame([
            'donated'     => ['code' => 'D', 'label' => 'Donated'],
            'tender-sold' => ['code' => 'T', 'label' => 'Tender/sold'],
            'scrap'       => ['code' => 'S', 'label' => 'Scrap'],
            'trade-in'    => ['code' => 'T', 'label' => 'Trade-in'],
        ], DisposalCodes::METHOD_CODES);

        $this->assertSame('T', DisposalCodes::methodCode('tender-sold'));
        $this->assertSame('T', DisposalCodes::methodCode('trade-in'));
        $this->assertNotSame('tender-sold', 'trade-in');

        // Both rows are reachable, the stored record can tell them apart, and
        // what prints is still the official letter.
        [, $formId] = $this->approveAndForm();
        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId, ['method_key' => 'trade-in']))
            ->assertRedirect("/admin/disposal/forms/{$formId}");

        $form = $this->form($formId);
        $this->assertSame('trade-in', $form->method_key);
        $this->assertSame('T', $form->method_code);

        $sheet = $this->actingAs($this->admin)->get("/admin/disposal/forms/{$formId}/preview")->assertOk();

        // What the record says, and both official rows it could have chosen.
        $sheet->assertSee('T — Trade-in');
        $sheet->assertSee('Tender/sold');
        $sheet->assertSee('the code T appears twice, for Tender/sold and for Trade-in');
    }

    public function test_the_printed_form_keeps_the_official_labels_and_signature_sections(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        $sheet = $this->actingAs($this->admin)->get("/admin/disposal/forms/{$formId}/preview")->assertOk();

        // The field names of the official form.
        foreach ([
            'Office/Department', 'Tag No.', 'Qty', 'Description', 'Serial Number',
            'Date Acquired', 'Location', 'Custodian', 'Condition Code', 'Reason for Disposal Code',
            'Method of Disposal Code', 'Additional Fields for Sold Items',
            'Amount', 'OR No.', 'Gate Pass No.',
        ] as $label) {
            $sheet->assertSee($label);
        }

        // The signature sections, in the official wording, and blank.
        foreach ([
            'Asset Management Specialist',
            'Department Head',
            'AB Director, President/Vice President',
            'Property Asset Custodian',
        ] as $role) {
            $sheet->assertSee($role);
        }

        $sheet->assertSee('The signature blocks are intentionally blank');
        $sheet->assertSee('Signature over printed name', false);

        // The legend travels with the printed document.
        foreach (DisposalCodes::REASON_CODES as $label) {
            $sheet->assertSee($label);
        }
    }

    public function test_a_signed_form_is_never_implied_by_generating_printing_or_uploading(): void
    {
        [, $formId] = $this->approveAndForm();

        // Generated.
        $this->assertNull($this->form($formId)->signed_received_at);

        // Printed.
        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId));
        $this->actingAs($this->admin)->post('/admin/disposal/forms/print', ['ids' => [$formId]])->assertOk();

        $printed = $this->form($formId);
        $this->assertSame('printed', $printed->status);
        $this->assertNull($printed->signed_received_at);
        $this->assertSame(0, DB::table('disposals')->count());

        // The stage label itself says the signature does not exist yet.
        $this->assertSame('Printed — awaiting signed form', DisposalForms::label($printed->status));
    }

    /* ═══════════ 6 / 7 / 8. accumulate, batch print, printing is not disposal ═══════════ */

    public function test_forms_accumulate_in_the_queue_before_anything_is_printed(): void
    {
        [, $first]  = $this->approveAndForm();
        $secondAsset = $this->secondAsset();
        [, $second] = $this->approveAndForm([$secondAsset]);

        // Both wait for the office, and neither has been printed.
        $this->assertSame(2, DB::table('disposal_forms')->where('status', 'draft')->count());
        $this->assertSame(0, DB::table('disposal_forms')->whereNotNull('printed_at')->count());

        $queue = $this->actingAs($this->admin)->get('/admin/disposal?tab=forms')->assertOk();
        $queue->assertSee('Disposal form queue');
        $queue->assertSee('Awaiting preparation');
        $queue->assertSee('AST-0001');
        $queue->assertSee('AST-0002');

        $this->assertSame([0, 0], [
            DB::table('disposal_forms')->where('id', $first)->value('print_count'),
            DB::table('disposal_forms')->where('id', $second)->value('print_count'),
        ]);
    }

    public function test_several_ready_forms_print_in_one_batch(): void
    {
        [, $first]  = $this->approveAndForm();
        $secondAsset = $this->secondAsset();
        [, $second] = $this->approveAndForm([$secondAsset]);

        foreach ([$first, $second] as $formId) {
            $this->actingAs($this->admin)
                ->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId))
                ->assertRedirect("/admin/disposal/forms/{$formId}");
        }

        $sheet = $this->actingAs($this->admin)
            ->post('/admin/disposal/forms/print', ['ids' => [$first, $second]])
            ->assertOk();

        // One sheet per form, both in the same document.
        $sheet->assertSee('NU Trace disposal form #' . $first);
        $sheet->assertSee('NU Trace disposal form #' . $second);
        $this->assertSame(2, substr_count($sheet->getContent(), 'NU Trace disposal form #'));

        foreach ([$first, $second] as $formId) {
            $form = $this->form($formId);
            $this->assertSame('printed', $form->status);
            $this->assertSame(1, (int) $form->print_count);
            $this->assertNotNull($form->printed_at);
            $this->assertSame($this->admin->id, (int) $form->printed_by);
        }
    }

    public function test_printing_never_marks_an_asset_disposed_of(): void
    {
        [$requestId, $formId] = $this->approveAndForm();

        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId));
        $this->actingAs($this->admin)->post('/admin/disposal/forms/print', ['ids' => [$formId]])->assertOk();

        $asset = DB::table('assets')->where('id', $this->assetId)->first();
        $this->assertSame('Active', $asset->Lifecycle_Status);
        $this->assertNull($asset->inventory_removed_at);
        $this->assertSame(0, DB::table('disposals')->where('Request_id', $requestId)->count());

        // A printed form is not a signature either.
        $this->assertSame(0, DB::table('disposal_forms')->where('status', 'signed')->count());
    }

    public function test_a_form_that_is_not_ready_is_refused_in_a_batch_without_affecting_the_others(): void
    {
        [, $ready]   = $this->approveAndForm();
        $secondAsset = $this->secondAsset();
        [, $draft]   = $this->approveAndForm([$secondAsset]);

        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$ready}/save", $this->formPayload($ready));

        $this->actingAs($this->admin)
            ->post('/admin/disposal/forms/print', ['ids' => [$ready, $draft]])
            ->assertOk()
            ->assertSee('Form #' . $draft . ' is not ready to print yet');

        $this->assertSame('printed', $this->form($ready)->status);
        $this->assertSame('draft', $this->form($draft)->status);
    }

    /* ═══════════ 9. the signed form and its attachment ═══════════ */

    public function test_the_signed_form_cannot_be_recorded_before_the_form_is_printed(): void
    {
        [, $formId] = $this->approveAndForm();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/signed", ['received_date' => now()->toDateString()])
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Print the form first'));

        $this->assertNull($this->form($formId)->signed_received_at);
    }

    public function test_the_signed_form_is_recorded_with_its_scanned_copy(): void
    {
        [, $formId] = $this->approveAndForm();

        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId));
        $this->actingAs($this->admin)->post('/admin/disposal/forms/print', ['ids' => [$formId]])->assertOk();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/signed", [
                'received_date' => now()->toDateString(),
                'signed_notes'  => 'Signed by the Property Asset Custodian, received by Accountancy.',
                'signed_copy'   => UploadedFile::fake()->create('signed-disposal-form.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Signed form recorded as received'));

        $form = $this->form($formId);
        $this->assertSame('signed', $form->status);
        $this->assertNotNull($form->signed_received_at);
        $this->assertSame($this->admin->id, (int) $form->signed_received_by);
        $this->assertSame('signed-disposal-form.pdf', $form->signed_file_name);

        // The scan is on the private disk — not on the media disk, which is a
        // public bucket (asset photos and QR images are readable by URL there).
        $this->assertStringStartsWith(DisposalForms::UPLOAD_FOLDER . '/', $form->signed_file_path);
        Storage::disk('local')->assertExists($form->signed_file_path);
        $this->assertSame('local', DisposalForms::UPLOAD_DISK);
        $this->assertNotSame(Media::DISK, DisposalForms::UPLOAD_DISK);

        // The office can open it; the URL is not public and is guarded.
        $this->actingAs($this->admin)
            ->get("/admin/disposal/forms/{$formId}/signed-copy")
            ->assertOk()
            ->assertDownload('signed-disposal-form.pdf');

        // Recording the receipt still disposed of nothing.
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(0, DB::table('disposals')->count());
    }

    public function test_a_signed_copy_of_the_wrong_type_or_size_is_refused(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/signed", [
                'signed_copy' => UploadedFile::fake()->create('malicious.exe', 20, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('signed_copy');

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/signed", [
                'signed_copy' => UploadedFile::fake()->create('huge.pdf', DisposalForms::UPLOAD_MAX_KB + 500, 'application/pdf'),
            ])
            ->assertSessionHasErrors('signed_copy');

        $this->assertNull($this->form($formId)->signed_file_path);
    }

    public function test_a_signed_copy_cannot_be_opened_by_an_account_outside_the_office(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/signed", [
            'signed_copy' => UploadedFile::fake()->create('signed.pdf', 60, 'application/pdf'),
        ])->assertRedirect("/admin/disposal/forms/{$formId}");

        foreach ([$this->employee, $this->head] as $account) {
            $this->actingAs($account)
                ->get("/admin/disposal/forms/{$formId}/signed-copy")
                ->assertStatus(403);
        }

        // With no file stored, the office gets an honest 404 rather than an error.
        [, $noFile] = $this->approveAndForm([$this->secondAsset()]);
        $this->actingAs($this->admin)
            ->get("/admin/disposal/forms/{$noFile}/signed-copy")
            ->assertNotFound();
    }

    /* ═══════════ 10 / 11 / 12. finalization ═══════════ */

    public function test_the_disposal_cannot_be_finalized_before_the_signed_form_is_recorded(): void
    {
        [, $formId] = $this->approveAndForm();

        // Still in preparation.
        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/finalize", ['confirm' => '1'])
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'cannot be finalized yet'));

        // Printed but not signed.
        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId));
        $this->actingAs($this->admin)->post('/admin/disposal/forms/print', ['ids' => [$formId]])->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/admin/disposal/forms/{$formId}/finalize", ['confirm' => '1'])
            ->assertStatus(422);

        $this->assertSame('printed', $this->form($formId)->status);
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(0, DB::table('disposals')->count());
    }

    public function test_finalization_requires_an_explicit_confirmation(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        // No confirmation at all: the route refuses before anything is written.
        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/finalize", ['actual_disposal_date' => now()->toDateString()])
            ->assertSessionHasErrors('confirm');

        $this->assertSame('signed', $this->form($formId)->status);
        $this->assertSame(0, DB::table('disposals')->count());
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));

        // And the service refuses on its own too, for any caller.
        $result = DisposalForms::finalize($formId, [], $this->admin);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Confirm the disposal first', $result['message']);
    }

    public function test_finalizing_retires_the_assets_and_records_the_disposal(): void
    {
        [$requestId, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        // Every disposal now waits out the three-year period measured from the
        // approval. Nothing before this point may retire the asset, so the test
        // steps over the wait before asking for the one step that may.
        $this->travel(3)->years();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/finalize", [
                'actual_disposal_date' => now()->toDateString(),
                'confirm'              => '1',
            ])
            ->assertRedirect('/admin/disposal?tab=forms')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Disposal finalized')
                && str_contains($message, 'AST-0001'));

        // The asset is retired, and never deleted.
        $asset = DB::table('assets')->where('id', $this->assetId)->first();
        $this->assertNotNull($asset);
        $this->assertSame(Disposals::DISPOSED_STATUS, $asset->Lifecycle_Status);

        // The disposal record the rest of the system already understands.
        $disposal = DB::table('disposals')->where('Request_id', $requestId)->first();
        $this->assertNotNull($disposal);
        $this->assertSame($this->assetId, (int) $disposal->Asset_id);
        $this->assertSame($this->admin->email, $disposal->Approve_by);
        $this->assertSame('Beyond Repair', $disposal->disposal_reason, 'code B records as the stored reason');
        $this->assertSame(now()->toDateString(), (string) $disposal->disposal_date);

        // The form records its own end state.
        $form = $this->form($formId);
        $this->assertSame('finalized', $form->status);
        $this->assertNotNull($form->finalized_at);
        $this->assertSame($this->admin->id, (int) $form->finalized_by);
        $this->assertSame(now()->toDateString(), (string) $form->actual_disposal_date);

        // The finalization's own audit entry, matched on what only it says.
        // A looser match is satisfied by the wrong row: the approval logs
        // action_type DISPOSAL, for this asset, on this request, and its text
        // names the finalization too — which is how an entry that was never
        // written (and an action_type holding the asset id) went unnoticed.
        $entries = DB::table('audit_logs')->where('notes', 'like', 'Disposal finalized%')->get();

        $this->assertCount(1, $entries, 'the finalization must leave one entry for a one-asset form');

        $audit = $entries->first();
        $this->assertSame('DISPOSAL', $audit->action_type, 'the entry has to be an action the log accepts');
        $this->assertSame($requestId, (int) $audit->request_id);
        $this->assertSame($this->assetId, (int) $audit->asset_id, 'the entry names the asset it retired');
        $this->assertSame($this->admin->id, (int) $audit->user_id);
        $this->assertStringContainsString('AST-0001', $audit->action_description);
        $this->assertStringContainsString('was marked disposed of', $audit->action_description);
        $this->assertStringContainsString('reason code B', $audit->notes);

        $notification = DB::table('notifications')
            ->where('user_id', $this->employee->id)
            ->where('title', 'Disposal Completed')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('finalized', $notification->message);

        // It shows up where the office expects the finished record.
        $this->flushSession();
        $this->actingAs($this->admin)->get('/admin/disposal')->assertOk()->assertSee('AST-0001');
    }

    public function test_a_multi_asset_disposal_finalizes_every_asset_on_the_form(): void
    {
        $secondAsset = $this->secondAsset();
        [$requestId, $formId] = $this->approveAndForm([$this->assetId, $secondAsset]);

        $this->takeToSigned($formId);
        $this->travel(3)->years();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/finalize", ['actual_disposal_date' => now()->toDateString(), 'confirm' => '1'])
            ->assertRedirect('/admin/disposal?tab=forms');

        foreach ([$this->assetId, $secondAsset] as $assetId) {
            $this->assertSame(
                Disposals::DISPOSED_STATUS,
                DB::table('assets')->where('id', $assetId)->value('Lifecycle_Status'),
                'every asset on the form has to be retired'
            );
        }

        $this->assertSame(2, DB::table('disposals')->where('Request_id', $requestId)->count());

        // One entry per asset, each naming its own — and every one of them an
        // action the log's constraint accepts.
        $entries = DB::table('audit_logs')->where('notes', 'like', 'Disposal finalized%')->get();

        $this->assertCount(2, $entries);
        $this->assertSame([$this->assetId, $secondAsset], $entries->pluck('asset_id')->map(fn ($id) => (int) $id)->sort()->values()->all());
        $this->assertSame(['DISPOSAL'], $entries->pluck('action_type')->unique()->values()->all());
    }

    public function test_finalizing_twice_changes_nothing_and_creates_nothing_twice(): void
    {
        [$requestId, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);
        $this->travel(3)->years();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/finalize", ['actual_disposal_date' => now()->toDateString(), 'confirm' => '1'])
            ->assertRedirect('/admin/disposal?tab=forms');

        $disposalIds = DB::table('disposals')->where('Request_id', $requestId)->pluck('Disposal_ID')->all();
        $finalizedAt = $this->form($formId)->finalized_at;
        $noticeCount = DB::table('notifications')->where('user_id', $this->employee->id)->count();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/forms/{$formId}/finalize", ['actual_disposal_date' => now()->toDateString(), 'confirm' => '1'])
            ->assertRedirect("/admin/disposal/forms/{$formId}")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'already been finalized'));

        $this->assertSame($disposalIds, DB::table('disposals')->where('Request_id', $requestId)->pluck('Disposal_ID')->all());
        $this->assertSame($finalizedAt, $this->form($formId)->finalized_at);
        $this->assertSame($noticeCount, DB::table('notifications')->where('user_id', $this->employee->id)->count());

        // A finalized form is part of the record: it cannot be edited either.
        $result = DisposalForms::save($formId, $this->formPayload($formId), $this->admin);
        $this->assertFalse($result['ok']);
    }

    public function test_reprinting_is_counted_but_never_disposes_of_anything(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        $this->actingAs($this->admin)->post('/admin/disposal/forms/print', ['ids' => [$formId]])->assertOk();

        $form = $this->form($formId);
        $this->assertSame(2, (int) $form->print_count);
        $this->assertSame('signed', $form->status, 'reprinting a signed form does not undo the signature');
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
    }

    public function test_a_finalized_form_can_no_longer_be_printed(): void
    {
        [, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);
        $this->travel(3)->years();
        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/finalize", [
            'actual_disposal_date' => now()->toDateString(), 'confirm' => '1',
        ])->assertRedirect('/admin/disposal?tab=forms');

        $before = (int) $this->form($formId)->print_count;

        $this->actingAs($this->admin)
            ->post('/admin/disposal/forms/print', ['ids' => [$formId]])
            ->assertRedirect('/admin/disposal?tab=forms')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'is finalized'));

        $this->assertSame($before, (int) $this->form($formId)->print_count);
    }

    /* ═══════════ 13. permissions stay where they were ═══════════ */

    public function test_only_the_asset_management_office_touches_a_disposal_form(): void
    {
        [$requestId, $formId] = $this->approveAndForm();
        $this->takeToSigned($formId);

        foreach ([$this->employee, $this->head] as $account) {
            $this->actingAs($account)->get("/admin/disposal/forms/{$formId}")->assertStatus(403);
            $this->actingAs($account)->get("/admin/disposal/forms/{$formId}/preview")->assertStatus(403);
            $this->actingAs($account)->get('/admin/disposal?tab=forms')->assertStatus(403);
            $this->actingAs($account)->post("/admin/disposal/forms/{$formId}/save", $this->formPayload($formId))->assertStatus(403);
            $this->actingAs($account)->post('/admin/disposal/forms/print', ['ids' => [$formId]])->assertStatus(403);
            $this->actingAs($account)->post("/admin/disposal/forms/{$formId}/signed", [])->assertStatus(403);
            $this->actingAs($account)
                ->post("/admin/disposal/forms/{$formId}/finalize", ['confirm' => '1'])
                ->assertStatus(403);
            $this->actingAs($account)->post("/admin/disposal/requests/{$requestId}/form")->assertStatus(403);
        }

        // Nothing moved.
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame('signed', $this->form($formId)->status);
        $this->assertSame(0, DB::table('disposals')->count());
    }

    public function test_a_repair_request_keeps_its_own_approval_behaviour(): void
    {
        // The disposal change must not have moved anything for other request
        // types: approving still creates the repair record and sets the status.
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->employee->id, 'asset_id' => null, 'request_type' => 'Repair',
            'status' => 'Pending', 'Note' => 'Keyboard replaced once already.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('request_items')->insert([
            'request_id' => $requestId, 'asset_id' => $this->assetId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->postJson("/admin/requests/{$requestId}/approve")->assertOk();

        $this->assertSame(1, DB::table('repairs')->where('Request_id', $requestId)->count());
        $this->assertSame('For Repair', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(0, DB::table('disposal_forms')->count());
    }

    public function test_the_queue_page_is_reachable_from_the_disposal_page_tab(): void
    {
        $records = $this->actingAs($this->admin)->get('/admin/disposal')->assertOk();
        $records->assertSee('Disposal Records');
        $records->assertSee('Official Disposal Forms');

        $queue = $this->actingAs($this->admin)->get('/admin/disposal?tab=forms')->assertOk();
        $queue->assertSee('Disposal form queue');
        $queue->assertSee('Official Disposal Forms');
    }

    public function test_the_route_and_the_tables_the_workflow_needs_are_registered(): void
    {
        $uris = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->all();

        foreach ([
            'admin/disposal/forms/{id}',
            'admin/disposal/forms/{id}/save',
            'admin/disposal/forms/{id}/preview',
            'admin/disposal/forms/{id}/signed',
            'admin/disposal/forms/{id}/signed-copy',
            'admin/disposal/forms/{id}/finalize',
            'admin/disposal/forms/print',
            'admin/disposal/requests/{requestId}/form',
        ] as $uri) {
            $this->assertContains($uri, $uris);
        }

        $this->assertTrue(DisposalForms::tablesReady());
        $this->assertSame('Disposal', Disposals::DISPOSED_STATUS);
    }

    /* ═══════════ requests approved before the forms existed ═══════════ */

    public function test_an_approved_request_with_no_form_is_offered_and_can_be_prepared(): void
    {
        // The live database holds requests that were approved while approval
        // still wrote the disposal record itself — there is no form behind
        // them. They must not be stranded: the queue lists them, preparing one
        // is the same idempotent generation the approval does, and preparing a
        // form retires nothing.
        $requestId = $this->submitDisposalRequest([], 'The unit is damaged beyond repair.', 'Approved');

        $queue = $this->actingAs($this->admin)->get('/admin/disposal?tab=forms')->assertOk();
        $queue->assertSee('Approved disposal requests with no form yet');
        $queue->assertSee('Prepare form');
        $queue->assertSee('#' . $requestId);
        $queue->assertSee('it does not dispose of anything by itself');

        $prepared = $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/form");

        $formId = (int) DB::table('disposal_forms')->where('request_id', $requestId)->value('id');
        $this->assertGreaterThan(0, $formId, 'preparing must generate the form');
        $prepared->assertRedirect('/admin/disposal/forms/' . $formId);

        $form = $this->form($formId);
        $this->assertSame('draft', $form->status);
        $this->assertSame('Facilities', $form->office_department);
        $this->assertSame($this->admin->id, (int) $form->prepared_by);

        // Prefilled from the records, exactly as an approval-generated form is.
        $item = DB::table('disposal_form_items')->where('form_id', $formId)->first();
        $this->assertSame($this->assetId, (int) $item->asset_id);
        $this->assertSame('AST-0001', $item->tag_no);
        $this->assertSame('B', $item->reason_code);

        // Pressing it again reopens the same form rather than making a second.
        $this->actingAs($this->admin)
            ->post("/admin/disposal/requests/{$requestId}/form")
            ->assertRedirect('/admin/disposal/forms/' . $formId);

        $this->assertSame(1, DB::table('disposal_forms')->count());
        $this->assertSame(1, DB::table('disposal_form_items')->count());

        // It is in the queue now, so the "no form yet" list no longer offers it.
        $this->actingAs($this->admin)
            ->get('/admin/disposal?tab=forms')
            ->assertOk()
            ->assertDontSee('Approved disposal requests with no form yet');

        // And nothing was disposed of by preparing it.
        $this->assertSame('Active', DB::table('assets')->where('id', $this->assetId)->value('Lifecycle_Status'));
        $this->assertSame(0, DB::table('disposals')->count());
    }

    /* ═══════════ the driver the office actually runs ═══════════ */

    /**
     * The columns this schema spells with a capital letter.
     *
     * They were created as quoted, mixed-case names, so PostgreSQL folds an
     * unquoted reference to lowercase and refuses the statement (42703) — while
     * SQLite matches names case-insensitively and passes. That difference put a
     * 500 on production: `DB::raw('requests.Note ...')` spelled the column the
     * way the table does, SQLite accepted it, PostgreSQL read it as
     * `requests.note` and threw "column requests.note does not exist".
     *
     * @var array<int, string>
     */
    private const CASE_SENSITIVE_COLUMNS = [
        'Note', 'Full_Name', 'Employee_number', 'Request_id', 'Disposal_ID',
        'Approve_by', 'Asset_code', 'Asset_name', 'Category', 'Condition',
        'Is_Current', 'Assign_date', 'Department_id', 'Create_at', 'Update_at',
    ];

    public function test_no_statement_the_chain_runs_leaves_a_case_sensitive_column_unquoted(): void
    {
        // SQLite cannot tell a quoted identifier from an unquoted one, so this
        // test reads the statements themselves rather than their results: it
        // runs the whole chain with the query log on and checks that every
        // case-sensitive column reached the driver quoted. It therefore fails
        // here, on either driver, for a mistake that would only throw in
        // production.
        DB::enableQueryLog();

        [, $formId] = $this->approveAndForm();

        // The queue, the preparation screen, the reprint, the saved form, the
        // signed receipt and the finalization — every statement the office's
        // own steps run.
        $this->actingAs($this->admin)->get('/admin/disposal?tab=forms')->assertOk();
        $this->actingAs($this->admin)->get("/admin/disposal/forms/{$formId}")->assertOk();

        $this->takeToSigned($formId);
        $this->travel(3)->years();

        $this->actingAs($this->admin)->get("/admin/disposal/forms/{$formId}/preview")->assertOk();

        $this->actingAs($this->admin)->post("/admin/disposal/forms/{$formId}/finalize", [
            'confirm'              => '1',
            'actual_disposal_date' => now()->toDateString(),
        ])->assertRedirect('/admin/disposal?tab=forms');

        $this->actingAs($this->admin)->get('/admin/disposal')->assertOk();

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty($log, 'the chain must have run the statements this test reads');

        $offenders = [];

        foreach ($log as $entry) {
            $sql = (string) ($entry['query'] ?? '');

            // Drop every quoted identifier first, so `"requests"."Note"` is left
            // as `"x"."x"` and cannot be mistaken for an unquoted reference.
            $bare = (string) preg_replace('/"[^"]*"/', '"x"', $sql);

            foreach (self::CASE_SENSITIVE_COLUMNS as $column) {
                if (preg_match('/\b' . preg_quote($column, '/') . '\b/', $bare) === 1) {
                    $offenders[$column] = $sql;
                }
            }
        }

        $this->assertSame([], $offenders,
            'These statements name a column in a case the database will not fold: '
            . implode(', ', array_keys($offenders))
            . '. Quote it (or let the query builder quote it) or PostgreSQL refuses the query. First: '
            . (reset($offenders) ?: ''));
    }
}
