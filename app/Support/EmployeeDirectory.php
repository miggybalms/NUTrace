<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The employee masterlist (employee_numbers) and the CSV that feeds it.
 *
 * One deliberate decision shapes this class: the masterlist is NOT an account.
 * An employee record can exist on its own — the office imports the official
 * list every semester, long before most of those people ever register — while
 * a NU Trace account is a separate row in `users` that points back at it. So
 * "add an employee" here never creates a login, never sets a role and never
 * grants privileges. Registration is still the only thing that creates an
 * account (see Recommendation 2.8), and it can only attach itself to an
 * employee record that already exists here.
 *
 * Everything the office can do - add, edit, import, export - is guarded on the
 * server by Access::canManageEmployees(); the page is a convenience, not the
 * control.
 */
class EmployeeDirectory
{
    /** Page size for the directory and the account list. */
    public const PER_PAGE = 10;

    /** Statuses the masterlist allows. */
    public const STATUSES = ['Active', 'Inactive'];

    /**
     * The headings the office may use, and the column each one means.
     *
     * A spreadsheet the office already has uses its own wording, so the
     * accepted headings are generous; matching ignores case, spaces and
     * underscores.
     */
    public const COLUMN_ALIASES = [
        'employee_number' => ['employee number', 'employee_number', 'employee id', 'employee no', 'employee', 'id'],
        'full_name'       => ['full name', 'full_name', 'name', 'employee name', 'fullname'],
        'email'           => ['email', 'email address', 'work email', 'e-mail'],
        'department'      => ['department', 'department name', 'office', 'unit'],
        'status'          => ['status', 'employee status', 'record status'],
    ];

    /** Columns the file must contain. */
    public const REQUIRED_COLUMNS = ['employee_number', 'full_name', 'department'];

    /**
     * The heading each column is written as.
     *
     * Used for the template and for the message that names a missing column, so
     * the office is told "Department" - the word on the template - rather than
     * the internal key that happens to describe it here.
     */
    public const COLUMN_LABELS = [
        'employee_number' => 'Employee Number',
        'full_name'       => 'Full Name',
        'email'           => 'Email',
        'department'      => 'Department',
        'status'          => 'Status',
    ];

    /** The template's own headings, in order. */
    public const TEMPLATE_HEADINGS = [
        self::COLUMN_LABELS['employee_number'],
        self::COLUMN_LABELS['full_name'],
        self::COLUMN_LABELS['email'],
        self::COLUMN_LABELS['department'],
        self::COLUMN_LABELS['status'],
    ];

    /* ═════════════════════════ read side ═════════════════════════ */

