<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three-year disposal waiting period.
 *
 * Two nullable columns on `requests`, nothing else:
 *
 *   approved_at                        the authoritative moment a disposal
 *                                      request was approved. Written only by
 *                                      the approval transaction, and never
 *                                      rewritten by preparing, saving, printing
 *                                      or signing the official form.
 *
 *   disposal_eligibility_notified_at   when the office was told the waiting
 *                                      period had ended. It is the recorded
 *                                      state shown on the disposal form and the
 *                                      reason a daily run does not look at the
 *                                      same request again.
 *
 * Both are additive and nullable, so every existing row keeps working: a
 * request approved before this migration has no approval timestamp and is
 * therefore treated as a legacy record (see Support\DisposalEligibility), not
 * as having been approved today.
 *
 * Rollback: dropping the two columns loses only those two facts. No request,
 * disposal form, disposal record, asset or attachment is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('requests', 'approved_at')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->timestamp('approved_at')->nullable();
            });
        }

        if (! Schema::hasColumn('requests', 'disposal_eligibility_notified_at')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->timestamp('disposal_eligibility_notified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('requests', 'disposal_eligibility_notified_at')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropColumn('disposal_eligibility_notified_at');
            });
        }

        if (Schema::hasColumn('requests', 'approved_at')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }
    }
};
