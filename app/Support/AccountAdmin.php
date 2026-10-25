<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The accounts that can sign in to NU Trace.
 *
 * Kept apart from the employee masterlist on purpose. An employee record is a
 * fact about the institution ("this person works in the Registrar"); an
 * account is a decision about the system ("this person may approve disposal
 * requests"). Only the office grants the second one, one account at a time, and
 * every change is written to the audit log with the name of whoever made it.
 *
 * Nothing here assigns a role automatically. A promotion in the university is
 * not a promotion in the system, and a department transfer never hands someone
 * approval authority they were not given - that is exactly what requirement
 * 2.7 and 3.5 ask for.
 */
class AccountAdmin
{
    public const PER_PAGE = 10;

    /** The account-status filter, and the two values it allows. */
    public const STATUSES = [Access::STATUS_ACTIVE, Access::STATUS_INACTIVE];

    /* ═════════════════════════ read side ═════════════════════════ */

    /** Registered accounts, with the employee record behind each one. */
    public static function accounts(array $filters): LengthAwarePaginator
    {
        $query = DB::table('users as u')
            ->leftJoin('employee_numbers as e', 'u.employee_numbers_id', '=', 'e.id')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->select([
                'u.id',
                'u.email',
                'u.role',
                'u.status',
                'u.created_at',
                'u.department_id',
                'e.Employee_number',
                'e.Full_Name',
                'e.status as employee_status',
                'd.Name as department',
            ]);

        Search::contains($query, [
            'u.email',
            'e.Full_Name',
            'e.Employee_number',
            'd.Name',
        ], (string) ($filters['q'] ?? ''));

        if (($filters['role'] ?? '') !== '' && Access::isKnownRole($filters['role'])) {
            $query->where('u.role', $filters['role']);
        }

        if (! empty($filters['department'])) {
            $query->where('u.department_id', (int) $filters['department']);
        }

        if (($filters['status'] ?? '') !== '' && in_array($filters['status'], self::STATUSES, true)) {
            $query->where('u.status', $filters['status']);
        }

        return $query
            ->orderByRaw("CASE WHEN LOWER(u.status) = 'active' THEN 0 ELSE 1 END")
            ->orderBy('u.email')
            ->paginate(self::PER_PAGE, ['*'], 'accounts_page')
            ->withQueryString();
    }

    /** Account filters, read from the query string and made safe. */
    public static function filters(array $input): array
    {
        $role       = self::text($input['role'] ?? '');
        $status     = self::text($input['status'] ?? '');
        $department = self::text($input['department'] ?? '');

        return [
            'q'          => self::text($input['q'] ?? ''),
            'role'       => Access::isKnownRole($role) ? $role : '',
            'status'     => in_array($status, self::STATUSES, true) ? $status : '',
            'department' => $department === '' ? '' : (int) $department,
        ];
    }

    /** One query-string value as text (a filter is a value, not a list). */
    private static function text($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function find(int $id): ?object
    {
        return DB::table('users as u')
            ->leftJoin('employee_numbers as e', 'u.employee_numbers_id', '=', 'e.id')
            ->where('u.id', $id)
            ->first([
                'u.id', 'u.email', 'u.role', 'u.status', 'u.department_id',
                'e.Employee_number', 'e.Full_Name',
            ]);
    }

    /** How many accounts hold each role, for the summary cards. */
    public static function counts(): array
    {
        $counts = ['total' => DB::table('users')->count()];

        foreach (Access::ROLES as $role) {
            $counts[$role] = DB::table('users')->where('role', $role)->count();
        }

        $counts['inactive'] = DB::table('users')->whereRaw('LOWER(status) = ?', ['inactive'])->count();

        // Accounts with no matching masterlist row, or whose employee record is
        // no longer Active. Shown so the office can review the account itself;
        // nothing is unlinked or deactivated automatically.
        $counts['unmatched'] = DB::table('users as u')
            ->leftJoin('employee_numbers as e', 'u.employee_numbers_id', '=', 'e.id')
            ->whereNull('e.id')
            ->count();

        $counts['inactive_employee'] = DB::table('users as u')
            ->join('employee_numbers as e', 'u.employee_numbers_id', '=', 'e.id')
            ->whereRaw('LOWER(e.status) != ?', ['active'])
            ->count();

        return $counts;
    }

    /**
     * The account's own history, read from the existing audit log.
     *
     * Requirement 2.6 asks for "relevant account history where supported by the
     * existing audit logs" - so this shows the trail that is already there
     * instead of introducing a second history table. Two kinds of entry belong
     * to an account: what it did (user_id is the account) and what was done to
     * it (a role, department or status change, which the account's own id names
     * in the description because the row records the administrator who made the
     * change, not the person it was made about).
     */
    public static function history(int $userId): array
    {
        return DB::table('audit_logs')
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('action_description', 'like', 'Account #' . $userId . ' %');
            })
            ->orderByDesc('created_at')
            ->limit(25)
            ->get(['id', 'action_type', 'notes', 'action_description', 'created_at'])
            ->all();
    }

    /* ═════════════════════════ write side ═════════════════════════ */

    /**
     * Change one account's system role.
     *
     * @return array{ok: bool, message: string}
     */
    public static function changeRole(int $userId, string $role, ?User $actor): array
    {
        $role = trim($role);

        if (! Access::isKnownRole($role)) {
            return ['ok' => false, 'message' => 'That is not a role NU Trace recognises.'];
        }

        $account = self::find($userId);

        if (! $account) {
            return ['ok' => false, 'message' => 'That account no longer exists.'];
        }

        if (trim((string) $account->role) === $role) {
            return ['ok' => true, 'message' => self::displayName($account) . ' is already ' . $role . '.'];
        }

        // Demoting the last administrator would leave the system with nobody
        // able to manage employees or decide requests.
        if (self::isLastActiveAdmin($account) && $role !== 'Admin') {
            return [
                'ok'      => false,
                'message' => 'This is the last active Administrator account, so its role cannot be changed. Give another account the Administrator role first.',
            ];
        }

        DB::transaction(function () use ($userId, $account, $role, $actor) {
            DB::table('users')->where('id', $userId)->update([
                'role'       => $role,
                'updated_at' => now(),
            ]);

            EmployeeDirectory::audit(
                $actor,
                'UPDATE',
                'Changed role for ' . self::displayName($account) . ' from ' . $account->role . ' to ' . $role . '.',
                'Account #' . $userId . ' role changed to ' . $role
            );
        });

        return ['ok' => true, 'message' => self::displayName($account) . ' is now ' . $role . '.'];
    }

    /**
     * Move an account to another department.
     *
     * The account keeps its identity, its history and its requests: only the
     * department changes. Its authority does not change with it - a Department
     * Head who moves to another office is still a Department Head, and an
     * employee who moves is still an employee.
     *
     * @return array{ok: bool, message: string}
     */
    public static function changeDepartment(int $userId, int $departmentId, ?User $actor): array
    {
        $account = self::find($userId);

        if (! $account) {
            return ['ok' => false, 'message' => 'That account no longer exists.'];
        }

        $validated = Validator::make(
            ['department_id' => $departmentId],
            ['department_id' => ['required', 'integer', Rule::exists('departments', 'id')]],
            ['department_id.exists' => 'That department is not in the department list.']
        );

        if ($validated->fails()) {
            return ['ok' => false, 'message' => $validated->errors()->first('department_id')];
        }

        $from = DB::table('departments')->where('id', $account->department_id)->value('Name');
        $to   = DB::table('departments')->where('id', $departmentId)->value('Name');

        if ((int) $account->department_id === $departmentId) {
            return ['ok' => true, 'message' => self::displayName($account) . ' is already in ' . $to . '.'];
        }

        DB::transaction(function () use ($userId, $account, $departmentId, $from, $to, $actor) {
            DB::table('users')->where('id', $userId)->update([
                'department_id' => $departmentId,
                'updated_at'    => now(),
            ]);

            EmployeeDirectory::audit(
                $actor,
                'UPDATE',
                'Moved ' . self::displayName($account) . ' from ' . ($from ?: 'no department') . ' to ' . $to . '.',
                'Account #' . $userId . ' department changed to ' . $to
            );
        });

        return [
            'ok'      => true,
            'message' => self::displayName($account) . ' moved to ' . $to
                . '. Requests they can already see are decided by the current rules, not the old ones.',
        ];
    }

    /**
     * Activate or deactivate an account.
     *
     * Deactivating is a lock, not a deletion: the account keeps its rows, its
     * requests and its history, and may be switched back on at any time. The
     * last active administrator cannot be switched off, which is the one way an
     * office could lock itself out of its own system.
     *
     * @return array{ok: bool, message: string}
     */
    public static function changeStatus(int $userId, string $status, ?User $actor): array
    {
        $status = ucfirst(strtolower(trim($status)));

        if (! in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'message' => 'An account can only be Active or Inactive.'];
        }

        $account = self::find($userId);

        if (! $account) {
            return ['ok' => false, 'message' => 'That account no longer exists.'];
        }

        if (self::accountStatus($account) === $status) {
            return ['ok' => true, 'message' => self::displayName($account) . ' is already ' . $status . '.'];
        }

        if ($status === Access::STATUS_INACTIVE && self::isLastActiveAdmin($account)) {
            return [
                'ok'      => false,
                'message' => 'This is the last active Administrator account. Deactivating it would leave NU Trace with nobody able to administer it.',
            ];
        }

        DB::transaction(function () use ($userId, $account, $status, $actor) {
            DB::table('users')->where('id', $userId)->update([
                'status'     => $status,
                'updated_at' => now(),
            ]);

            EmployeeDirectory::audit(
                $actor,
                'UPDATE',
                ($status === Access::STATUS_INACTIVE ? 'Deactivated' : 'Reactivated')
                    . ' the account for ' . self::displayName($account) . '.',
                'Account #' . $userId . ' status changed to ' . $status
            );
        });

        $message = $status === Access::STATUS_INACTIVE
            ? self::displayName($account) . ' can no longer sign in. Their records and requests are untouched, and the account can be reactivated at any time.'
            : self::displayName($account) . ' can sign in again.';

        // A change of who may decide requests is exactly the moment the office
        // needs to know whether anything is now waiting with nobody eligible.
        $orphaned = self::pendingWithoutReviewer();

        if ($orphaned > 0) {
            $message .= ' Note: ' . $orphaned . ' pending request(s) now have no active Asset Management Office account that can decide them.';
        }

        return ['ok' => true, 'message' => $message];
    }

    /* ══════════════════════ the guards ══════════════════════ */

    /**
     * Is this the only active Administrator account left?
     *
     * Counted from the database, so it is the real remaining number and not
     * something the page believes.
     */
    public static function isLastActiveAdmin(object $account): bool
    {
        if (trim((string) ($account->role ?? '')) !== 'Admin') {
            return false;
        }

        if (self::accountStatus($account) !== Access::STATUS_ACTIVE) {
            return false;
        }

        return DB::table('users')
            ->where('role', 'Admin')
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->where('id', '!=', (int) $account->id)
            ->count() === 0;
    }

    /**
     * A row's status as one of the two the system uses.
     *
     * Rows written before the column existed, or by hand, may hold '' or an odd
     * casing; anything that is not 'inactive' counts as active, which is what
     * the login check does too.
     */
    private static function accountStatus(object $account): string
    {
        $status = trim((string) ($account->status ?? ''));

        return strtolower($status) === 'inactive' ? Access::STATUS_INACTIVE : Access::STATUS_ACTIVE;
    }

    /**
     * Pending requests with nobody left who may decide them.
     *
     * Requirement 3.6 in one number: when the last eligible reviewer is
     * deactivated (or demoted), the waiting requests are preserved and the
     * situation is flagged for the office. Nothing is reassigned automatically,
     * and no request is deleted.
     */
    public static function pendingWithoutReviewer(): int
    {
        if (Access::activeDeciders() > 0) {
            return 0;
        }

        return DB::table('requests')->whereRaw('LOWER(status) = ?', ['pending'])->count();
    }

    /** The name to show for an account: the employee's, or the email as a fallback. */
    /**
     * How an account relates to the employee masterlist.
     *
     * @return array{key: string, label: string}
     */
    public static function employeeRecordState(object $account): array
    {
        if (empty($account->Employee_number)) {
            return ['key' => 'unmatched', 'label' => 'No employee record'];
        }

        if (strtolower(trim((string) ($account->employee_status ?? 'Active'))) !== 'active') {
            return ['key' => 'inactive', 'label' => 'Employee record inactive'];
        }

        return ['key' => 'matched', 'label' => (string) $account->Employee_number];
    }

    public static function displayName(object $account): string
    {
        $name = trim((string) ($account->Full_Name ?? ''));

        return $name !== '' ? $name : (string) $account->email;
    }
}
