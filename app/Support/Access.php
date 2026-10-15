<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who may do what — asked in one place.
 *
 * The app grew up with `$user->role === 'Admin'` written out at each route, so
 * the same question ("is this the Asset Management Office?") was answered
 * slightly differently in a dozen files. Every rule that decides access now
 * goes through here, which is also what makes a role or department change take
 * effect immediately: the answer is read from the live account on each
 * request, never from something cached at sign-in.
 *
 * Two rules from the panel recommendations live here and nowhere else:
 *
 *   - approval authority is the Asset Management Office (Admin / Facilities),
 *     which is what the disposal and reject routes already enforced;
 *   - nobody decides their own request, compared against the submitter stored
 *     on the request row, never against anything the browser sent.
 */
class Access
{
    /** The four roles the users table allows. */
    public const ROLES = ['Admin', 'Employee', 'Department Head', 'Facilities'];

    /** The role the office runs the registry with. */
    public const ASSET_OFFICE_ROLES = ['Admin', 'Facilities'];

    public const STATUS_ACTIVE   = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    /* ─────────────────────────── roles ─────────────────────────── */

    public static function role(?User $user): string
    {
        return trim((string) ($user->role ?? ''));
    }

    public static function isAdmin(?User $user): bool
    {
        return self::role($user) === 'Admin';
    }

    /**
     * The Asset Management Office: the accounts that run the registry, decide
     * requests and maintain the employee masterlist.
     */
    public static function isAssetOffice(?User $user): bool
    {
        return in_array(self::role($user), self::ASSET_OFFICE_ROLES, true);
    }

    public static function isDepartmentHead(?User $user): bool
    {
        return self::role($user) === 'Department Head';
    }

    /** A role value that can actually be stored. */
    public static function isKnownRole(?string $role): bool
    {
        return in_array(trim((string) $role), self::ROLES, true);
    }

    /* ──────────────────────── account status ─────────────────────── */

    public static function status(?User $user): string
    {
        $status = trim((string) ($user->status ?? self::STATUS_ACTIVE));

        return $status !== '' ? $status : self::STATUS_ACTIVE;
    }

    /**
     * A deactivated account keeps its history and its rows but may not sign in
     * or act. Compared case-insensitively so an older row written as
     * 'inactive' cannot slip through.
     */
    public static function isInactive(?User $user): bool
    {
        return strtolower(self::status($user)) === strtolower(self::STATUS_INACTIVE);
    }

    /* ──────────────────── areas and authority ──────────────────── */

    /** The private area behind /admin/*. */
    public static function canEnterAdminArea(?User $user): bool
    {
        return self::isAssetOffice($user);
    }

    /**
     * Employee and account management, and the import/export that feeds it.
     *
     * Administrators only - narrower than the admin area itself. This is a
     * capability that did not exist before, so handing it to the Facilities
     * accounts would be granting authority they have never had (requirement
     * 3.4 says not to). Facilities keeps the registry and the repair work it
     * already ran, and can still decide requests.
     */
    public static function canManageEmployees(?User $user): bool
    {
        return self::isAdmin($user);
    }

    /**
     * Deciding a request: approve or reject.
     *
     * Deliberately the office, not the requester's department head - the
     * department head's request page is a view of their own department, and
     * granting them a decision here would be inventing an approval stage that
     * the workflow has never had.
     */
    public static function canDecideRequests(?User $user): bool
    {
        return self::isAssetOffice($user);
    }

    /* ──────────────────────── self-approval ─────────────────────── */

    /**
     * Is this account the one that submitted the request?
     *
     * The comparison uses requests.user_id - the submitter recorded in the
     * database - so it cannot be talked out of the way by a crafted form, a
     * changed URL or a hidden field.
     */
    public static function isSubmitter(?User $user, $requestRecord): bool
    {
        if (! $user || ! $requestRecord) {
            return false;
        }

        $submitterId = (int) ($requestRecord->user_id ?? 0);

        return $submitterId > 0 && $submitterId === (int) $user->id;
    }

    /* ─────────────────── shared refusal messages ─────────────────── */

    public static function adminAreaMessage(): string
    {
        return 'This area belongs to the Asset Management Office. Your account does not have access to it.';
    }

    public static function decideMessage(): string
    {
        return 'Only the Asset Management Office can approve or reject a request.';
    }

    public static function selfApprovalMessage(): string
    {
        return 'You cannot approve or reject your own request. It has to be decided by the Asset Management Office.';
    }

    public static function manageEmployeesMessage(): string
    {
        return 'Only an Administrator can manage employees and accounts.';
    }

    /**
     * How many active accounts could still decide a request.
     *
     * Used to flag the one situation requirement 3.6 asks about: requests that
     * are waiting, with nobody left who is allowed to decide them. The office
     * keeps the request and is told about it; nothing is reassigned silently.
     */
    public static function activeDeciders(): int
    {
        return DB::table('users')
            ->whereIn('role', self::ASSET_OFFICE_ROLES)
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->count();
    }
}
