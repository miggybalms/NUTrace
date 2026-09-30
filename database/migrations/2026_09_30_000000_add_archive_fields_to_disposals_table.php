<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive support for disposal records.
 *
 * A disposal record is never deleted: archiving only moves it out of the main
 * Disposal list and into Archived Disposal Assets so the historical trail of a
 * retired asset stays intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('disposals', 'is_archived')) {
            Schema::table('disposals', function (Blueprint $table) {
                // false => main Disposal page, true => Archived Disposal Assets
                $table->boolean('is_archived')->default(false);
            });

            Schema::table('disposals', function (Blueprint $table) {
                $table->index('is_archived');
            });
        }

        if (! Schema::hasColumn('disposals', 'archived_at')) {
            Schema::table('disposals', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable();
            });
        }

        if (! Schema::hasColumn('disposals', 'archived_by')) {
            Schema::table('disposals', function (Blueprint $table) {
                $table->unsignedBigInteger('archived_by')->nullable();
            });

            Schema::table('disposals', function (Blueprint $table) {
                $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('disposals', 'archived_by')) {
            Schema::table('disposals', function (Blueprint $table) {
                $table->dropForeign(['archived_by']);
            });
        }

        Schema::table('disposals', function (Blueprint $table) {
            $table->dropIndex(['is_archived']);
            $table->dropColumn(['is_archived', 'archived_at', 'archived_by']);
        });
    }
};
