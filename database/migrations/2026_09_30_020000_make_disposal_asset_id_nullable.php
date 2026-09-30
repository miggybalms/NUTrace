<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Older disposal records kept on the Archived Disposal Assets page have no
 * asset at all: their asset row is long gone and only the record remains, which
 * is why the page shows them with an "N/A" asset code and an "Asset record
 * removed" chip.
 *
 * Production already stores those rows, so its disposals.Asset_id is nullable —
 * this migration is what makes a fresh database (and the test suite, which
 * builds its schema from the migrations) match it. Running it on production is a
 * no-op; it only stops a new environment from rejecting rows that exist in the
 * live database, and it is what lets those records be deleted too.
 *
 * Request_id was made nullable the same way in
 * 2026_05_03_000000_make_disposal_request_id_nullable.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disposals', function (Blueprint $table) {
            $table->foreignId('Asset_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Only reversible while no legacy record is stored: a NULL cannot be
        // turned back into a required value.
        Schema::table('disposals', function (Blueprint $table) {
            $table->foreignId('Asset_id')->change();
        });
    }
};
