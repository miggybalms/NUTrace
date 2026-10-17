<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DisposalDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Supporting documents on a disposal request.
 *
 * Files are private, attached and read only by the Asset Management Office, and
 * preserved once the disposal is part of the historical record.
 */
class DisposalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
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
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'amo@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'juan@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Fair', 'Lifecycle_Status' => 'Active',
            'serial_Number' => 'SN-9F31', 'asset_location' => 'Registrar Office',
            'accusion_date' => now()->subYears(4)->toDateString(),
            'purchase_Price' => 42000.00,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    private function request(string $status = 'Pending', string $type = 'Disposal'): int
    {
        $requestId = (int) DB::table('requests')->insertGetId([
            'user_id'      => $this->employee->id,
            'asset_id'     => null,
            'request_type' => $type,
            'status'       => $status,
            'Note'         => 'The unit is damaged beyond repair.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('request_items')->insert([
            'request_id' => $requestId,
            'asset_id'   => $this->assetId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $requestId;
    }

    /** An approved disposal request and the form approval generated. */
    private function approved(): array
    {
        $requestId = $this->request();

        $this->actingAs($this->admin)
            ->postJson("/admin/requests/{$requestId}/approve")
            ->assertOk();

        $formId = (int) DB::table('disposal_forms')->where('request_id', $requestId)->value('id');

        return [$requestId, $formId];
    }

    private function pdf(string $name = 'inspection.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    /* ═══════════════ 1. attaching and reading ═══════════════ */

    public function test_the_asset_management_office_can_attach_a_supporting_document(): void
    {
        [$requestId, $formId] = $this->approved();

        $response = $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'title'    => 'Property inspection report',
            'asset_id' => $this->assetId,
            'form_id'  => $formId,
        ]);

        $response->assertRedirect("/admin/disposal/forms/{$formId}");

        $document = DB::table('disposal_documents')->where('request_id', $requestId)->first();

        $this->assertNotNull($document);
        $this->assertSame('Property inspection report', $document->title);
        $this->assertSame('inspection.pdf', $document->file_name);
        $this->assertSame($this->assetId, (int) $document->asset_id);
        $this->assertSame($formId, (int) $document->form_id);
        $this->assertSame($this->admin->id, (int) $document->uploaded_by);

        Storage::disk('local')->assertExists($document->file_path);

        // Not on the public media disk, and no public URL was recorded.
        $this->assertStringStartsWith(DisposalDocuments::FOLDER . '/', $document->file_path);
        $this->assertDatabaseHas('audit_logs', ['request_id' => $requestId, 'action_type' => 'UPDATE']);
    }

    public function test_the_document_appears_on_the_form_page_for_the_office(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'title'    => 'Appraisal sheet',
            'form_id'  => $formId,
        ])->assertRedirect("/admin/disposal/forms/{$formId}");

        $this->actingAs($this->admin)
            ->get("/admin/disposal/forms/{$formId}")
            ->assertOk()
            ->assertSee('Supporting documents')
            ->assertSee('Appraisal sheet');
    }

    public function test_an_authorized_download_streams_the_stored_file(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'title'    => 'Inspection report',
            'form_id'  => $formId,
        ]);

        $document = DB::table('disposal_documents')->first();

        $this->actingAs($this->admin)
            ->get("/admin/disposal/documents/{$document->id}")
            ->assertOk()
            ->assertDownload('inspection.pdf');
    }

    /* ═══════════════ 2. access control ═══════════════ */

    public function test_an_employee_cannot_upload_or_open_a_document(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'form_id'  => $formId,
        ]);

        $document = DB::table('disposal_documents')->first();

        $this->actingAs($this->employee)
            ->post("/admin/disposal/requests/{$requestId}/documents", ['document' => $this->pdf('other.pdf')])
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->get("/admin/disposal/documents/{$document->id}")
            ->assertForbidden();

        $this->assertSame(1, DB::table('disposal_documents')->count());
    }

    public function test_a_guest_cannot_open_a_document(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'form_id'  => $formId,
        ]);

        $document = DB::table('disposal_documents')->first();

        Auth::logout();

        $this->get("/admin/disposal/documents/{$document->id}")
            ->assertRedirect('/');
    }

    /* ═══════════════ 3. validation and eligibility ═══════════════ */

    public function test_a_pending_request_cannot_receive_documents(): void
    {
        $requestId = $this->request();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
        ])->assertRedirect('/admin/disposal?tab=forms');

        $this->assertSame(0, DB::table('disposal_documents')->count());
    }

    public function test_a_non_disposal_request_cannot_receive_documents(): void
    {
        $requestId = $this->request('Approved', 'Repair');

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
        ])->assertRedirect('/admin/disposal?tab=forms');

        $this->assertSame(0, DB::table('disposal_documents')->count());
    }

    public function test_the_wrong_file_type_is_refused(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
            'form_id'  => $formId,
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, DB::table('disposal_documents')->count());
    }

    public function test_an_oversized_document_is_refused(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf('huge.pdf', DisposalDocuments::MAX_KB + 1024),
            'form_id'  => $formId,
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, DB::table('disposal_documents')->count());
    }

    public function test_an_asset_outside_the_request_cannot_be_named(): void
    {
        [$requestId, $formId] = $this->approved();

        $otherAsset = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-9999', 'Asset_name' => 'Other printer',
            'Category' => 'Info and Equipment', 'Condition' => 'Good', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'asset_id' => $otherAsset,
            'form_id'  => $formId,
        ])->assertRedirect("/admin/disposal/forms/{$formId}");

        $this->assertSame(0, DB::table('disposal_documents')->count());
    }

    /* ═══════════════ 4. the historical record ═══════════════ */

    public function test_documents_cannot_be_removed_once_the_disposal_is_recorded(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'form_id'  => $formId,
        ]);

        $document = DB::table('disposal_documents')->first();

        // A recorded disposal is the point at which the documentation becomes
        // history.
        DB::table('disposals')->insert([
            'Request_id'      => $requestId,
            'Asset_id'        => $this->assetId,
            'Approve_by'      => 'amo@nu-lipa.edu.ph',
            'Description'     => 'Disposal request approved',
            'disposal_reason' => 'Beyond Repair',
            'disposal_date'   => now()->toDateString(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/disposal/documents/{$document->id}/delete", ['form_id' => $formId])
            ->assertRedirect("/admin/disposal/forms/{$formId}");

        $this->assertDatabaseHas('disposal_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_a_document_can_be_removed_while_the_disposal_is_being_processed(): void
    {
        [$requestId, $formId] = $this->approved();

        $this->actingAs($this->admin)->post("/admin/disposal/requests/{$requestId}/documents", [
            'document' => $this->pdf(),
            'form_id'  => $formId,
        ]);

        $document = DB::table('disposal_documents')->first();

        $this->actingAs($this->admin)
            ->post("/admin/disposal/documents/{$document->id}/delete", ['form_id' => $formId])
            ->assertRedirect("/admin/disposal/forms/{$formId}");

        $this->assertDatabaseMissing('disposal_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);
        $this->assertDatabaseHas('audit_logs', ['request_id' => $requestId, 'action_type' => 'UPDATE']);
    }
}
