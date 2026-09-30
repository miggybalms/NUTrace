<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Allow asset_id to be NULL for bulk pullout requests.
        // The existing foreign key can remain in place.
        if ($this->usesAlterColumn()) {
            DB::statement(
                'ALTER TABLE pullouts ALTER COLUMN asset_id DROP NOT NULL'
            );

            return;
        }

        Schema::table('pullouts', function ($table) {
            $table->unsignedBigInteger('asset_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This will only work if there are no NULL asset_id values.
        if ($this->usesAlterColumn()) {
            DB::statement(
                'ALTER TABLE pullouts ALTER COLUMN asset_id SET NOT NULL'
            );

            return;
        }

        Schema::table('pullouts', function ($table) {
            $table->unsignedBigInteger('asset_id')->nullable(false)->change();
        });
    }

    /**
     * Only PostgreSQL understands ALTER COLUMN ... DROP NOT NULL. Everything
     * else (SQLite, which the test suite runs on) goes through the schema
     * builder instead. Production has already run this migration, so the
     * PostgreSQL path is unchanged there.
     */
    private function usesAlterColumn(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};