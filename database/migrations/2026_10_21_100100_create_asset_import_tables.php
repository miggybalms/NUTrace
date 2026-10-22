<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One upload, one row per spreadsheet line, kept until the office finishes.
 *
 * A fixed-asset registry is far too big for one web request, so an import is
 * staged: the file is read a chunk at a time, every line is checked and stored
 * in `asset_import_rows`, the office corrects what it must, and only then is a
 * chunk of rows written into `assets`. Nothing in `assets` is touched until the
 * commit step, and every imported row points back at the line it came from.
 *
 * The workbook itself is NOT stored here: it lives on the local disk only while
 * a batch is being checked, and is removed when the batch finishes. What is
 * kept is what the office reviewed - each line's values, the decision taken on
 * it, and why - which is small and is what makes a re-run auditable.
 *
 * Each table is created only when absent. A managed PostgreSQL does not keep a
 * migration's statements in one transaction, so a migration that failed after
 * creating them stays unrecorded while the tables remain - re-running must not
 * die on "relation already exists".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_import_batches')) {
            Schema::create('asset_import_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('original_name', 255);
                $table->string('disk_path', 255)->nullable();
                $table->string('file_hash', 64)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);

                // uploaded | checking | ready | importing | completed | failed
                $table->string('status', 20)->default('uploaded');

                $table->json('header')->nullable();     // source headings, in order
                $table->json('mapping')->nullable();    // NU Trace field => heading
                $table->json('options')->nullable();    // department + category decisions
                $table->json('summary')->nullable();    // last known counters

                $table->unsignedInteger('rows_checked')->default(0);
                $table->unsignedInteger('row_count')->nullable();

                // Byte offset in the data source just past the last row checked,
                // so a 300 MB workbook is read in windows instead of one long
                // request.
                $table->unsignedBigInteger('read_offset')->default(0);
                $table->text('message')->nullable();

                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('file_hash');
            });
        }

        if (! Schema::hasTable('asset_import_rows')) {
            Schema::create('asset_import_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained('asset_import_batches')->cascadeOnDelete();

                // The line in the spreadsheet, so every message can name it.
                $table->unsignedInteger('line');

                // ready | review | blocked | exists | imported | failed
                $table->string('status', 20)->default('review');
                $table->string('action', 20)->nullable(); // import | skip

                $table->string('property_id_no', 100)->nullable();
                $table->string('inventory_id_no', 100)->nullable();
                $table->string('asset_code', 50)->nullable();

                $table->json('payload')->nullable();    // values read from the file
                $table->json('resolved')->nullable();   // department, category, holder, flags
                $table->json('issues')->nullable();     // every reason a human should look

                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('accountable_name', 150)->nullable();
                $table->unsignedBigInteger('accountable_user_id')->nullable();

                $table->unsignedBigInteger('asset_id')->nullable();

                // generated | failed - the asset is saved either way, so a
                // storage outage can be retried without creating the asset a
                // second time.
                $table->string('qr_status', 20)->nullable();
                $table->text('message')->nullable();

                $table->timestamps();

                $table->unique(['batch_id', 'line']);
                $table->index(['batch_id', 'status']);
                $table->index(['batch_id', 'property_id_no']);
                $table->index(['batch_id', 'asset_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_import_rows');
        Schema::dropIfExists('asset_import_batches');
    }
};
