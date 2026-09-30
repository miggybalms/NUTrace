<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Taking a disposed asset out of the inventory.
 *
 * Archiving a disposal record means the asset is gone from the institution's
 * inventory: it must disappear from the Assets list, the registry, the
 * department views and the inventory download.
 *
 * It is NOT deleted from the database. The row stays so the archived disposal
 * record can still say what the asset was, and so its accountability, repair,
 * replacement and audit history survives — the asset is still traceable, it is
 * simply no longer counted as inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assets', 'inventory_removed_at')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->timestamp('inventory_removed_at')->nullable();
            });

            Schema::table('assets', function (Blueprint $table) {
                $table->index('inventory_removed_at');
            });
        }

        if (! Schema::hasColumn('assets', 'inventory_removed_by')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->unsignedBigInteger('inventory_removed_by')->nullable();
            });

            Schema::table('assets', function (Blueprint $table) {
                $table->foreign('inventory_removed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Backfill: everything already sitting in Archived Disposal Assets has,
        // by that act, left the inventory. The stamp is taken from the archive
        // event itself so the dates line up.
        if (Schema::hasColumn('disposals', 'is_archived')) {
            $archived = DB::table('disposals')
                ->where('is_archived', true)
                ->whereNotNull('Asset_id')
                ->get(['Asset_id', 'archived_at', 'archived_by']);

            foreach ($archived as $record) {
                DB::table('assets')
                    ->where('id', $record->Asset_id)
                    ->whereNull('inventory_removed_at')
                    ->update([
                        'inventory_removed_at' => $record->archived_at ?: now(),
                        'inventory_removed_by' => $record->archived_by,
                        'updated_at'           => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assets', 'inventory_removed_by')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['inventory_removed_by']);
            });
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['inventory_removed_at']);
            $table->dropColumn(['inventory_removed_at', 'inventory_removed_by']);
        });
    }
};
