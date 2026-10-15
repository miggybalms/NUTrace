<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The official NU Lipa Disposal Form: preparation for one approved disposal
 * request, and the paper trail that follows it.
 *
 * One form per approved disposal request (disposal_forms.request_id is unique),
 * with one line per asset the request covers — which is how the official form
 * is laid out: a table of items with Tag No./Quantity/Description columns under
 * one set of signatures.
 *
 * Nothing here finalizes a disposal. The form records what the office prepared,
 * printed and sent to Accountancy; the asset only becomes Disposed when an
 * Admin explicitly finalizes the disposal, which writes the rows Support\
 * Disposals already maintains. No existing column or row is changed by this
 * migration — it is purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disposal_forms')) {
            Schema::create('disposal_forms', function (Blueprint $table) {
                $table->id();

                // One form per approved disposal request. The unique index is
                // what makes "generate" idempotent even if two clicks race.
                $table->foreignId('request_id')->unique()->constrained('requests')->cascadeOnDelete();

                // Workflow stage. A short key rather than an enum column on
                // purpose: the stages are the application's, and every database
                // this ships to stores enums differently (PostgreSQL keeps a
                // CHECK constraint), so adding a stage later must not need a
                // schema change.
                $table->string('status', 32)->default('draft');

                // ── Fields the form itself prints ────────────────────────
                $table->string('office_department', 150)->nullable();
                $table->date('expected_disposal_date')->nullable();

                // Method of disposal: the letter printed on the form (which
                // cannot tell the two T rows apart) plus the row it came from.
                $table->string('method_key', 32)->nullable();
                $table->string('method_code', 4)->nullable();

                // Additional fields for sold items.
                $table->decimal('sold_amount', 12, 2)->nullable();
                $table->string('sold_or_no', 60)->nullable();
                $table->date('sold_date')->nullable();
                $table->string('gate_pass_no', 60)->nullable();
                $table->date('gate_pass_date')->nullable();

                $table->text('remarks')->nullable();

                // The reason the office gave when it approved the request, kept
                // so the form can be prefilled from what was already decided.
                $table->string('approved_reason', 60)->nullable();

                // ── Preparation ─────────────────────────────────────────
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('prepared_at')->nullable();
                $table->timestamp('ready_at')->nullable();

                // ── Printing (evidence of printing only, never of signing) ─
                $table->timestamp('printed_at')->nullable();
                $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('print_count')->default(0);

                // ── Signed form received from Accountancy ───────────────
                $table->timestamp('signed_received_at')->nullable();
                $table->foreignId('signed_received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('signed_notes')->nullable();

                // The scanned signed copy lives on the private disk and is only
                // ever read back through an authorized route.
                $table->string('signed_file_name')->nullable();
                $table->string('signed_file_path')->nullable();
                $table->unsignedBigInteger('signed_file_size')->nullable();
                $table->string('signed_file_mime', 120)->nullable();

                // ── Finalization: the only step that retires the assets ──
                $table->date('actual_disposal_date')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('disposal_form_items')) {
            Schema::create('disposal_form_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('disposal_forms')->cascadeOnDelete();

                // Nullable so a line can survive the asset row being deleted
                // later: the form is the record of what was prepared.
                $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();

                $table->string('tag_no', 60)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('description', 255)->nullable();
                $table->string('serial_number', 150)->nullable();
                $table->date('date_acquired')->nullable();
                $table->string('location', 150)->nullable();
                $table->string('custodian', 150)->nullable();
                $table->string('condition_code', 4)->nullable();
                $table->string('reason_code', 4)->nullable();

                $table->timestamps();

                $table->unique(['form_id', 'asset_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_form_items');
        Schema::dropIfExists('disposal_forms');
    }
};
