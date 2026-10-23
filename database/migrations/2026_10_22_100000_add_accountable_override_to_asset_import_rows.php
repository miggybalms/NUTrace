<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The office's own answer to "who is accountable for this line?".
 *
 * The accountable person is normally read from the file's name and matched
 * against the employee masterlist. When that match is missing, ambiguous, or
 * simply wrong, the office can pick the account for one line during review —
 * this column remembers that choice:
 *
 *   NULL  no choice: the name in the file decides (the default)
 *   0     the office deliberately left this line unassigned
 *   > 0   the user account they picked
 *
 * It is kept apart from `accountable_user_id`, which is what the resolver (or
 * this override) concluded, so re-checking a batch never forgets a decision the
 * office made by hand. The override is only ever read while staging a line; the
 * asset that is finally written uses the resulting user id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_import_rows') || Schema::hasColumn('asset_import_rows', 'accountable_override')) {
            return;
        }

        Schema::table('asset_import_rows', function (Blueprint $table) {
            $table->unsignedBigInteger('accountable_override')->nullable()->after('accountable_user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asset_import_rows') || ! Schema::hasColumn('asset_import_rows', 'accountable_override')) {
            return;
        }

        Schema::table('asset_import_rows', function (Blueprint $table) {
            $table->dropColumn('accountable_override');
        });
    }
};
