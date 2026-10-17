<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Employee and Account Management (panel recommendation 2).
 *
 * The rule that shapes every test here: the masterlist and the accounts are two
 * different things. Adding an employee writes a fact about the institution and
 * creates no login; an account is a separate, deliberate decision made on the
 * Accounts tab. A test that let the first of those quietly create the second
 * would be testing the wrong system.
 *
 * The second rule: all of it is the Asset Management Office's job, checked on
 * the server. The page and its buttons are convenience.
 */
class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'Registrar', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Alex Reyes', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Nena Santos', 'Department_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'alex@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 2, 'email' => 'nena@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);
    }

    /**
     * The form's own fields.
     *
     * The Add Employee form does not carry an official email any more, but the
     * address is still accepted when a caller does send one (the import records
     * addresses too), so it stays in the payload the tests post.
     */
    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'employee_number' => 'EMP-3',
            'full_name'       => 'Juan Dela Cruz',
            'email'           => 'juan.dela.cruz@nu-lipa.edu.ph',
            'department_id'   => 2,
            'status'          => 'Active',
        ], $overrides);
    }

    /* ═══════════════════ 2.2 / 2.3 — the directory ═══════════════════ */

    public function test_the_directory_lists_masterlist_records_with_their_account(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/employees');

        $response->assertOk()
            ->assertSee('Employee Management')
            ->assertSee('Nena Santos')
            ->assertSee('EMP-2')
            ->assertSee('Registrar');
    }

    public function test_the_directory_can_be_searched_and_filtered(): void
    {
        // The signed-in administrator's own name is in the page header, so the
        // assertions follow the employee numbers, which only appear on rows.
        $this->actingAs($this->admin)
            ->get('/admin/employees?q=EMP-2')
            ->assertOk()
            ->assertSee('Nena Santos')
            ->assertDontSee('EMP-1');

        // A department filter is applied in SQL, so it cannot be talked out of
        // the way by a crafted query string either.
        $this->actingAs($this->admin)
            ->get('/admin/employees?department=1')
            ->assertOk()
            ->assertSee('EMP-1')
            ->assertDontSee('EMP-2');

        // Both records here already have an account, so this filter is empty on
        // purpose - it is how the office finds the people who have not
        // activated an account yet.
        $this->actingAs($this->admin)
            ->get('/admin/employees?account=unregistered')
            ->assertOk()
            ->assertSee('No employee record matches this filter');
    }

    public function test_an_employee_record_can_be_added_by_hand(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/employees', $this->employeePayload());

        $response->assertRedirect('/admin/employees');

        $row = DB::table('employee_numbers')->where('Employee_number', 'EMP-3')->first();

        $this->assertNotNull($row, 'the employee record must be written');
        $this->assertSame('Juan Dela Cruz', $row->Full_Name);
        $this->assertSame('juan.dela.cruz@nu-lipa.edu.ph', $row->Email);
        $this->assertSame(2, (int) $row->Department_id);
        $this->assertSame('Active', $row->status);

        // Adding an employee is not creating an account: no login, no role.
        $this->assertSame(0, DB::table('users')->where('employee_numbers_id', $row->id)->count());
        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_the_add_employee_form_asks_for_no_official_email(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/employees')
            ->assertOk()
            // Anchored first: the form really is on the page, so the two
            // assertions below are not passing on an empty response.
            ->assertSee('name="employee_number"', false)
            ->assertDontSee('Official email')
            ->assertDontSee('name="email"', false);
    }

    public function test_an_employee_can_be_added_without_an_official_email(): void
    {
        $payload = $this->employeePayload();
        unset($payload['email']);

        $this->actingAs($this->admin)
            ->post('/admin/employees', $payload)
            ->assertRedirect('/admin/employees');

        $row = DB::table('employee_numbers')->where('Employee_number', 'EMP-3')->first();

        $this->assertNotNull($row, 'an employee record must be written with no email in the form');
        $this->assertNull($row->Email);
        $this->assertSame('Juan Dela Cruz', $row->Full_Name);
        $this->assertSame(2, (int) $row->Department_id);
        $this->assertSame('Active', $row->status);
    }

    public function test_editing_a_record_leaves_a_stored_official_email_alone(): void
    {
        // An address on the record - recorded by an import, or before the field
        // was taken off the form. An edit that says nothing about it must not
        // erase it.
        DB::table('employee_numbers')->where('id', 2)->update(['Email' => 'nena@nu-lipa.edu.ph']);

        $payload = $this->employeePayload([
            'employee_number' => 'EMP-2',
            'full_name'       => 'Nena S. Santos',
        ]);
        unset($payload['email']);

        $this->actingAs($this->admin)
            ->post('/admin/employees/2', $payload)
            ->assertRedirect('/admin/employees');

        $row = DB::table('employee_numbers')->where('id', 2)->first();

        $this->assertSame('Nena S. Santos', $row->Full_Name);
        $this->assertSame('nena@nu-lipa.edu.ph', $row->Email, 'a field the form does not send must not be erased');
    }

    public function test_adding_an_employee_is_recorded_in_the_audit_log(): void
    {
        $this->actingAs($this->admin)->post('/admin/employees', $this->employeePayload());

        $entry = DB::table('audit_logs')
            ->where('user_id', $this->admin->id)
            ->where('notes', 'like', '%EMP-3%')
            ->first();

        $this->assertNotNull($entry, 'a masterlist change must appear in the existing audit log');
        $this->assertSame('CREATE', $entry->action_type);
    }

    public function test_a_duplicate_employee_number_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/employees', $this->employeePayload(['employee_number' => 'EMP-2']))
            ->assertSessionHasErrors('employee_number');

        $this->assertSame(1, DB::table('employee_numbers')->where('Employee_number', 'EMP-2')->count());
    }

    public function test_a_duplicate_email_is_refused_whatever_its_casing(): void
    {
        DB::table('employee_numbers')->where('id', 2)->update(['Email' => 'nena@nu-lipa.edu.ph']);

        $this->actingAs($this->admin)
            ->post('/admin/employees', $this->employeePayload(['email' => 'NENA@nu-lipa.edu.ph']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, DB::table('employee_numbers')->where('Employee_number', 'EMP-3')->count());
    }

    public function test_an_unknown_department_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/employees', $this->employeePayload(['department_id' => 99]))
            ->assertSessionHasErrors('department_id');
    }

    public function test_required_fields_are_validated_on_the_server(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/employees', ['employee_number' => '', 'full_name' => '', 'department_id' => ''])
            ->assertSessionHasErrors(['employee_number', 'full_name', 'department_id']);
    }

    public function test_an_existing_employee_record_can_be_corrected(): void
    {
        DB::table('employee_numbers')->where('id', 2)->update(['Email' => 'old@nu-lipa.edu.ph']);

        $this->actingAs($this->admin)
            ->post('/admin/employees/2', $this->employeePayload([
                'employee_number' => 'EMP-2',
                'full_name'       => 'Nena S. Santos',
                'email'           => 'nena.santos@nu-lipa.edu.ph',
                'status'          => 'Active',
            ]))
            ->assertRedirect('/admin/employees');

        $row = DB::table('employee_numbers')->where('id', 2)->first();

        $this->assertSame('Nena S. Santos', $row->Full_Name);
        $this->assertSame('nena.santos@nu-lipa.edu.ph', $row->Email);
    }

    public function test_editing_an_employee_never_touches_their_account(): void
    {
        $this->actingAs($this->admin)->post('/admin/employees/2', $this->employeePayload([
            'employee_number' => 'EMP-2',
            'full_name'       => 'Nena S. Santos',
        ]));

        // The correction belongs to the masterlist. The account keeps its role
        // and status, because a renamed employee is not a promoted one.
        $account = User::find($this->employee->id);

        $this->assertSame('Employee', $account->role);
        $this->assertSame('Active', $account->status);
        $this->assertSame(2, (int) $account->department_id);
    }

    /* ═══════════════════ 2.4 — CSV import ═══════════════════ */

    public function test_the_template_downloads_with_the_supported_headings(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/employees/template');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();

        $this->assertStringContainsString('Employee Number', $body);
        $this->assertStringContainsString('Full Name', $body);
        $this->assertStringContainsString('Department', $body);
    }

    public function test_a_valid_csv_is_previewed_without_writing_anything(): void
    {
        $csv = "Employee Number,Full Name,Email,Department,Status\n"
            . "EMP-10,Ana Cruz,ana@nu-lipa.edu.ph,Registrar,Active\n"
            . "EMP-11,Ben Ocampo,,Facilities,Active\n";

        $response = $this->actingAs($this->admin)->post('/admin/employees/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.ready', 2)
            ->assertJsonPath('summary.invalid', 0);

        // The whole point of a preview: nothing is in the database yet.
        $this->assertSame(0, DB::table('employee_numbers')->whereIn('Employee_number', ['EMP-10', 'EMP-11'])->count());
    }

    public function test_a_valid_csv_is_imported(): void
    {
        $csv = "Employee Number,Full Name,Email,Department,Status\n"
            . "EMP-10,Ana Cruz,ana@nu-lipa.edu.ph,Registrar,Active\n"
            . "EMP-11,Ben Ocampo,,Facilities,Active\n";

        $this->actingAs($this->admin)
            ->post('/admin/employees/import', ['file' => UploadedFile::fake()->createWithContent('employees.csv', $csv)])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('imported', 2);

        $ana = DB::table('employee_numbers')->where('Employee_number', 'EMP-10')->first();

        $this->assertNotNull($ana);
        $this->assertSame('Ana Cruz', $ana->Full_Name);
        $this->assertSame(2, (int) $ana->Department_id);

        // Importing a list creates records, never accounts.
        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_an_import_leaves_existing_records_alone(): void
    {
        $csv = "Employee Number,Full Name,Department\n"
            . "EMP-2,Somebody Else,Facilities\n";

        $response = $this->actingAs($this->admin)->post('/admin/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
        ]);

        // Nothing to do: the only row names an employee who is already on the
        // masterlist, so the import says so instead of writing anything.
        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('skipped', 1);

        $row = DB::table('employee_numbers')->where('Employee_number', 'EMP-2')->first();

        $this->assertSame('Nena Santos', $row->Full_Name, 'an import must never overwrite an existing employee');
        $this->assertSame(2, (int) $row->Department_id);
    }

    public function test_invalid_rows_are_named_and_the_good_rows_still_import(): void
    {
        $csv = "Employee Number,Full Name,Email,Department,Status\n"
            . "EMP-20,Good Row,,Registrar,Active\n"
            . "EMP-21,Bad Department,,No Such Office,Active\n"
            . "EMP-22,Bad Status,,Registrar,Retired\n"
            . ",No Number,,Registrar,Active\n"
            . "EMP-23,Duplicate Email,ana@nu-lipa.edu.ph,Registrar,Active\n";

        $preview = $this->actingAs($this->admin)->post('/admin/employees/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
        ]);

        $preview->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('summary.total', 5)
            ->assertJsonPath('summary.ready', 2)     // EMP-20 and EMP-23
            ->assertJsonPath('summary.invalid', 3);  // unknown department, bad status, missing number

        $import = $this->actingAs($this->admin)->post('/admin/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
        ]);

        $import->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('invalid', 3);

        // Every rejected row is reported by line, with a reason - never dropped
        // silently.
        $errors = $import->json('errors');

        $this->assertCount(3, $errors);
        $this->assertSame(3, $errors[0]['line']);
        $this->assertStringContainsString('No Such Office', implode(' ', $errors[0]['errors']));

        $this->assertSame(1, DB::table('employee_numbers')->where('Employee_number', 'EMP-20')->count());
        $this->assertSame(0, DB::table('employee_numbers')->where('Employee_number', 'EMP-21')->count());
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $csv = "Employee Number,Full Name\nEMP-30,Ana Cruz\n";

        $response = $this->actingAs($this->admin)->post('/admin/employees/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);

        // The message names the column by the heading the template uses, so the
        // office knows what to add to the file.
        $this->assertStringContainsString('Department', (string) $response->json('message'));
    }

    public function test_duplicate_rows_inside_one_file_are_reported(): void
    {
        $csv = "Employee Number,Full Name,Department\n"
            . "EMP-40,Ana Cruz,Registrar\n"
            . "EMP-40,Ana Again,Registrar\n";

        $this->actingAs($this->admin)
            ->post('/admin/employees/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
            ])
            ->assertOk()
            ->assertJsonPath('summary.ready', 1)
            ->assertJsonPath('summary.invalid', 1);
    }

    public function test_an_import_is_recorded_once_in_the_audit_log(): void
    {
        $csv = "Employee Number,Full Name,Department\nEMP-50,Ana Cruz,Registrar\nEMP-51,Ben Ocampo,Facilities\n";

        $this->actingAs($this->admin)
            ->post('/admin/employees/import', ['file' => UploadedFile::fake()->createWithContent('employees.csv', $csv)])
            ->assertOk();

        $entries = DB::table('audit_logs')
            ->where('user_id', $this->admin->id)
            ->where('action_description', 'like', 'Employee masterlist import%')
            ->get();

        $this->assertCount(1, $entries, 'one summary entry per import, not one per employee');
        $this->assertStringContainsString('2 record(s)', $entries->first()->action_description);
    }

    /* ═══════════════════ 2.5 — CSV export ═══════════════════ */

    public function test_the_export_contains_the_records_and_no_credentials(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/employees/export');

        $response->assertOk();

        $body = $response->streamedContent();

        $this->assertStringContainsString('Employee Number', $body);
        $this->assertStringContainsString('Nena Santos', $body);
        $this->assertStringContainsString('EMP-2', $body);

        // Nothing that could be used to sign in may leave in a spreadsheet.
        $this->assertStringNotContainsString('password', strtolower($body));
        $this->assertStringNotContainsString('secret-password', $body);
        $this->assertStringNotContainsString('$2y$', $body, 'no hash may travel in an export');
    }

    public function test_the_export_follows_the_filter_on_screen(): void
    {
        $body = $this->actingAs($this->admin)->get('/admin/employees/export?department=1')->streamedContent();

        $this->assertStringContainsString('Alex Reyes', $body);
        $this->assertStringNotContainsString('Nena Santos', $body);
    }

    public function test_a_leading_equals_sign_cannot_become_a_spreadsheet_formula(): void
    {
        DB::table('employee_numbers')->where('id', 2)->update(['Full_Name' => '=HYPERLINK("http://evil.example","click me")']);

        $body = $this->actingAs($this->admin)->get('/admin/employees/export')->streamedContent();

        $this->assertStringNotContainsString('"=HYPERLINK', $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);
    }

    /* ═══════════════════ 2.6 / 2.7 — accounts ═══════════════════ */

    public function test_an_accounts_role_can_be_changed(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/role", ['role' => 'Department Head'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('Department Head', User::find($this->employee->id)->role);

        $entry = DB::table('audit_logs')
            ->where('user_id', $this->admin->id)
            ->where('notes', 'like', '%role changed%')
            ->orWhere('notes', 'like', '%Changed role%')
            ->first();

        $this->assertNotNull($entry, 'a role change must be in the audit log');
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/role", ['role' => 'Superuser'])
            ->assertStatus(422);

        $this->assertSame('Employee', User::find($this->employee->id)->role);
    }

    public function test_the_accounts_tab_does_not_offer_the_facilities_role(): void
    {
        // Nothing on the page offers the retired role, so there is nothing to
        // pick. (The word "Facilities" is still on the page as a department
        // name; the role options carry it as a value, which is what is checked
        // here.)
        $this->actingAs($this->admin)
            ->get('/admin/employees?tab=accounts')
            ->assertOk()
            // The three roles that can still be assigned are offered, and the
            // retired one is not.
            ->assertSee('value="Admin"', false)
            ->assertSee('value="Department Head"', false)
            ->assertDontSee('value="Facilities"', false);

        // A crafted filter cannot ask for it either: the filter is validated
        // against the same list of assignable roles.
        $this->assertSame('', \App\Support\AccountAdmin::filters(['role' => 'Facilities'])['role']);
    }

    public function test_the_last_active_administrator_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->admin->id}/role", ['role' => 'Employee'])
            ->assertStatus(422);

        $this->assertSame('Admin', User::find($this->admin->id)->role);
    }

    public function test_the_last_active_administrator_cannot_be_deactivated(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->admin->id}/status", ['status' => 'Inactive'])
            ->assertStatus(422);

        $this->assertSame('Active', User::find($this->admin->id)->status);
    }

    public function test_a_department_can_be_reassigned_without_touching_the_role(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/department", ['department_id' => 1])
            ->assertOk();

        $account = User::find($this->employee->id);

        $this->assertSame(1, (int) $account->department_id);
        $this->assertSame('Employee', $account->role, 'a transfer is not a promotion');
    }

    public function test_an_account_can_be_deactivated_and_reactivated(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/status", ['status' => 'Inactive'])
            ->assertOk();

        $this->assertSame('Inactive', User::find($this->employee->id)->status);

        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/status", ['status' => 'Active'])
            ->assertOk();

        $this->assertSame('Active', User::find($this->employee->id)->status);
    }

    public function test_the_account_history_comes_from_the_audit_log(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/admin/accounts/{$this->employee->id}/role", ['role' => 'Department Head'])
            ->assertOk();

        $response = $this->actingAs($this->admin)->getJson("/admin/accounts/{$this->employee->id}/history");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('account.email', 'nena@nu-lipa.edu.ph');

        $this->assertNotEmpty($response->json('history'), 'the account history must show what was recorded');
    }

    /* ═══════════════════ 2.8 — registration ═══════════════════ */

    public function test_registration_attaches_an_account_to_an_employee_record(): void
    {
        DB::table('employee_numbers')->insert([
            ['id' => 5, 'Employee_number' => 'EMP-5', 'Full_Name' => 'Maria Lopez', 'Email' => 'maria@nu-lipa.edu.ph',
             'Department_id' => 2, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->post('/register', [
            'unit_heads_number'   => 'EMP-5',
            'email'               => 'maria@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertRedirect('/login');

        $account = User::where('email', 'maria@nu-lipa.edu.ph')->first();

        $this->assertNotNull($account);
        $this->assertSame(5, (int) $account->employee_numbers_id);
        $this->assertSame('Employee', $account->role, 'registration grants no role beyond Employee');
        $this->assertSame('Active', $account->status);
    }

    public function test_a_record_without_an_official_email_can_still_register(): void
    {
        // Added the way the form now does it: no email on the masterlist record.
        $payload = $this->employeePayload();
        unset($payload['email']);

        $this->actingAs($this->admin)->post('/admin/employees', $payload)->assertRedirect('/admin/employees');

        // The employee supplies their own address at registration and the
        // account attaches to the record exactly as before.
        $this->post('/register', [
            'unit_heads_number'   => 'EMP-3',
            'email'               => 'juan@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertRedirect('/login');

        $account = User::where('email', 'juan@nu-lipa.edu.ph')->first();

        $this->assertNotNull($account, 'a record with no official email must still be usable');
        $this->assertSame(
            (int) DB::table('employee_numbers')->where('Employee_number', 'EMP-3')->value('id'),
            (int) $account->employee_numbers_id
        );
        $this->assertSame('Employee', $account->role, 'registration grants no role beyond Employee');
    }

    public function test_registration_never_promotes_the_first_person_in_a_department(): void
    {
        DB::table('employee_numbers')->insert([
            ['id' => 6, 'Employee_number' => 'EMP-6', 'Full_Name' => 'Junior Staff', 'Department_id' => 1,
             'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Department 1 - the Facilities office - has no Department Head account
        // at all. Registering into it used to make the first person who signed
        // up its Head, which handed them approval authority nobody granted.
        $this->post('/register', [
            'unit_heads_number'   => 'EMP-6',
            'email'               => 'junior@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertRedirect('/login');

        $this->assertSame('Employee', User::where('email', 'junior@nu-lipa.edu.ph')->value('role'));
    }

    public function test_the_first_account_is_still_an_administrator(): void
    {
        User::query()->delete();

        DB::table('employee_numbers')->insert([
            ['id' => 7, 'Employee_number' => 'EMP-7', 'Full_Name' => 'First Officer', 'Department_id' => 1,
             'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->post('/register', [
            'unit_heads_number'   => 'EMP-7',
            'email'               => 'first@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertRedirect('/login');

        $this->assertSame('Admin', User::where('email', 'first@nu-lipa.edu.ph')->value('role'));
    }

    public function test_an_unknown_employee_number_cannot_register(): void
    {
        $this->post('/register', [
            'unit_heads_number'   => 'EMP-404',
            'email'               => 'nobody@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertSessionHasErrors('unit_heads_number');

        $this->assertNull(User::where('email', 'nobody@nu-lipa.edu.ph')->first());
    }

    public function test_an_inactive_employee_record_cannot_register(): void
    {
        DB::table('employee_numbers')->insert([
            ['id' => 8, 'Employee_number' => 'EMP-8', 'Full_Name' => 'Former Staff', 'Department_id' => 2,
             'status' => 'Inactive', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->post('/register', [
            'unit_heads_number'   => 'EMP-8',
            'email'               => 'former@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertSessionHasErrors('unit_heads_number');

        $this->assertNull(User::where('email', 'former@nu-lipa.edu.ph')->first());
    }

    public function test_an_employee_number_cannot_open_a_second_account(): void
    {
        $this->post('/register', [
            'unit_heads_number'   => 'EMP-2',
            'email'               => 'someone.else@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertSessionHasErrors('unit_heads_number');

        $this->assertNull(User::where('email', 'someone.else@nu-lipa.edu.ph')->first());
    }

    public function test_the_official_email_on_the_record_is_the_one_that_must_be_used(): void
    {
        DB::table('employee_numbers')->where('id', 2)->update(['Email' => 'nena@nu-lipa.edu.ph']);
        User::query()->where('id', $this->employee->id)->delete();

        $this->post('/register', [
            'unit_heads_number'   => 'EMP-2',
            'email'               => 'someone.elses.inbox@nu-lipa.edu.ph',
            'password'            => 'Correct-Horse-Battery-9',
            'password_confirmation' => 'Correct-Horse-Battery-9',
        ])->assertSessionHasErrors('email');

        $this->assertNull(User::where('email', 'someone.elses.inbox@nu-lipa.edu.ph')->first());
    }

    /* ═══════════════════ 2.9 — authorization ═══════════════════ */

    public function test_an_employee_cannot_open_employee_management(): void
    {
        $this->actingAs($this->employee)->get('/admin/employees')->assertStatus(403);
        $this->actingAs($this->employee)->get('/admin/employees/template')->assertStatus(403);
        $this->actingAs($this->employee)->get('/admin/employees/export')->assertStatus(403);
    }

    public function test_an_employee_cannot_write_to_the_masterlist(): void
    {
        $this->actingAs($this->employee)
            ->post('/admin/employees', $this->employeePayload())
            ->assertStatus(403);

        $this->assertSame(0, DB::table('employee_numbers')->where('Employee_number', 'EMP-3')->count());
    }

    public function test_an_employee_cannot_change_their_own_role_or_department(): void
    {
        $this->actingAs($this->employee)
            ->postJson("/admin/accounts/{$this->employee->id}/role", ['role' => 'Admin'])
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->postJson("/admin/accounts/{$this->employee->id}/department", ['department_id' => 1])
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->postJson("/admin/accounts/{$this->employee->id}/status", ['status' => 'Inactive'])
            ->assertStatus(403);

        $account = User::find($this->employee->id);

        $this->assertSame('Employee', $account->role);
        $this->assertSame(2, (int) $account->department_id);
    }

    public function test_an_employee_cannot_upload_a_masterlist(): void
    {
        $csv = "Employee Number,Full Name,Department\nEMP-99,Sneaky Insert,Registrar\n";

        $this->actingAs($this->employee)
            ->post('/admin/employees/import', ['file' => UploadedFile::fake()->createWithContent('employees.csv', $csv)])
            ->assertStatus(403);

        $this->assertSame(0, DB::table('employee_numbers')->where('Employee_number', 'EMP-99')->count());
    }

    public function test_a_visitor_with_no_session_is_sent_to_the_landing_page(): void
    {
        $this->get('/admin/employees')->assertRedirect('/');
    }

    /* ═══════════ 2.10 — eligibility and unmatched accounts ═══════════ */

    public function test_the_directory_shows_who_is_eligible_to_register(): void
    {
        DB::table('employee_numbers')->insert([
            ['id' => 4, 'Employee_number' => 'EMP-4', 'Full_Name' => 'Ana Cruz', 'Department_id' => 2, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'Employee_number' => 'EMP-5', 'Full_Name' => 'Retired Person', 'Department_id' => 2, 'status' => 'Inactive', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/employees');

        $response->assertOk()
            ->assertSee('Eligible to register')
            ->assertSee('Not eligible')
            ->assertSee('The employee record is Inactive', false)
            ->assertSee('Already registered');
    }

    public function test_an_account_whose_employee_record_is_not_eligible_is_flagged(): void
    {
        DB::table('employee_numbers')->where('id', 2)->update(['status' => 'Inactive']);

        $response = $this->actingAs($this->admin)->get('/admin/employees?tab=accounts');

        $response->assertOk()
            ->assertSee('Employee record inactive')
            ->assertSee('no longer Active');
    }

    /* ═══════════ 2.11 — duplicate and case handling ═══════════ */

    public function test_duplicate_rows_are_reported_even_when_the_first_row_also_has_an_error(): void
    {
        $csv = "Employee Number,Full Name,Department\n"
            . "EMP-41,Ana Cruz,No Such Office\n"
            . "EMP-41,Ana Again,Registrar\n";

        $this->actingAs($this->admin)
            ->post('/admin/employees/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
            ])
            ->assertOk()
            ->assertJsonPath('summary.ready', 0)
            ->assertJsonPath('summary.invalid', 2)
            ->assertJsonPath('rows.1.errors.0', 'Employee number EMP-41 appears more than once in this file (also on line 2).');
    }

    public function test_an_existing_employee_number_is_matched_whatever_its_casing(): void
    {
        DB::table('employee_numbers')->where('id', 2)->update(['Employee_number' => 'emp-2']);

        $csv = "Employee Number,Full Name,Department\nEMP-2,Somebody Else,Facilities\n";

        $this->actingAs($this->admin)
            ->post('/admin/employees/import', ['file' => UploadedFile::fake()->createWithContent('employees.csv', $csv)])
            ->assertStatus(422)
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertSame(1, DB::table('employee_numbers')->whereRaw('UPPER(Employee_number) = ?', ['EMP-2'])->count());
        $this->assertSame('Nena Santos', DB::table('employee_numbers')->where('id', 2)->value('Full_Name'));
    }

    public function test_a_duplicate_employee_number_is_refused_whatever_its_casing(): void
    {
        DB::table('employee_numbers')->where('id', 2)->update(['Employee_number' => 'emp-2']);

        $this->actingAs($this->admin)
            ->post('/admin/employees', $this->employeePayload(['employee_number' => 'EMP-2']))
            ->assertSessionHasErrors('employee_number');

        $this->assertSame(1, DB::table('employee_numbers')->whereRaw('UPPER(Employee_number) = ?', ['EMP-2'])->count());
    }

    public function test_an_unusable_import_file_is_recorded_in_the_audit_log(): void
    {
        $csv = "Employee Number,Full Name\nEMP-60,Ana Cruz\n";

        $this->actingAs($this->admin)
            ->post('/admin/employees/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'            => $this->admin->id,
            'action_description' => 'Employee masterlist import rejected - nothing was written',
        ]);
    }
}
