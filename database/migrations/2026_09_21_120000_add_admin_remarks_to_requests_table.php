<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The user's request note (requests.Note) is their own submitted statement and
 * must never be rewritten by the Asset Management Office. Their response is
 * recorded separately here, together with who wrote it and when, so the two
 * accounts stay distinguishable in the request history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable();
            }
            if (!Schema::hasColumn('requests', 'admin_remarks_by')) {
                $table->unsignedBigInteger('admin_remarks_by')->nullable();
            }
            if (!Schema::hasColumn('requests', 'admin_remarks_at')) {
                $table->timestamp('admin_remarks_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (Schema::hasColumn('requests', 'admin_remarks')) {
                $table->dropColumn('admin_remarks');
            }
            if (Schema::hasColumn('requests', 'admin_remarks_by')) {
                $table->dropColumn('admin_remarks_by');
            }
            if (Schema::hasColumn('requests', 'admin_remarks_at')) {
                $table->dropColumn('admin_remarks_at');
            }
        });
    }
};