    /**
     * The directory: masterlist records, with the account (if any) attached.
     *
     * One query with left joins, so an employee who has never registered is
     * still a row — with an empty account column rather than being missing.
     */
    public static function directory(array $filters): LengthAwarePaginator
    {
        $query = DB::table('employee_numbers as e')
            ->leftJoin('departments as d', 'e.Department_id', '=', 'd.id')
            ->leftJoin('users as u', 'u.employee_numbers_id', '=', 'e.id')
            ->select([
                'e.id',
                'e.Employee_number',
                'e.Full_Name',
                'e.Email',
                'e.status',
                'd.id as department_id',
                'd.Name as department',
                'u.id as account_id',
                'u.email as account_email',
                'u.role as account_role',
                'u.status as account_status',
                'u.created_at as account_created_at',
            ]);

        self::applyDirectoryFilters($query, $filters);

        return $query
            ->orderBy('e.Full_Name')
            ->orderBy('e.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** The filters, read from the query string and made safe. */
    public static function filters(array $input): array
    {
        $department = self::text($input['department'] ?? '');
        $status     = self::text($input['status'] ?? '');
        $account    = self::text($input['account'] ?? 'all');

        return [
            'q'          => self::text($input['q'] ?? ''),
            'department' => $department === '' ? '' : (int) $department,
            'status'     => in_array($status, self::STATUSES, true) ? $status : '',
            'account'    => in_array($account, ['all', 'registered', 'unregistered'], true) ? $account : 'all',
        ];
    }

    /**
     * One query-string value as text.
     *
     * A filter is a value, not a list: '?status[]=' would otherwise reach the
     * validator as an array and turn an ordinary page load into a PHP warning.
     */
    private static function text($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** The same filters, applied to any employee_numbers query. */
    private static function applyDirectoryFilters($query, array $filters): void
    {
        Search::contains($query, [
            'e.Employee_number',
            'e.Full_Name',
            'e.Email',
            'd.Name',
            'u.email',
        ], (string) ($filters['q'] ?? ''));

        if (! empty($filters['department'])) {
            $query->where('e.Department_id', (int) $filters['department']);
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('e.status', $filters['status']);
        }

        if (($filters['account'] ?? 'all') === 'registered') {
            $query->whereNotNull('u.id');
        } elseif (($filters['account'] ?? 'all') === 'unregistered') {
            $query->whereNull('u.id');
        }
    }

    /** One masterlist record, or null. */
    public static function find(int $id): ?object
    {
        return DB::table('employee_numbers')->where('id', $id)->first();
    }

    /** Departments the office can assign, for the form's select. */
    public static function departments(): Collection
    {
        return DB::table('departments')->orderBy('Name')->get(['id', 'Name', 'status']);
    }

    /**
     * Whether this masterlist record may be used to register an account.
     *
     * Registration (routes/web.php, POST /register/verify) is the only place an
     * account is created, and it applies the same two rules this method
     * describes: the record must be Active, and no account may already point at
     * it. The directory shows the answer so the office does not have to guess
     * why an employee cannot sign up.
     *
     * @return array{eligible: bool, label: string, detail: string}
     */
    public static function eligibility(object $record): array
    {
        if (! empty($record->account_id)) {
            return [
                'eligible' => false,
                'label'    => 'Already registered',
                'detail'   => 'This employee already has a NU Trace account. Manage it on the Accounts tab.',
            ];
        }

        if (strtolower(trim((string) ($record->status ?? 'Active'))) !== 'active') {
            return [
                'eligible' => false,
                'label'    => 'Not eligible',
                'detail'   => 'The employee record is ' . ($record->status ?: 'Inactive') . '. Registration is refused until the office marks it Active.',
            ];
        }

        return [
            'eligible' => true,
            'label'    => 'Eligible to register',
            'detail'   => 'The employee may register an account with this employee number.',
        ];
    }

    /** Counters for the page's summary cards. */
    public static function counts(): array
    {
        $registered = DB::table('users')->distinct()->count('employee_numbers_id');

        return [
            'employees'    => DB::table('employee_numbers')->count(),
            'active'       => DB::table('employee_numbers')->where('status', 'Active')->count(),
            'inactive'     => DB::table('employee_numbers')->where('status', 'Inactive')->count(),
            'registered'   => $registered,
            'unregistered' => max(DB::table('employee_numbers')->count() - $registered, 0),
        ];
    }

    /* ═════════════════════════ write side ═════════════════════════ */

    /**
     * Add one employee record by hand.
     *
     * @return array{ok: bool, id?: int, errors?: array<string, string>}
     */
    public static function store(array $input, ?User $actor): array
    {
        $data = self::normalizeInput($input);

        $validator = Validator::make($data, self::rules(), [], self::attributeNames());

        self::addDuplicateNumberRule($validator, $data);
        self::addDuplicateEmailRule($validator, $data);

        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        try {
            $id = DB::transaction(function () use ($data, $actor) {
                $id = DB::table('employee_numbers')->insertGetId([
                    'Employee_number' => $data['employee_number'],
                    'Full_Name'       => $data['full_name'],
                    'Email'           => $data['email'] !== '' ? $data['email'] : null,
                    'Department_id'   => (int) $data['department_id'],
                    'status'          => $data['status'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                self::audit($actor, 'CREATE',
                    'Added employee record ' . $data['employee_number'] . ' (' . $data['full_name'] . ') to the masterlist.',
                    'Employee masterlist record #' . $id . ' created');

                return $id;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Two administrators saving the same employee at the same moment:
            // the database's unique index decides, and the second one is told
            // plainly rather than shown a 500.
            return ['ok' => false, 'errors' => ['employee_number' => self::duplicateMessage($e)]];
        }

        return ['ok' => true, 'id' => (int) $id];
    }

    /**
     * Edit one employee record.
     *
     * The employee number can be corrected (a typo in an imported list), but it
     * still has to stay unique. Editing a record never touches the account that
     * may point at it: the person's role, status and history are managed on the
     * accounts tab, deliberately, so an edit here cannot silently change what
     * someone is allowed to do.
     *
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public static function update(int $id, array $input, ?User $actor): array
    {
        $record = self::find($id);

        if (! $record) {
            return ['ok' => false, 'errors' => ['employee_number' => 'That employee record no longer exists.']];
        }

        $data = self::normalizeInput($input);

        $validator = Validator::make($data, self::rules(), [], self::attributeNames());

        self::addDuplicateNumberRule($validator, $data, $id);
        self::addDuplicateEmailRule($validator, $data, $id);

        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        try {
            DB::transaction(function () use ($id, $record, $data, $input, $actor) {
            $fields = [
                'Employee_number' => $data['employee_number'],
                'Full_Name'       => $data['full_name'],
                'Department_id'   => (int) $data['department_id'],
                'status'          => $data['status'],
                'updated_at'      => now(),
            ];

            // The employee form no longer carries an official email, and a
            // field the form does not send is not a field to erase: an edit
            // that says nothing about the address leaves the stored one alone.
            // (It can still be corrected by a caller that does send one.)
            if (array_key_exists('email', $input)) {
                $fields['Email'] = $data['email'] !== '' ? $data['email'] : null;
            }

            DB::table('employee_numbers')->where('id', $id)->update($fields);

            self::audit($actor, 'UPDATE',
                'Updated employee record ' . $record->Employee_number . ' (' . $record->Full_Name . ') in the masterlist.',
                'Employee masterlist record #' . $id . ' updated');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return ['ok' => false, 'errors' => ['employee_number' => self::duplicateMessage($e)]];
        }

        return ['ok' => true];
    }

    /** The one thing a unique-index failure means, in words the office can act on. */
    private static function duplicateMessage(\Throwable $e): string
    {
        \Log::warning('Employee record write refused by the database: ' . $e->getMessage());

        return 'Another employee record with the same employee number or email was saved a moment ago. Reload the page and check the list.';
    }

    /* ═══════════════════════ CSV: template ═══════════════════════ */

    /**
     * An empty file with the supported headings and one example row, so the
     * office can see what a good line looks like without guessing.
     */
    public static function template(): StreamedResponse
    {
        $department = DB::table('departments')->orderBy('id')->value('Name') ?? 'Registrar';

        $example = [
            'EMP-0001',
            'Juan Dela Cruz',
            'juan.dela.cruz@nu-lipa.edu.ph',
            $department,
            'Active',
        ];

        return Csv::download('employee-masterlist-template.csv', self::TEMPLATE_HEADINGS, [$example]);
    }

    /**
     * Every masterlist row the current filter selects, as export rows.
     *
     * The export follows the page's own filter, so "download what I am looking
     * at" downloads exactly that - not the whole table, and not only the page
     * on screen. Passwords and password hashes are never included.
     */
    public static function exportRows(array $filters): array
    {
        $query = DB::table('employee_numbers as e')
            ->leftJoin('departments as d', 'e.Department_id', '=', 'd.id')
            ->leftJoin('users as u', 'u.employee_numbers_id', '=', 'e.id')
            ->select([
                'e.Employee_number',
                'e.Full_Name',
                'e.Email',
                'e.status',
                'd.Name as department',
                'u.email as account_email',
                'u.role as account_role',
                'u.status as account_status',
            ]);

        self::applyDirectoryFilters($query, $filters);

        $rows = [];

        foreach ($query->orderBy('e.Full_Name')->orderBy('e.id')->get() as $row) {
            // The official address on the employee record wins; when it was
            // never recorded, the account's address is exported instead of an
            // empty cell - otherwise "download, correct, upload" hands the
            // office a file with nobody's email in it.
            $email = trim((string) ($row->Email ?? ''));
            if ($email === '') {
                $email = trim((string) ($row->account_email ?? ''));
            }

            $rows[] = [
                $row->Employee_number,
                $row->Full_Name,
                $email,
                (string) ($row->department ?? ''),
                (string) $row->status,
                $row->account_email ? 'Yes' : 'No',
                (string) ($row->account_role ?? ''),
                (string) ($row->account_status ?? ''),
            ];
        }

        return $rows;
    }

    public static function exportHeadings(): array
    {
        return [
            'Employee Number', 'Full Name', 'Email', 'Department', 'Status',
            'Has Account', 'Account Role', 'Account Status',
        ];
    }

    /* ═════════════════════════ CSV: import ═════════════════════════ */

    /**
     * Read an uploaded file, check every row, and write nothing.
     *
     * This is the whole point of the preview stage: the office sees what would
     * happen - which rows import, which are skipped because the employee is
     * already on the list, and which rows are wrong and why - before anything
     * is written. Nothing here touches the database except to resolve
     * departments and look for duplicates.
     *
     * @return array{ok: bool, message?: string, missing?: string[], columns?: array, rows?: array, summary?: array}
     */
    public static function preview(UploadedFile $file): array
    {
        $parsed = self::parse($file);

        if (! $parsed['ok']) {
            return $parsed;
        }

        return $parsed;
    }

    /**
     * Import the valid rows of an already-checked file.
     *
     * The file is validated again here rather than trusting the browser's copy
     * of the preview: the answer the page shows is a convenience, and the only
     * thing that decides what lands in the database is this method.
     *
     * Rows already in the masterlist are skipped, never overwritten - an import
     * cannot rename an employee or move them to another department by accident.
     * Unexpected errors roll the whole batch back, so a failed import leaves no
     * half-imported list behind.
     *
     * @return array{ok: bool, message?: string, imported?: int, skipped?: int, invalid?: int, errors?: array}
     */
    public static function commit(UploadedFile $file, ?User $actor): array
    {
        $preview = self::parse($file);

        if (! $preview['ok']) {
            return [
                'ok'      => false,
                'message' => $preview['message'] ?? 'The file could not be read.',
                'missing' => $preview['missing'] ?? [],
            ];
        }

        $ready = array_values(array_filter($preview['rows'], fn ($row) => $row['action'] === 'import'));

        if (empty($ready)) {
            return [
                'ok'        => false,
                'message'   => 'There is nothing to import: every row was either already on the masterlist or had an error.',
                'imported'  => 0,
                'skipped'   => $preview['summary']['skipped'],
                'invalid'   => $preview['summary']['invalid'],
                'errors'    => array_values(array_filter(
                    array_map(fn ($row) => $row['errors'] === [] ? null : ['line' => $row['line'], 'errors' => $row['errors']], $preview['rows'])
                )),
            ];
        }

        try {
            $imported = DB::transaction(function () use ($ready, $actor, $file) {
                $now  = now();
                $rows = [];

                foreach ($ready as $row) {
                    $rows[] = [
                        'Employee_number' => $row['employee_number'],
                        'Full_Name'       => $row['full_name'],
                        'Email'           => $row['email'] !== '' ? $row['email'] : null,
                        'Department_id'   => (int) $row['department_id'],
                        'status'          => $row['status'],
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }

                // Chunked so a long list is not one enormous statement.
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('employee_numbers')->insert($chunk);
                }

                // One summary entry per import, not one per employee: the office
                // needs to see that the list changed and who did it, and 174
                // identical rows would bury everything else in the log.
                self::audit(
                    $actor,
                    'CREATE',
                    'Imported ' . count($rows) . ' employee record(s) from ' . $file->getClientOriginalName(),
                    'Employee masterlist import: ' . count($rows) . ' record(s) added'
                );

                return count($rows);
            });
        } catch (\Throwable $e) {
            // The transaction rolled back: nothing from this file was written.
            \Log::error('Employee import failed: ' . $e->getMessage());

            return [
                'ok'      => false,
                'message' => 'The import was rolled back, so nothing was written. No employee records were changed. Please try again.',
            ];
        }

        return [
            'ok'       => true,
            'imported' => $imported,
            'skipped'  => $preview['summary']['skipped'],
            'invalid'  => $preview['summary']['invalid'],
            'errors'   => array_values(array_filter(
                array_map(fn ($row) => $row['errors'] === [] ? null : ['line' => $row['line'], 'errors' => $row['errors']], $preview['rows'])
            )),
        ];
    }

    /**
     * Read and check the file without writing anything.
     *
     * @return array{ok: bool, message?: string, missing?: string[], columns?: array, rows?: array, summary?: array}
     */
    private static function parse(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return ['ok' => false, 'message' => 'The file did not upload completely. Please choose it again.'];
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'], true)) {
            return ['ok' => false, 'message' => 'Only a CSV file can be imported. Download the template and fill that in.'];
        }

        $read = Csv::read($file->getRealPath());

        if (empty($read['headers'])) {
            return ['ok' => false, 'message' => 'The file is empty. It needs a first row with the column names.'];
        }

        $map = self::mapHeadings($read['headers']);

        $missing = array_values(array_filter(
            self::REQUIRED_COLUMNS,
            fn (string $column) => ! isset($map[$column])
        ));

        if (! empty($missing)) {
            return [
                'ok'      => false,
                'message' => 'The file is missing required column(s): ' . implode(', ', array_map(
                    fn ($column) => self::COLUMN_LABELS[$column] ?? $column,
                    $missing
                )) . '. Download the template to see the expected headings.',
                'missing' => $missing,
            ];
        }

        if (count($read['rows']) > Csv::MAX_ROWS) {
            return [
                'ok'      => false,
                'message' => 'That file has more than ' . Csv::MAX_ROWS . ' rows. Split it and import it in parts.',
            ];
        }

        $departments = self::departmentLookup();

        $rows        = [];
        $seenNumbers = [];
        $seenEmails  = [];
        $summary     = ['total' => 0, 'ready' => 0, 'skipped' => 0, 'invalid' => 0];

        foreach ($read['rows'] as $entry) {
            $summary['total']++;

            // Every column starts empty, so a file that leaves out the optional
            // ones (Email, Status) is read the same way as one that has them.
            $values = [
                'employee_number' => '',
                'full_name'       => '',
                'email'           => '',
                'department'      => '',
                'status'          => '',
            ];

            foreach ($map as $column => $heading) {
                $values[$column] = trim((string) ($entry['values'][$heading] ?? ''));
            }

            // The employee number is what decides whether this row is new; it is
            // normalised the same way the manual form does, so 'emp-1' and
            // 'EMP-1 ' cannot arrive as two different people.
            $values['employee_number'] = strtoupper($values['employee_number']);

            $departmentName = $values['department'];
            $departmentId   = null;

            if ($departmentName !== '') {
                $key = self::key($departmentName);
                $departmentId = $departments[$key] ?? (ctype_digit($departmentName) ? (int) $departmentName : null);
            }

            $errors = [];

            if ($values['employee_number'] === '') {
                $errors[] = 'Employee number is required.';
            } elseif (mb_strlen($values['employee_number']) > 20) {
                $errors[] = 'Employee number cannot be longer than 20 characters.';
            }

            if ($values['employee_number'] !== '') {
                if (isset($seenNumbers[$values['employee_number']])) {
                    $errors[] = 'Employee number ' . $values['employee_number'] . ' appears more than once in this file (also on line ' . $seenNumbers[$values['employee_number']] . ').';
                } else {
                    // Recorded even when this row has another error, so a later
                    // row with the same number is still reported as a duplicate
                    // instead of importing one of two identical people.
                    $seenNumbers[$values['employee_number']] = $entry['line'];
                }
            }

            if ($values['full_name'] === '') {
                $errors[] = 'Full name is required.';
            } elseif (mb_strlen($values['full_name']) > 100) {
                $errors[] = 'Full name cannot be longer than 100 characters.';
            }

            if ($values['email'] !== '') {
                if (! filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 100) {
                    $errors[] = 'Email "' . $values['email'] . '" is not a valid email address.';
                } elseif (isset($seenEmails[strtolower($values['email'])])) {
                    $errors[] = 'Email ' . $values['email'] . ' appears more than once in this file (also on line ' . $seenEmails[strtolower($values['email'])] . ').';
                } else {
                    $seenEmails[strtolower($values['email'])] = $entry['line'];
                }
            }

            if ($departmentName === '') {
                $errors[] = 'Department is required.';
            } elseif ($departmentId === null) {
                $errors[] = 'Department "' . $departmentName . '" is not in the department list.';
            }

            $status = $values['status'] !== '' ? ucfirst(strtolower($values['status'])) : 'Active';
            if (! in_array($status, self::STATUSES, true)) {
                $errors[] = 'Status must be Active or Inactive.';
            }

            $action = 'import';

            if (empty($errors)) {
                // Already on the masterlist? Then the file says nothing new
                // about this person, and the existing record is left exactly as
                // it is. Only a deliberate edit on the directory changes it.
                if (DB::table('employee_numbers')->whereRaw('UPPER(' . self::column('Employee_number') . ') = ?', [$values['employee_number']])->exists()) {
                    $action = 'skip';
                    $errors[] = 'Already on the masterlist - existing record left unchanged.';
                } elseif ($values['email'] !== '' && self::emailTaken($values['email'])) {
                    $action = 'skip';
                    $errors[] = 'That email is already on another employee record.';
                }
            }

            if ($action === 'import' && empty($errors)) {
                $summary['ready']++;
            } elseif ($action === 'skip') {
                $summary['skipped']++;
            } else {
                $action = 'error';
                $summary['invalid']++;
            }

            $rows[] = [
                'line'            => $entry['line'],
                'employee_number' => $values['employee_number'],
                'full_name'       => $values['full_name'],
                'email'           => $values['email'],
                'department'      => $departmentName,
                'department_id'   => $departmentId,
                'status'          => $status,
                'action'          => $action,
                'errors'          => $errors,
            ];
        }

        return [
            'ok'      => true,
            'columns' => array_map(fn (string $heading) => $heading, $read['headers']),
            'rows'    => $rows,
            'summary' => $summary,
        ];
    }

    /* ═══════════════════ shared validation pieces ═══════════════════ */

    /** Rules shared by the manual form and the import. */
    public static function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:20'],
            'full_name'       => ['required', 'string', 'max:100'],
            'email'           => ['nullable', 'email', 'max:100'],
            'department_id'   => ['required', 'integer', Rule::exists('departments', 'id')->where('status', 'Active')],
            'status'          => ['required', Rule::in(self::STATUSES)],
        ];
    }

    public static function attributeNames(): array
    {
        return [
            'employee_number' => 'employee number',
            'full_name'       => 'full name',
            'email'           => 'email address',
            'department_id'   => 'department',
            'status'          => 'status',
        ];
    }

    /**
     * One employee number belongs to one employee record, whatever the casing.
     *
     * New records are stored upper-case, but older rows may not be: comparing
     * with UPPER() is what stops 'emp-7' and 'EMP-7' becoming two records that
     * registration can only ever attach one account to.
     */
    private static function addDuplicateNumberRule($validator, array $data, ?int $ignoreId = null): void
    {
        $validator->after(function ($validator) use ($data, $ignoreId) {
            $number = strtoupper(trim((string) ($data['employee_number'] ?? '')));

            if ($number === '') {
                return;
            }

            $query = DB::table('employee_numbers')
                ->whereRaw('UPPER(' . self::column('Employee_number') . ') = ?', [$number]);

            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }

            $clash = $query->first(['Employee_number', 'Full_Name']);

            if ($clash) {
                $validator->errors()->add(
                    'employee_number',
                    'The employee number ' . $number . ' already belongs to employee record '
                    . $clash->Employee_number . ' (' . $clash->Full_Name . ').'
                );
            }
        });
    }

    /**
     * One email belongs to one employee record, whatever the casing.
     *
     * The unique index on the column is case-sensitive and NULL-friendly, so it
     * stops exact duplicates but not 'Juan@nu.edu.ph' next to 'juan@nu.edu.ph'.
     * That check happens here, where it can also name the record it clashes
     * with.
     */
    private static function addDuplicateEmailRule($validator, array $data, ?int $ignoreId = null): void
    {
        $validator->after(function ($validator) use ($data, $ignoreId) {
            $email = trim((string) ($data['email'] ?? ''));

            if ($email === '') {
                return;
            }

            $query = DB::table('employee_numbers')
                ->whereRaw('LOWER(' . self::column('Email') . ') = ?', [strtolower($email)]);

            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }

            $clash = $query->first(['Employee_number', 'Full_Name']);

            if ($clash) {
                $validator->errors()->add(
                    'email',
                    'The email address ' . $email . ' already belongs to employee record '
                    . $clash->Employee_number . ' (' . $clash->Full_Name . ').'
                );
            }
        });
    }

    /**
     * Trim and normalise what the form posted.
     *
     * Employee numbers are stored upper-case ('emp-1' becomes 'EMP-1'), which
     * is the form registration compares against - it upper-cases what the
     * applicant types too, so the two always meet. An unrecognised status is
     * left as it is, so the validator can refuse it by name instead of quietly
     * turning a typo into 'Active'.
     */
    private static function normalizeInput(array $input): array
    {
        $rawStatus = trim((string) ($input['status'] ?? ''));

        return [
            'employee_number' => strtoupper(trim((string) ($input['employee_number'] ?? ''))),
            'full_name'       => trim((string) ($input['full_name'] ?? '')),
            'email'           => trim((string) ($input['email'] ?? '')),
            'department_id'   => trim((string) ($input['department_id'] ?? '')),
            'status'          => $rawStatus === '' ? 'Active' : ucfirst(strtolower($rawStatus)),
        ];
    }

    /** The canonical column each heading in the file means. */
    public static function mapHeadings(array $headings): array
    {
        $map = [];

        foreach ($headings as $heading) {
            $key = self::key($heading);

            foreach (self::COLUMN_ALIASES as $column => $aliases) {
                if (isset($map[$column])) {
                    continue;
                }

                if (in_array($key, array_map([self::class, 'key'], $aliases), true)) {
                    $map[$column] = $heading;
                }
            }
        }

        return $map;
    }

    /**
     * A masterlist column, quoted the way the current connection needs it.
     *
     * The table was created with mixed-case names - "Employee_number" and
     * "Email". PostgreSQL keeps that casing, so an unquoted reference in raw
     * SQL folds to lower case and stops matching ('column "employee_number"
     * does not exist'). The query grammar quotes for whichever driver is in
     * use, which keeps the same raw comparison working everywhere.
     */
    public static function column(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    /** Lower-case, no spaces and no underscores: 'Employee_Number' == 'employee number'. */
    private static function key(string $value): string
    {
        return preg_replace('/[\s_\-]+/', '', strtolower(trim($value))) ?? '';
    }

    private static function departmentLookup(): array
    {
        $lookup = [];

        foreach (DB::table('departments')->get(['id', 'Name']) as $department) {
            $lookup[self::key((string) $department->Name)] = (int) $department->id;
        }

        return $lookup;
    }

    private static function emailTaken(string $email): bool
    {
        return DB::table('employee_numbers')
            ->whereRaw('LOWER(' . self::column('Email') . ') = ?', [strtolower($email)])
            ->exists();
    }

    /** One entry in the existing audit log, written the way the rest of the app writes. */
    public static function audit(?User $actor, string $actionType, string $notes, string $description): void
    {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => $actor?->id,
                'request_id'         => null,
                'asset_id'           => null,
                'action_type'        => $actionType,
                'notes'              => $notes,
                'action_description' => $description,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // A record kept is worth more than a log entry lost: the write above
            // has already happened by the time this runs, and losing the note
            // must not undo it.
            \Log::warning('Employee audit entry failed: ' . $e->getMessage());
        }
    }
}
