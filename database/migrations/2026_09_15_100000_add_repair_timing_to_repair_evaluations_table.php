<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensures repair_evaluations has the documented servicing columns.
     *
     * The base migration already creates the table complete, so this is a
     * no-op for fresh installs; for databases where the table was created
     * earlier (before the base migration was completed), it adds any
     * missing servicing columns in place.
     */
    public function up(): void
    {
        if (!Schema::hasTable('repair_evaluations')) {
            return;
        }

        Schema::table('repair_evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('repair_evaluations', 'technician_provider')) {
                $table->string('technician_provider', 255)->nullable()->after('repair_id');
            }
            if (!Schema::hasColumn('repair_evaluations', 'repair_cost')) {
                $table->decimal('repair_cost', 10, 2)->nullable()->after('technician_provider');
            }
            if (!Schema::hasColumn('repair_evaluations', 'repair_result')) {
                $table->string('repair_result')->nullable()->after('repair_cost');
            }
            if (!Schema::hasColumn('repair_evaluations', 'expected_completion')) {
                $table->date('expected_completion')->nullable()->after('repair_result');
            }
            if (!Schema::hasColumn('repair_evaluations', 'parts_replaced')) {
                $table->text('parts_replaced')->nullable()->after('expected_completion');
            }
            if (!Schema::hasColumn('repair_evaluations', 'inspection_findings')) {
                $table->text('inspection_findings')->nullable()->after('parts_replaced');
            }
            if (!Schema::hasColumn('repair_evaluations', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable()->after('inspection_findings');
            }
            if (!Schema::hasColumn('repair_evaluations', 'recorded_by')) {
                $table->unsignedBigInteger('recorded_by')->nullable()->after('admin_remarks');
            }
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: never drop documented servicing data.
    }
};
