<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail records a sign-out as its own event, next to the LOGIN that
 * opened the session, so an admin can see the whole session.
 *
 * action_type was declared as an enum, which PostgreSQL stores as varchar plus
 * a CHECK constraint listing the allowed values - a LOGOUT row is rejected
 * until that list is widened here.
 *
 * Until this migration runs, sign-outs are still recorded: Support\AuditTrail
 * falls back to the LOGIN action type when the database refuses LOGOUT, so the
 * notice always reaches the audit log.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'audit_logs_action_type_check';

    /** Values action_type allowed before this migration. */
    private const ORIGINAL = [
        'CREATE', 'UPDATE', 'REPAIR', 'REPLACEMENT', 'DISPOSAL',
        'TRANSFER', 'LOGIN', 'APPROVAL',
    ];

    /** ...and after it. */
    private const WIDENED = [
        'CREATE', 'UPDATE', 'REPAIR', 'REPLACEMENT', 'DISPOSAL',
        'TRANSFER', 'LOGIN', 'LOGOUT', 'APPROVAL',
    ];

    public function up(): void
    {
        $this->writeConstraint(self::WIDENED);
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        // Keep sign-outs in the trail rather than deleting them.
        DB::table('audit_logs')->where('action_type', 'LOGOUT')->update(['action_type' => 'LOGIN']);

        $this->writeConstraint(self::ORIGINAL);
    }

    /**
     * PostgreSQL only - this app's database. Other drivers enforce their enum
     * on the column itself and are left untouched.
     */
    private function writeConstraint(array $values): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        $allowed = implode(', ', array_map(static fn (string $value): string => "'" . $value . "'", $values));

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS ' . self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE audit_logs ADD CONSTRAINT ' . self::CONSTRAINT
            . ' CHECK (action_type::text = ANY ((ARRAY[' . $allowed . '])::text[]))'
        );
    }

    private function isPostgres(): bool
    {
        return Schema::hasTable('audit_logs')
            && DB::connection()->getDriverName() === 'pgsql';
    }
};
