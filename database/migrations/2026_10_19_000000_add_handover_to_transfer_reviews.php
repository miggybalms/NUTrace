<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The handover an invitation describes.
 *
 * "Who is holding these assets right now" is written down when the invitation
 * goes out, because the employee who *requested* the transfer is not always the
 * employee holding the assets. A Department Head requests on behalf of their
 * department — the request form offers them the department's assets — and a
 * decision that insisted every asset were still the requester's would refuse
 * every one of those transfers. A decision also has to be able to tell that an
 * asset has passed into a *third* employee's hands since the office asked, which
 * is the one case it must refuse.
 *
 * Plain text, encoded in code: the same reason the tables above avoid enums.
 * No driver has to agree on how a JSON value is spelled, and a column added by
 * the code before it is added to the database still reads as "not recorded".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transfer_reviews') || Schema::hasColumn('transfer_reviews', 'handover')) {
            return;
        }

        Schema::table('transfer_reviews', function (Blueprint $table) {
            // {asset id: the user holding it when this invitation was issued}
            $table->text('handover')->nullable()->after('recipient_email');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('transfer_reviews') && Schema::hasColumn('transfer_reviews', 'handover')) {
            Schema::table('transfer_reviews', function (Blueprint $table) {
                $table->dropColumn('handover');
            });
        }
    }
};
