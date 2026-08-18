<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Allow asset_id to be NULL for bulk pullout requests.
        // The existing foreign key can remain in place.
        DB::statement(
            'ALTER TABLE pullouts ALTER COLUMN asset_id DROP NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This will only work if there are no NULL asset_id values.
        DB::statement(
            'ALTER TABLE pullouts ALTER COLUMN asset_id SET NOT NULL'
        );
    }
};