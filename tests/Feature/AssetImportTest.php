<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Support\Import\AssetImport;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/**
 * Importing the office's existing Fixed Asset Registry.
 *
 * The rules this file locks down, in the order a line is resolved:
 *
 *  1. PROPERTY ID NO. decides identity. A property number already in NU Trace is
 *     skipped, never overwritten; the same number twice in one file blocks both
 *     lines.
 *  2. A line without a property number is held until the office says otherwise.
 *  3. Departments and asset types are matched to records that already exist,
 *     and are refused until a human chooses when they do not match.
 *  4. An accountable person is only assigned through a real, active account.
 *     An employee without one is imported unassigned, with the name preserved.
 *  5. Asset codes are the office's own, checked against NU Trace and the file.
 *  6. Re-importing the same file creates nothing and re-mints no sticker.
 */
class AssetImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rio;

    protected function setUp(): void
    {
        parent::setUp();

        // The sticker image is fetched from an outside service; the import must
        // work whether or not the network does, so it is stubbed here.
        Http::fake(['api.qrserver.com/*' => Http::response('PNG-BYTES', 200)]);

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Health Services Office', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'College of Nursing', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Rio Atienza', 'Department_id' => 1, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Maria Santos', 'Department_id' => 2, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'Employee_number' => 'EMP-3', 'Full_Name' => 'Juan Cruz', 'Department_id' => 1, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Juan Cruz', 'Department_id' => 2, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            // The administrator has their own masterlist record: one employee
            // holding two active accounts is deliberately ambiguous to the
            // importer, so the office itself must not be one of those cases.
            ['id' => 5, 'Employee_number' => 'EMP-5', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = $this->makeUser(5, 1, 'alex@nu-lipa.edu.ph', 'Admin');
        $this->rio = $this->makeUser(1, 1, 'rio@nu-lipa.edu.ph', 'Employee');
        $this->makeUser(3, 1, 'juan.one@nu-lipa.edu.ph', 'Employee');
        $this->makeUser(4, 2, 'juan.two@nu-lipa.edu.ph', 'Employee');
    }

    /* ═══════════════════════════ upload & mapping ═══════════════════════════ */

    public function test_the_file_is_read_as_headers_first_and_mapped_without_guessing(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/assets/import', [
            'file' => $this->csv($this->rows()),
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $mapping = $response->json('batch.mapping');

        $this->assertSame(2, $mapping['property_id_no']);
        $this->assertSame(0, $mapping['inventory_id_no'], 'ID NO. must not be mapped onto PROPERTY ID NO.');
        $this->assertSame(3, $mapping['name']);
        $this->assertSame(9, $mapping['accountable']);
        $this->assertSame(10, $mapping['asset_code']);
    }

    public function test_the_template_can_be_downloaded(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/assets/import/template');

        $response->assertOk();
        $this->assertStringContainsString('PROPERTY ID NO', $response->streamedContent());
    }

    public function test_the_import_pages_are_for_the_asset_office_only(): void
    {
        $this->get('/admin/assets/import')->assertRedirect('/');

        $employee = $this->makeUser(2, 2, 'employee@nu-lipa.edu.ph', 'Employee');

        $this->actingAs($employee)->get('/admin/assets/import')->assertForbidden();
        $this->actingAs($employee)->postJson('/admin/assets/import', ['file' => $this->csv($this->rows())])->assertForbidden();
    }

    /* ═══════════════════════════ identity rules ═══════════════════════════ */

    public function test_a_valid_line_imports_with_its_property_number_department_holder_and_sticker(): void
    {
        $batch = $this->check($this->rows());

        $this->assertSame(AssetImport::READY, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)
            ->postJson("/admin/assets/import/{$batch}/commit")
            ->assertOk()
            ->assertJsonPath('done', true);

        $asset = Asset::where('Asset_code', 'AST-0001')->firstOrFail();

        $this->assertSame('30014399', $asset->property_id_no);
        $this->assertSame('001', $asset->inventory_id_no);
        $this->assertSame('Donation', $asset->po_no);
        $this->assertSame('Mobile Pedestal', $asset->Asset_name);
        $this->assertSame('Furnitures and Fixtures', $asset->Category);
        $this->assertSame('Existing', $asset->Condition);
        $this->assertSame('2022-01-19', $asset->accusion_date->format('Y-m-d'));
        $this->assertSame(1, (int) $asset->department_id);
        $this->assertSame($this->rio->id, (int) $asset->user_id);
        $this->assertSame('Atienza, Rio', $asset->accountable_name, 'The office\'s own spelling is preserved.');
        $this->assertSame('Complete', $asset->completion_status);

        // The assignment went through the existing accountability table, not
        // through a second kind of assignment invented for the importer.
        $this->assertDatabaseHas('asset_accountability', [
            'asset_id' => $asset->id,
            'user_id'  => $this->rio->id,
            'Is_Current' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'asset_id'    => $asset->id,
            'action_type' => 'CREATE',
        ]);

        $this->assertDatabaseHas('asset_import_rows', [
            'batch_id' => $batch,
            'line'     => 2,
            'status'   => AssetImport::IMPORTED,
            'asset_id' => $asset->id,
        ]);
    }

    public function test_a_property_number_already_in_nu_trace_is_skipped_not_overwritten(): void
    {
        $existing = $this->makeAsset('AST-0100', 'Old record', ['property_id_no' => '30014399']);

        $batch = $this->check([$this->sourceRow(1)]);

        $this->assertSame(AssetImport::EXISTS, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)
            ->postJson("/admin/assets/import/{$batch}/commit")
            ->assertOk();

        $this->assertSame(1, Asset::count());
        $this->assertSame('Old record', $existing->fresh()->Asset_name);
        $this->assertSame('AST-0100', $existing->fresh()->Asset_code);
    }

    public function test_the_same_property_number_twice_in_one_file_blocks_both_lines(): void
    {
        $rows = [
            $this->sourceRow(1, ['property' => '30014400', 'description' => 'Chair A', 'code' => 'AST-1001']),
            $this->sourceRow(2, ['property' => '30014400', 'description' => 'Chair B', 'code' => 'AST-1002']),
        ];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 2)['status']);
        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 3)['status']);
        $this->assertStringContainsString('more than once', implode(' ', array_column($this->row($batch, 2)['issues'], 'text')));

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $this->assertSame(0, Asset::count());
    }

    public function test_a_line_without_a_property_number_is_held_until_the_office_opts_in(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '', 'code' => 'AST-2001'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/decisions", [
            'allow_blank_property' => true,
        ])->assertOk();

        $this->assertSame(AssetImport::REVIEW, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-2001')->firstOrFail();
        $this->assertNull($asset->property_id_no);
        $this->assertSame('Needs completion', $asset->completion_status);
    }

    public function test_an_id_number_that_belongs_to_another_asset_is_only_a_warning(): void
    {
        $this->makeAsset('AST-3000', 'Known chair', ['property_id_no' => '99999999', 'inventory_id_no' => '001']);

        $batch = $this->check($this->rows());

        $row = $this->row($batch, 2);
        $text = implode(' ', array_column($row['issues'], 'text'));

        $this->assertSame(AssetImport::REVIEW, $row['status']);
        $this->assertStringContainsString('ID NO. 001 is already used by AST-3000', $text);
    }

    /* ═══════════════════════════ departments & categories ═══════════════════════════ */

    public function test_an_unmatched_department_blocks_the_line_until_it_is_matched(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30015000', 'department' => 'Unknown Office', 'code' => 'AST-4001'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/decisions", [
            'departments' => ['Unknown Office' => 2],
        ])->assertOk();

        $this->assertSame(AssetImport::READY, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $this->assertSame(2, (int) Asset::where('Asset_code', 'AST-4001')->value('department_id'));
    }

    public function test_a_department_written_with_different_capitalisation_is_reused_not_created(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30016000', 'department' => '  health services office ', 'code' => 'AST-4002'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::READY, $this->row($batch, 2)['status']);
        $this->assertSame(1, (int) $this->row($batch, 2)['department_id']);
        $this->assertSame(2, DB::table('departments')->count());
    }

    public function test_a_type_of_asset_nu_trace_does_not_know_must_be_mapped(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30017000', 'category' => 'Gadgets', 'code' => 'AST-4003'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/decisions", [
            'categories' => ['Gadgets' => 'Info and Equipment'],
        ])->assertOk();

        $this->assertSame(AssetImport::READY, $this->row($batch, 2)['status']);
    }

    public function test_the_office_spelling_of_furniture_is_read_as_the_schema_category(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30018000', 'category' => 'FURNITURES AND FITTINGS', 'code' => 'AST-4004'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::READY, $this->row($batch, 2)['status']);
        $this->assertSame('Furnitures and Fixtures', $this->row($batch, 2)['category']);
    }

    /* ═══════════════════════════ accountable people ═══════════════════════════ */

    public function test_an_employee_without_an_account_is_imported_unassigned_with_the_name_kept(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30019000', 'person' => 'Santos, Maria', 'code' => 'AST-5001'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::REVIEW, $this->row($batch, 2)['status']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-5001')->firstOrFail();

        $this->assertNull($asset->user_id);
        $this->assertSame('Santos, Maria', $asset->accountable_name);
        $this->assertSame('Needs completion', $asset->completion_status);
        $this->assertDatabaseMissing('asset_accountability', ['asset_id' => $asset->id]);
        $this->assertSame(4, DB::table('users')->count(), 'No account may be invented for an importing name.');
    }

    public function test_a_name_nobody_on_the_masterlist_matches_is_a_warning_and_not_an_assignment(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30019100', 'person' => 'Unknown Person', 'code' => 'AST-5002'])];

        $batch = $this->check($rows);
        $row = $this->row($batch, 2);

        $this->assertSame(AssetImport::REVIEW, $row['status']);
        $this->assertSame(AssetImport::PERSON_NO_EMPLOYEE, $row['accountable_status']);
        $this->assertNull($row['accountable_user_id']);
    }

    public function test_two_employees_with_the_same_name_need_a_decision(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30019200', 'person' => 'Cruz, Juan', 'code' => 'AST-5003'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 2)['status']);
        $this->assertSame(AssetImport::PERSON_AMBIGUOUS, $this->row($batch, 2)['accountable_status']);

        $juanTwo = DB::table('users')->where('email', 'juan.two@nu-lipa.edu.ph')->value('id');

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/decisions", [
            'people' => ['Cruz, Juan' => $juanTwo],
        ])->assertOk();

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $this->assertSame((int) $juanTwo, (int) Asset::where('Asset_code', 'AST-5003')->value('user_id'));
    }

    public function test_the_office_may_explicitly_leave_an_ambiguous_name_unassigned(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30019300', 'person' => 'Cruz, Juan', 'code' => 'AST-5004'])];

        $batch = $this->check($rows);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/decisions", [
            'people_unassigned' => ['Cruz, Juan'],
        ])->assertOk();

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-5004')->firstOrFail();

        $this->assertNull($asset->user_id);
        $this->assertSame('Cruz, Juan', $asset->accountable_name);
    }

    /* ═══════════════════════════ custom codes ═══════════════════════════ */

    public function test_a_missing_asset_code_blocks_the_line_and_can_be_filled_in(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30020000', 'code' => ''])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 2)['status']);
        $this->assertStringContainsString('No asset code yet', implode(' ', array_column($this->row($batch, 2)['issues'], 'text')));

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/codes", [
            'codes' => [2 => 'nu-2026-0001'],
        ])->assertOk();

        $row = $this->row($batch, 2);
        $this->assertSame(AssetImport::READY, $row['status']);
        $this->assertSame('NU-2026-0001', $row['asset_code']);
    }

    public function test_a_code_already_used_in_nu_trace_blocks_the_line(): void
    {
        $this->makeAsset('AST-0001', 'Taken code', ['property_id_no' => '11111111']);

        $batch = $this->check($this->rows());

        $this->assertSame(AssetImport::BLOCKED, $this->row($batch, 2)['status']);
        $this->assertStringContainsString('already used by', implode(' ', array_column($this->row($batch, 2)['issues'], 'text')));
    }

    public function test_codes_can_be_proposed_from_a_pattern_without_imposing_a_format(): void
    {
        $rows = [
            $this->sourceRow(1, ['property' => '30021000', 'code' => '']),
            $this->sourceRow(2, ['property' => '30021001', 'code' => '']),
        ];

        $batch = $this->check($rows);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/propose", [
            'pattern' => 'NU-{property_id}',
        ])->assertOk();

        $this->assertSame('NU-30021000', $this->row($batch, 2)['asset_code']);
        $this->assertSame(AssetImport::READY, $this->row($batch, 2)['status']);
    }

    /* ═══════════════════════════ re-import ═══════════════════════════ */

    public function test_uploading_the_same_file_again_creates_no_duplicate_and_no_new_sticker(): void
    {
        $rows = $this->rows();

        $first = $this->check($rows);
        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$first}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-0001')->firstOrFail();
        $qrBefore = $asset->qr_code_path;

        // A new batch: the first one is completed, so the fingerprint does not
        // resume it — the property number is what protects the database.
        $second = $this->check($rows);

        $this->assertSame(AssetImport::EXISTS, $this->row($second, 2)['status']);

        $this->actingAs($this->admin)
            ->postJson("/admin/assets/import/{$second}/commit")
            ->assertOk()
            ->assertJsonPath('summary.imported', 0);

        $this->assertSame(3, Asset::count(), 'The three checked lines import once, not twice.');
        $this->assertSame($qrBefore, $asset->fresh()->qr_code_path);
    }

    public function test_a_failed_line_can_be_reopened_and_retried_without_duplicating_the_asset(): void
    {
        $batch = $this->check($this->rows());
        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-0001')->firstOrFail();

        // Pretend the row's bookkeeping failed after the asset was written.
        DB::table('asset_import_rows')->where('batch_id', $batch)->where('line', 2)->update([
            'status' => AssetImport::FAILED,
            'asset_id' => null,
        ]);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/retry")->assertOk();
        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $this->assertSame(1, Asset::where('property_id_no', '30014399')->count());
        $this->assertSame('AST-0001', $asset->fresh()->Asset_code);
    }

    /* ═══════════════════════════ the reader itself ═══════════════════════════ */

    public function test_an_xlsx_is_read_with_its_dates_and_text_identifiers_intact(): void
    {
        $path = $this->xlsx();

        $reader = SpreadsheetReader::open($path, 'xlsx');

        $this->assertSame('PROPERTY ID NO', $reader->headers()[2]);

        $rows = iterator_to_array($reader->rowsFrom($reader->dataOffset()), false);

        $this->assertCount(1, $rows);
        $this->assertSame('001', $rows[0]['cells'][0], 'A text identifier must not be turned into a number.');
        $this->assertSame('30014399', $rows[0]['cells'][2]);
        $this->assertSame('2022-01-19', $rows[0]['cells'][4], 'An Excel date serial must arrive as a date.');
        $this->assertSame(2, $rows[0]['line']);

        $reader->close();
        @unlink($path);
    }

    /* ═════════════════ completing a record after the import ═════════════════ */

    public function test_an_imported_record_is_corrected_from_its_own_page(): void
    {
        $batch = $this->check($this->rows());
        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-0001')->firstOrFail();

        $this->actingAs($this->admin)
            ->get('/admin/assets/' . $asset->id)
            ->assertOk()
            ->assertSee('Edit asset information')
            ->assertSee('30014399', false)
            ->assertSee('id="edit-asset"', false);

        $this->actingAs($this->admin)
            ->post('/admin/assets/' . $asset->id . '/details', [
                'Asset_name'          => 'Mobile Pedestal (2 door)',
                'Category'            => 'Furnitures and Fixtures',
                'Condition'           => 'Good',
                'asset_location'      => 'Health Services Office — Room 3',
                'accusion_date'       => '2022-01-19',
                'purchase_Price'      => '1500.50',
                'property_id_no'      => '30014399',
                'inventory_id_no'     => '001',
                'po_no'               => 'Donation',
                'department_id'       => 2,
                'accountable_user_id' => '',
                'accountable_name'    => 'Atienza, Rio',
                'completion_status'   => 'Needs completion',
            ])
            ->assertRedirect('/admin/assets/' . $asset->id);

        $fresh = $asset->fresh();

        $this->assertSame('Mobile Pedestal (2 door)', $fresh->Asset_name);
        $this->assertSame('Health Services Office — Room 3', $fresh->asset_location);
        $this->assertSame(2, (int) $fresh->department_id);
        $this->assertSame('Needs completion', $fresh->completion_status);
        $this->assertNull($fresh->user_id, 'Nobody was chosen, so the asset is unassigned.');
        $this->assertSame('Atienza, Rio', $fresh->accountable_name, 'The name is kept while the asset is unassigned.');
        $this->assertSame('AST-0001', $fresh->Asset_code, 'Editing never rewrites the code on the sticker.');

        // The old assignment is closed, not rewritten: the accountability table
        // is the history of who held what.
        $this->assertDatabaseHas('asset_accountability', ['asset_id' => $asset->id, 'Is_Current' => 0]);

        $this->assertDatabaseHas('audit_logs', [
            'asset_id'    => $asset->id,
            'action_type' => 'UPDATE',
        ]);
    }

    public function test_an_asset_cannot_be_assigned_to_an_account_that_is_not_active(): void
    {
        $retired = $this->makeUser(2, 2, 'retired@nu-lipa.edu.ph', 'Employee');
        $retired->forceFill(['status' => 'Inactive'])->save();

        $asset = $this->makeAsset('AST-0200', 'Scanner', ['property_id_no' => '30019999']);

        $this->actingAs($this->admin)
            ->from('/admin/assets/' . $asset->id)
            ->post('/admin/assets/' . $asset->id . '/details', [
                'Asset_name'          => 'Scanner',
                'Category'            => 'General and Office Equipment',
                'Condition'           => 'Good',
                'property_id_no'      => '30019999',
                'accountable_user_id' => (string) $retired->id,
                'completion_status'   => 'Complete',
            ])
            ->assertRedirect('/admin/assets/' . $asset->id)
            ->assertSessionHasErrors('accountable_user_id');

        $this->assertSame($this->admin->id, (int) $asset->fresh()->user_id, 'The holder did not change.');
    }

    public function test_a_property_number_already_on_another_asset_cannot_be_copied_onto_this_one(): void
    {
        $this->makeAsset('AST-0300', 'Chair', ['property_id_no' => '30014399']);
        $asset = $this->makeAsset('AST-0301', 'Table');

        $this->actingAs($this->admin)
            ->from('/admin/assets/' . $asset->id)
            ->post('/admin/assets/' . $asset->id . '/details', [
                'Asset_name'        => 'Table',
                'Category'          => 'General and Office Equipment',
                'Condition'         => 'Good',
                'property_id_no'    => '30014399',
                'completion_status' => 'Complete',
            ])
            ->assertRedirect('/admin/assets/' . $asset->id)
            ->assertSessionHasErrors('property_id_no');

        $this->assertNull($asset->fresh()->property_id_no);
    }

    public function test_the_edit_form_is_administrator_only(): void
    {
        $asset = $this->makeAsset('AST-0400', 'Desk');

        $this->actingAs($this->rio)
            ->post('/admin/assets/' . $asset->id . '/details', ['Asset_name' => 'Renamed'])
            ->assertForbidden();

        $this->assertSame('Desk', $asset->fresh()->Asset_name);
    }

    /* ═══════════════════════════ helpers ═══════════════════════════ */

    private function makeUser(int $employeeId, int $departmentId, string $email, string $role): User
    {
        return User::create([
            'employee_numbers_id' => $employeeId,
            'department_id'       => $departmentId,
            'email'               => $email,
            'password'            => Hash::make('secret-password'),
            'role'                => $role,
            'status'              => 'Active',
        ]);
    }

    private function makeAsset(string $code, string $name, array $overrides = []): Asset
    {
        return Asset::create($overrides + [
            'user_id'          => $this->admin->id,
            'Asset_code'       => $code,
            'Asset_name'       => $name,
            'Category'         => 'General and Office Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => 'Active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** A data row, as the client's file writes one. */
    private function sourceRow(int $index, array $overrides = []): array
    {
        return $overrides + [
            'inventory'   => str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'po'          => 'Donation',
            'property'    => '30014' . str_pad((string) (398 + $index), 3, '0', STR_PAD_LEFT),
            'description' => 'Mobile Pedestal',
            'date'        => '19-Jan-22',
            'location'    => 'Health Services',
            'department'  => 'Health Services Office',
            'category'    => 'Furnitures and Fittings',
            'cost'        => '1.00',
            'person'      => 'Atienza, Rio',
            'code'        => 'AST-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        ];
    }

    /** The three-row file most tests start from. */
    private function rows(): array
    {
        return [$this->sourceRow(1), $this->sourceRow(2), $this->sourceRow(3)];
    }

    private function csv(array $rows): UploadedFile
    {
        $headings = ['ID NO.', 'PO NO', 'PROPERTY ID NO', 'DESCRIPTION', 'DATE ACQUIRED', 'LOCATION', 'DEPARTMENT', 'TYPE OF ASSET', 'UNIT COST', 'ACCOUNTABLE PERSON', 'ASSET CODE'];

        $lines = [implode(',', $headings)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(function ($value) {
                $value = (string) $value;

                return str_contains($value, ',') || str_contains($value, '"')
                    ? '"' . str_replace('"', '""', $value) . '"'
                    : $value;
            }, [
                $row['inventory'], $row['po'], $row['property'], $row['description'], $row['date'],
                $row['location'], $row['department'], $row['category'], $row['cost'], $row['person'], $row['code'],
            ]));
        }

        $path = tempnam(sys_get_temp_dir(), 'inventory') . '.csv';
        file_put_contents($path, implode("\n", $lines));

        return new UploadedFile($path, 'Fixed Asset Registry 2026.csv', 'text/csv', null, true);
    }

    /** Upload, confirm the mapping, check every row, and hand back the batch id. */
    private function check(array $rows): int
    {
        $upload = $this->actingAs($this->admin)->postJson('/admin/assets/import', [
            'file' => $this->csv($rows),
        ])->assertOk();

        $batch = (int) $upload->json('batch.id');
        $mapping = $upload->json('batch.mapping');

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/mapping", [
            'mapping' => $mapping,
        ])->assertOk();

        for ($guard = 0; $guard < 20; $guard++) {
            $step = $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/step")->assertOk();

            if ($step->json('done')) {
                break;
            }
        }

        return $batch;
    }

    /** One staged line, as the review table sees it. */
    private function row(int $batch, int $line): array
    {
        $rows = $this->actingAs($this->admin)
            ->getJson("/admin/assets/import/{$batch}/rows?status=all&page=1")
            ->assertOk()
            ->json('rows');

        foreach ($rows as $row) {
            if ((int) $row['line'] === $line) {
                return $row;
            }
        }

        $this->fail("Line {$line} was not staged.");
    }

    /** A minimal but genuine .xlsx: shared strings are not used, dates get a style. */
    private function xlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'workbook') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
                <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
                <Default Extension="xml" ContentType="application/xml"/>
                <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
                <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
                <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
            </Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
            </Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                <sheets><sheet name="Fixed Asset Registry" sheetId="1" r:id="rId1"/></sheets>
            </workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
            </Relationships>');

        // Style 1 is the built-in date format (numFmtId 14).
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <cellXfs count="2">
                    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
                    <xf numFmtId="14" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/>
                </cellXfs>
            </styleSheet>');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
                <row r="1">
                    <c r="A1" t="inlineStr"><is><t>ID NO.</t></is></c>
                    <c r="B1" t="inlineStr"><is><t>PO NO</t></is></c>
                    <c r="C1" t="inlineStr"><is><t>PROPERTY ID NO</t></is></c>
                    <c r="D1" t="inlineStr"><is><t>DESCRIPTION</t></is></c>
                    <c r="E1" t="inlineStr"><is><t>DATE ACQUIRED</t></is></c>
                </row>
                <row r="2">
                    <c r="A2" t="inlineStr"><is><t>001</t></is></c>
                    <c r="B2" t="inlineStr"><is><t>Donation</t></is></c>
                    <c r="C2" t="inlineStr"><is><t>30014399</t></is></c>
                    <c r="D2" t="inlineStr"><is><t>Mobile Pedestal</t></is></c>
                    <c r="E2" s="1"><v>44580</v></c>
                </row>
            </sheetData></worksheet>');

        $zip->close();

        return $path;
    }
}
