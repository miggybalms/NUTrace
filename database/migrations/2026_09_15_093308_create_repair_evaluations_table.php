<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create repair_evaluations — one servicing/evaluation record per repair.
     *
     * Matches the live table exactly (no started_at/completed_at; the row's
     * own created_at/updated_at tell the servicing timeline story).
     * Guarded: safe to run on databases where the table already exists
     * (e.g. created manually in Supabase) — it skips instead of failing.
     */
    public function up(): void
    {
        if (Schema::hasTable('repair_evaluations')) {
            return;
        }

        Schema::create('repair_evaluations', function (Blueprint $table) {
            $table->id('evaluation_id');

            // Link to the repair (one evaluation per repair)
            $table->unsignedBigInteger('repair_id');

            // Servicing information
            $table->string('technician_provider', 255)->nullable();
            $table->decimal('repair_cost', 10, 2)->nullable();
            $table->string('repair_result')->nullable();
            $table->date('expected_completion')->nullable();
            $table->text('parts_replaced')->nullable();

            // Evaluation information
            $table->text('inspection_findings')->nullable();
            $table->text('admin_remarks')->nullable();

            // Who recorded it
            $table->unsignedBigInteger('recorded_by')->nullable();

            $table->timestamps();

            // Enforce one evaluation per repair
            $table->unique('repair_id');

            // Foreign keys
            $table->foreign('repair_id')
                  ->references('Repair_id')   // ← fixed (capital R)
                  ->on('repairs')
                  ->cascadeOnDelete();

            $table->foreign('recorded_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_evaluations');
    }
};
