<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Support\AssetQr;
use App\Support\Import\AssetImport;
use App\Support\Import\SpreadsheetReader;
use App\Support\Media;
use App\Support\QrPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    /* ═══════════════════════ uploads of any size ═══════════════════════ */

    public function test_a_file_can_be_uploaded_in_parts_and_is_read_as_one_workbook(): void
    {
        $bytes = file_get_contents($this->csv($this->rows())->getRealPath());
        $total = 3;
        $size = (int) ceil(strlen($bytes) / $total);
        $uploadId = bin2hex(random_bytes(16));

        $last = null;

        for ($index = 0; $index < $total; $index++) {
            $response = $this->actingAs($this->admin)->post('/admin/assets/import/part', [
                'upload_id' => $uploadId,
                'index'     => $index,
                'total'     => $total,
                'name'      => 'Fixed Asset Registry 2026.csv',
                'file'      => UploadedFile::fake()->createWithContent(
                    'part-' . $index . '.part',
                    substr($bytes, $index * $size, $size)
                ),
            ])->assertOk();

            // Only the part that completes the set opens the batch.
            if ($index < $total - 1) {
                $response->assertJsonPath('received', $index + 1);
                $this->assertNull($response->json('batch'));
            }

            $last = $response;
        }

        $last->assertJsonPath('ok', true);
        $this->assertSame(strlen($bytes), $last->json('batch.size_bytes'));
        $this->assertSame(2, $last->json('batch.mapping.property_id_no'));

        // The joined file is the real one: its columns were detected from it.
        $this->assertSame('PROPERTY ID NO', $last->json('batch.header.2'));

        // The slices are discarded once joined; a marker stays so a retried
        // last slice can find the batch it already made.
        $left = Storage::disk('local')->files('asset-imports/_parts/' . $uploadId);
        $this->assertSame([], array_values(array_filter($left, fn (string $file) => str_ends_with($file, '.part'))));
        $this->assertContains('asset-imports/_parts/' . $uploadId . '/joined.txt', $left);
    }

    public function test_a_workbook_sent_in_parts_is_recognised_if_it_is_sent_again_whole(): void
    {
        $batchId = $this->uploadParts($this->rows());

        $again = $this->actingAs($this->admin)->postJson('/admin/assets/import', [
            'file' => $this->csv($this->rows()),
        ])->assertOk();

        // Same bytes, same fingerprint: the open batch is resumed, not doubled.
        $this->assertSame($batchId, $again->json('batch.id'));
        $this->assertStringContainsString('already open', (string) $again->json('message'));
    }

    public function test_a_retried_last_slice_opens_the_batch_it_already_created(): void
    {
        $bytes = file_get_contents($this->csv($this->rows())->getRealPath());
        $total = 2;
        $size = (int) ceil(strlen($bytes) / $total);
        $uploadId = bin2hex(random_bytes(16));

        $send = fn (int $index) => $this->actingAs($this->admin)->post('/admin/assets/import/part', [
            'upload_id' => $uploadId,
            'index'     => $index,
            'total'     => $total,
            'name'      => 'Fixed Asset Registry 2026.csv',
            'file'      => UploadedFile::fake()->createWithContent(
                'part-' . $index . '.part',
                substr($bytes, $index * $size, $size)
            ),
        ])->assertOk();

        $send(0)->assertJsonPath('received', 1);
        $batchId = (int) $send(1)->json('batch.id');

        // The browser lost the answer to the last slice and sent it again.
        $again = $send(1);

        $again->assertJsonPath('batch.id', $batchId);
        $this->assertStringContainsString('already uploaded', (string) $again->json('message'));
        $this->assertSame(1, DB::table('asset_import_batches')->where('file_hash', hash('sha256', $bytes))->count());
    }

    public function test_an_upload_id_that_is_not_hex_is_refused(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/assets/import/part', [
            'upload_id' => '../../escape',
            'index'     => 0,
            'total'     => 1,
            'name'      => 'inventory.csv',
            'file'      => $this->csv($this->rows()),
        ])->assertStatus(422)->assertJsonValidationErrors('upload_id');
    }

    public function test_a_slice_outside_the_set_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/admin/assets/import/part', [
            'upload_id' => str_repeat('b', 32),
            'index'     => 2,
            'total'     => 2,
            'name'      => 'inventory.csv',
            'file'      => $this->csv($this->rows()),
        ])->assertStatus(422);
    }

    public function test_a_file_is_never_refused_for_its_size(): void
    {
        // There is no size rule at all — the upload is bounded by the disk, not
        // by a configured ceiling left over from an older deployment.
        $this->actingAs($this->admin)->postJson('/admin/assets/import', [
            'file' => $this->csv($this->rows()),
        ])->assertOk();

        // A part is never larger than one request can carry.
        $this->assertGreaterThanOrEqual(65536, AssetImport::partBytes());
        $this->assertLessThanOrEqual(8 * 1048576, AssetImport::partBytes());
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

    public function test_the_import_page_says_the_property_number_is_the_code(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/assets/import')
            ->assertOk()
            ->assertSee('PROPERTY ID NO is the Asset Code')
            ->assertSee('Asset Code (Property ID No.)')
            ->assertDontSee('Fill blank codes')
            ->assertDontSee('Propose codes from a pattern')
            ->assertDontSee('Asset codes');
    }

    public function test_the_import_page_does_not_announce_a_size_cap(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/assets/import')
            ->assertOk()
            ->assertSee('no size limit')
            // The page slices the file at the size the server can actually take.
            ->assertSee('const PART_BYTES = ' . AssetImport::partBytes(), false);
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

    public function test_the_office_can_choose_the_accountable_person_for_one_line(): void
    {
        // The name in the file matches nobody, so the line arrives as a review
        // row that would be imported unassigned. The office picks a real
        // account for it during review instead of fixing the workbook.
        $rows = [$this->sourceRow(1, ['property' => '30019400', 'person' => 'Ghost Person', 'code' => 'AST-5005'])];

        $batch = $this->check($rows);

        $this->assertSame(AssetImport::REVIEW, $this->row($batch, 2)['status']);
        $this->assertSame(AssetImport::PERSON_NO_EMPLOYEE, $this->row($batch, 2)['accountable_status']);

        $this->actingAs($this->admin)
            ->postJson("/admin/assets/import/{$batch}/person", ['line' => 2, 'user_id' => $this->rio->id])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $row = $this->row($batch, 2);

        $this->assertSame($this->rio->id, $row['accountable_user_id']);
        $this->assertSame($this->rio->id, $row['accountable_override']);
        $this->assertSame(AssetImport::PERSON_ASSIGNED, $row['accountable_status']);
        $this->assertSame(AssetImport::READY, $row['status'], 'The only warning is gone, so the line is ready.');

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-5005')->firstOrFail();

        $this->assertSame($this->rio->id, (int) $asset->user_id);
        $this->assertSame('Rio Atienza', $asset->accountable_name);
        $this->assertDatabaseHas('asset_accountability', [
            'asset_id' => $asset->id,
            'user_id'  => $this->rio->id,
            'Is_Current' => 1,
        ]);
    }

    public function test_a_line_can_be_left_unassigned_by_hand_and_keeps_the_name_from_the_file(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30019500', 'person' => 'Atienza, Rio', 'code' => 'AST-5006'])];

        $batch = $this->check($rows);

        // The file's name resolves to a real account on its own.
        $this->assertSame($this->rio->id, $this->row($batch, 2)['accountable_user_id']);

        $this->actingAs($this->admin)
            ->postJson("/admin/assets/import/{$batch}/person", ['line' => 2, 'user_id' => 0])
            ->assertOk();

        $row = $this->row($batch, 2);

        $this->assertNull($row['accountable_user_id'], 'The chosen account was removed.');
        $this->assertSame(0, $row['accountable_override']);
        $this->assertSame('Atienza, Rio', $row['accountable_name'], 'The name in the file is not thrown away.');

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', 'AST-5006')->firstOrFail();

        $this->assertNull($asset->user_id);
        $this->assertSame('Atienza, Rio', $asset->accountable_name);
    }

    public function test_an_inactive_account_cannot_be_chosen_for_a_line(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30019600', 'person' => 'Ghost Person', 'code' => 'AST-5007'])];

        $batch = $this->check($rows);

        $inactive = $this->makeUser(2, 2, 'maria@nu-lipa.edu.ph', 'Employee');
        DB::table('users')->where('id', $inactive->id)->update(['status' => 'Inactive']);

        $this->actingAs($this->admin)
            ->postJson("/admin/assets/import/{$batch}/person", ['line' => 2, 'user_id' => $inactive->id])
            ->assertStatus(422);

        $this->assertNull($this->row($batch, 2)['accountable_user_id']);
    }

    /* ═══════════════════════════ custom codes ═══════════════════════════ */

    public function test_a_blank_asset_code_cell_still_uses_the_property_number(): void
    {
        // The client's file leaves any code column empty: that must not hold a
        // line whose PROPERTY ID NO is right there.
        $rows = [$this->sourceRow(1, ['property' => '30020000', 'code' => ''])];

        $batch = $this->check($rows);

        $row = $this->row($batch, 2);
        $this->assertSame(AssetImport::READY, $row['status']);
        $this->assertSame('30020000', $row['asset_code']);
    }

    public function test_the_property_number_is_the_asset_code_and_the_sticker_code(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30014399', 'code' => ''])];

        // No ASSET CODE heading at all, as in the client's workbook.
        $upload = $this->actingAs($this->admin)->postJson('/admin/assets/import', [
            'file' => $this->csv($rows, false),
        ])->assertOk();

        $mapping = $upload->json('batch.mapping');
        $this->assertSame(
            $mapping['property_id_no'],
            $mapping['asset_code'],
            'Asset Code must default to the PROPERTY ID NO column.'
        );

        $batch = $this->check($rows, false);

        $row = $this->row($batch, 2);
        $this->assertSame(AssetImport::READY, $row['status']);
        $this->assertSame('30014399', $row['asset_code']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/commit")->assertOk();

        $asset = Asset::where('Asset_code', '30014399')->firstOrFail();
        $this->assertSame('30014399', $asset->property_id_no);

        // That code is what the sticker encodes: the QR is minted from the
        // asset code, and the asset code is the client's property number.
        $this->assertSame('30014399', QrPayload::parse(AssetQr::textFor($asset->Asset_code))['code']);

        // The image needs media storage, which tests do not have; when it was
        // produced, it must carry the same number.
        $this->assertTrue(
            $asset->qr_payload === null
                || QrPayload::parse((string) $asset->qr_payload)['code'] === '30014399',
            'A generated sticker must encode the property number.'
        );
    }

    public function test_the_page_no_longer_sends_an_asset_code_and_the_property_number_is_used(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '30019999', 'code' => ''])];

        $upload = $this->actingAs($this->admin)->postJson('/admin/assets/import', [
            'file' => $this->csv($rows, false),
        ])->assertOk();

        $batch = (int) $upload->json('batch.id');
        $mapping = $upload->json('batch.mapping');

        // What the page posts now: only the fields it still shows.
        unset($mapping['asset_code']);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/mapping", [
            'mapping' => $mapping,
        ])->assertOk();

        for ($guard = 0; $guard < 20; $guard++) {
            if ($this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/step")->assertOk()->json('done')) {
                break;
            }
        }

        $row = $this->row($batch, 2);
        $this->assertSame(AssetImport::READY, $row['status']);
        $this->assertSame('30019999', $row['asset_code']);
    }

    public function test_a_line_with_no_number_at_all_takes_a_code_by_hand(): void
    {
        $rows = [$this->sourceRow(1, ['property' => '', 'code' => ''])];

        $batch = $this->check($rows);

        $row = $this->row($batch, 2);
        $this->assertSame(AssetImport::BLOCKED, $row['status']);
        $this->assertStringContainsString('No asset code', implode(' ', array_column($row['issues'], 'text')));

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/codes", [
            'codes' => [2 => 'nu-2026-0001'],
        ])->assertOk();

        $row = $this->row($batch, 2);
        $this->assertSame('NU-2026-0001', $row['asset_code']);

        // A code does not invent a property number, so the line is still held.
        $this->assertSame(AssetImport::BLOCKED, $row['status']);
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
        // Neither a property number nor a code: the only case that needs one.
        $rows = [
            $this->sourceRow(1, ['property' => '', 'code' => '']),
            $this->sourceRow(2, ['property' => '', 'code' => '']),
        ];

        $batch = $this->check($rows);

        $this->actingAs($this->admin)->postJson("/admin/assets/import/{$batch}/propose", [
            'pattern' => 'NU-{seq}',
        ])->assertOk();

        $this->assertSame('NU-2', $this->row($batch, 2)['asset_code']);
        $this->assertSame('NU-3', $this->row($batch, 3)['asset_code']);
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

    public function test_a_workbook_that_prefixes_its_elements_is_read_the_same_way(): void
    {
        // Excel writes the spreadsheet namespace as the default one (<row>),
        // but other writers prefix every element (<x:row>) — PhpSpreadsheet,
        // which produced the office's test download, is one of them. Both are
        // the same OOXML and must arrive as the same rows, dates included.
        $path = $this->prefixedXlsx();

        $reader = SpreadsheetReader::open($path, 'xlsx');

        $this->assertSame('ID NO.', $reader->headers()[0]);
        $this->assertSame('PROPERTY ID NO', $reader->headers()[1]);

        $rows = iterator_to_array($reader->rowsFrom($reader->dataOffset()), false);

        $this->assertCount(1, $rows);
        $this->assertSame('001', $rows[0]['cells'][0]);
        $this->assertSame('Mobile Pedestal', $rows[0]['cells'][1]);
        $this->assertSame('2022-01-19', $rows[0]['cells'][2], 'A prefixed cell style must still be read as a date.');
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

    public function test_a_photo_can_be_uploaded_while_completing_the_record(): void
    {
        Storage::fake('public');

        $asset = $this->makeAsset('AST-0500', 'Mobile Pedestal');

        $this->assertSame(0, DB::table('asset_files')->count());

        $this->actingAs($this->admin)
            ->post('/admin/assets/' . $asset->id . '/details', $this->editFields($asset) + [
                'asset_photo' => $this->photo('pedestal.png'),
            ])
            ->assertRedirect('/admin/assets/' . $asset->id)
            ->assertSessionHasNoErrors();

        $file = DB::table('asset_files')->where('Asset_id', $asset->id)->first();

        $this->assertNotNull($file, 'The uploaded photo must be recorded against the asset.');
        $this->assertSame('pedestal.png', $file->file_name);
        Storage::disk('public')->assertExists($file->file_path);

        // The record page now shows the photo instead of the empty frame.
        $this->actingAs($this->admin)
            ->get('/admin/assets/' . $asset->id)
            ->assertOk()
            ->assertSee($file->url, false)
            ->assertDontSee('No photo')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="asset_photo"', false)
            // The hint under the field names the server's real limit, not a
            // ceiling PHP would throw the file away for exceeding.
            ->assertSee('up to ' . \App\Support\AssetEditing::photoMaxLabel() . '.', false);

        $this->assertDatabaseHas('audit_logs', ['asset_id' => $asset->id, 'action_type' => 'UPDATE']);
    }

    public function test_a_new_photo_replaces_the_previous_one_instead_of_stacking_up(): void
    {
        Storage::fake('public');

        $asset = $this->makeAsset('AST-0501', 'Desk');
        $this->givePhoto($asset, 'assets/old-photo.png');

        $this->actingAs($this->admin)
            ->post('/admin/assets/' . $asset->id . '/details', $this->editFields($asset) + [
                'asset_photo' => $this->photo('new-photo.png'),
            ])
            ->assertRedirect('/admin/assets/' . $asset->id);

        $files = DB::table('asset_files')->where('Asset_id', $asset->id)->get();

        $this->assertCount(1, $files, 'An asset keeps one photo, so every page shows the same one.');
        $this->assertSame('new-photo.png', $files->first()->file_name);
        Storage::disk('public')->assertExists($files->first()->file_path);
        Storage::disk('public')->assertMissing('assets/old-photo.png');
    }

    public function test_a_file_that_is_not_an_image_is_refused_and_the_current_photo_is_kept(): void
    {
        Storage::fake('public');

        $asset = $this->makeAsset('AST-0502', 'Chair');
        $this->givePhoto($asset, 'assets/keep.png');

        $this->actingAs($this->admin)
            ->from('/admin/assets/' . $asset->id)
            ->post('/admin/assets/' . $asset->id . '/details', $this->editFields($asset) + [
                'asset_photo' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
            ])
            ->assertRedirect('/admin/assets/' . $asset->id)
            ->assertSessionHasErrors('asset_photo');

        $this->assertSame(1, DB::table('asset_files')->count());
        $this->assertSame('assets/keep.png', DB::table('asset_files')->value('file_path'));
        Storage::disk('public')->assertExists('assets/keep.png');
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

    /**
     * The text fields the edit form posts. A test that is about the photo adds
     * only the file, so nothing else in the record changes underneath it.
     */
    private function editFields(Asset $asset): array
    {
        return [
            'Asset_name'          => $asset->Asset_name,
            'Category'            => $asset->Category ?? 'General and Office Equipment',
            'Condition'           => $asset->Condition ?? 'Good',
            'asset_location'      => '',
            'accusion_date'       => '',
            'purchase_Price'      => '',
            'property_id_no'      => '',
            'inventory_id_no'     => '',
            'po_no'               => '',
            'department_id'       => '',
            'accountable_user_id' => '',
            'accountable_name'    => '',
            'completion_status'   => 'Complete',
        ];
    }

    /**
     * A real 1x1 PNG. The `image` rule reads the file itself and this container
     * has no GD, so the bytes have to be a genuine image.
     */
    private function photo(string $name = 'asset.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/AF+g7oAAAAASUVORK5CYII=')
        );
    }

    /** An asset that already has a photo, as the registry and imports create them. */
    private function givePhoto(Asset $asset, string $path): void
    {
        Storage::disk('public')->put($path, 'photo-bytes');

        DB::table('asset_files')->insert([
            'Asset_id'    => $asset->id,
            'file_name'   => basename($path),
            'file_path'   => $path,
            'file_size'   => 11,
            'mime_type'   => 'image/png',
            'url'         => Media::url($path),
            'uploaded_at' => now()->toDateTimeString(),
            'created_at'  => now(),
            'updated_at'  => now(),
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

    /**
     * A registry file. The client's real file has no ASSET CODE column — its
     * PROPERTY ID NO is the code — so that shape is what $withCodeColumn=false
     * builds; the column stays here to cover files that do keep a separate code.
     */
    private function csv(array $rows, bool $withCodeColumn = true): UploadedFile
    {
        $headings = ['ID NO.', 'PO NO', 'PROPERTY ID NO', 'DESCRIPTION', 'DATE ACQUIRED', 'LOCATION', 'DEPARTMENT', 'TYPE OF ASSET', 'UNIT COST', 'ACCOUNTABLE PERSON', 'ASSET CODE'];

        if (! $withCodeColumn) {
            array_pop($headings);
        }

        $lines = [implode(',', $headings)];

        foreach ($rows as $row) {
            $cells = [
                $row['inventory'], $row['po'], $row['property'], $row['description'], $row['date'],
                $row['location'], $row['department'], $row['category'], $row['cost'], $row['person'], $row['code'],
            ];

            if (! $withCodeColumn) {
                array_pop($cells);
            }

            $lines[] = implode(',', array_map(function ($value) {
                $value = (string) $value;

                return str_contains($value, ',') || str_contains($value, '"')
                    ? '"' . str_replace('"', '""', $value) . '"'
                    : $value;
            }, $cells));
        }

        $path = tempnam(sys_get_temp_dir(), 'inventory') . '.csv';
        file_put_contents($path, implode("\n", $lines));

        return new UploadedFile($path, 'Fixed Asset Registry 2026.csv', 'text/csv', null, true);
    }

    /** Send the registry in slices, the way the Import page does; return its batch id. */
    private function uploadParts(array $rows, int $total = 3): int
    {
        $bytes = file_get_contents($this->csv($rows)->getRealPath());
        $size = (int) ceil(strlen($bytes) / $total);
        $uploadId = bin2hex(random_bytes(16));

        $last = null;

        for ($index = 0; $index < $total; $index++) {
            $last = $this->actingAs($this->admin)->post('/admin/assets/import/part', [
                'upload_id' => $uploadId,
                'index'     => $index,
                'total'     => $total,
                'name'      => 'Fixed Asset Registry 2026.csv',
                'file'      => UploadedFile::fake()->createWithContent(
                    'part-' . $index . '.part',
                    substr($bytes, $index * $size, $size)
                ),
            ])->assertOk();
        }

        $last->assertJsonPath('ok', true);

        return (int) $last->json('batch.id');
    }

    /** Upload, confirm the mapping, check every row, and hand back the batch id. */
    private function check(array $rows, bool $withCodeColumn = true): int
    {
        $upload = $this->actingAs($this->admin)->postJson('/admin/assets/import', [
            'file' => $this->csv($rows, $withCodeColumn),
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

    /**
     * The same workbook written the way PhpSpreadsheet writes it: every
     * element carries an `x:` prefix, strings live in a shared table, and the
     * date column points at a style.
     */
    private function prefixedXlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'workbook-prefixed') . '.xlsx';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
                <Default Extension="xml" ContentType="application/xml"/>
                <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            </Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
            </Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <x:workbook xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                <x:sheets><x:sheet name="Fixed Asset Registry" sheetId="1" r:id="rId1"/></x:sheets>
            </x:workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
            </Relationships>');

        // Style 1 is the built-in date format (numFmtId 14), prefixed.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <x:styleSheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <x:cellXfs count="2">
                    <x:xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
                    <x:xf numFmtId="14" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/>
                </x:cellXfs>
            </x:styleSheet>');

        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <x:sst xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <x:si><x:t>ID NO.</x:t></x:si>
                <x:si><x:t>PROPERTY ID NO</x:t></x:si>
                <x:si><x:t>001</x:t></x:si>
                <x:si><x:t>Mobile Pedestal</x:t></x:si>
            </x:sst>');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData>
                <x:row r="1">
                    <x:c r="A1" t="s"><x:v>0</x:v></x:c>
                    <x:c r="B1" t="s"><x:v>1</x:v></x:c>
                </x:row>
                <x:row r="2">
                    <x:c r="A2" t="s"><x:v>2</x:v></x:c>
                    <x:c r="B2" t="s"><x:v>3</x:v></x:c>
                    <x:c r="C2" s="1"><x:v>44580</x:v></x:c>
                </x:row>
            </x:sheetData></x:worksheet>');

        $zip->close();

        return $path;
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
