<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Auth events in the audit trail.
 *
 * The admin only needs to know that an account signed in or signed out. No IP
 * address, hostname or other network detail is written about a user - or about
 * the admin themselves. Older rows that still contain one are cleaned on the
 * way to the screen by AuditLogText::redact().
 */
class AuditTrail
{
    public const LOGIN  = 'LOGIN';
    public const LOGOUT = 'LOGOUT';

    /** The account just signed in. */
    public static function login(?User $user): void
    {
        self::record($user, self::LOGIN, 'logged in');
    }

    /** The account just signed out. */
    public static function logout(?User $user): void
    {
        self::record($user, self::LOGOUT, 'logged out');
    }

    private static function record(?User $user, string $event, string $verb): void
    {
        if (! $user) {
            return;
        }

        $name = trim((string) ($user->display_name ?? $user->email ?? ''));
        $name = $name !== '' ? $name : ('user #' . $user->id);

        $row = [
            'user_id'            => $user->id,
            'action_type'        => $event,
            'notes'              => 'User ' . $verb,
            'action_description' => 'User ' . $name . ' ' . $verb . '.',
            'created_at'         => now(),
            'updated_at'         => now(),
        ];

        try {
            DB::table('audit_logs')->insert($row);
        } catch (\Throwable $e) {
            // An install whose action_type is still the original enum has no
            // LOGOUT value; record the sign-out as an auth event rather than
            // losing it entirely.
            if ($event === self::LOGOUT) {
                try {
                    $row['action_type'] = self::LOGIN;
                    DB::table('audit_logs')->insert($row);
                } catch (\Throwable $e) {
                    // Auditing must never break signing in or out.
                }
            }
        }
    }
}
