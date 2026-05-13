<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add maintenance_interval column to assets table
        if (Schema::hasTable('assets') && !Schema::hasColumn('assets', 'maintenance_interval')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->integer('maintenance_interval')->nullable()->comment('Maintenance interval in months');
            });
        }

        // Update Lifecycle_Status enum to include "For Checking"
        if (DB::getDriverName() === 'pgsql') {
            // For PostgreSQL, convert to text with check constraint for maximum compatibility
            // This approach works regardless of how the enum type was originally named
            
            // First, get the current constraint/type info
            $result = DB::select("
                SELECT column_name, data_type, udt_name 
                FROM information_schema.columns 
                WHERE table_name = 'assets' AND column_name = 'Lifecycle_Status'
            ");

            if ($result && count($result) > 0) {
                $col = $result[0];
                
                // If it's already a user-defined type (enum), try to add the value
                if ($col->data_type === 'USER-DEFINED' && $col->udt_name) {
                    try {
                        // Try to add the enum value
                        DB::statement("ALTER TYPE \"{$col->udt_name}\" ADD VALUE 'For Checking' BEFORE 'Disposal'");
                        return; // Success, exit early
                    } catch (\Throwable $e) {
                        // If adding to enum fails, convert to text below
                    }
                }
            }

            // If we reach here, convert the column to text with check constraint
            // This provides the safety of a constraint while being compatible
            try {
                // Drop any existing check constraint first
                DB::statement("
                    ALTER TABLE assets 
                    DROP CONSTRAINT IF EXISTS check_lifecycle_status
                ");
            } catch (\Throwable $e) {
                // Ignore if constraint doesn't exist
            }

            // Convert column to text
            DB::statement("
                ALTER TABLE assets 
                ALTER COLUMN \"Lifecycle_Status\" TYPE text
            ");

            // Add new check constraint with all valid statuses
            DB::statement("
                ALTER TABLE assets 
                ADD CONSTRAINT check_lifecycle_status 
                CHECK (\"Lifecycle_Status\" IN ('Acquired', 'Active', 'For Checking', 'For Repair', 'For Replacement', 'Pullout', 'Disposal'))
            ");

        } elseif (DB::getDriverName() === 'mysql') {
            // For MySQL, modify the column directly
            try {
                DB::statement("ALTER TABLE assets MODIFY COLUMN Lifecycle_Status ENUM('Acquired', 'Active', 'For Checking', 'For Repair', 'For Replacement', 'Pullout', 'Disposal') DEFAULT 'Acquired'");
            } catch (\Throwable $e) {
                // If enum modification fails, convert to varchar with check
                DB::statement("ALTER TABLE assets MODIFY COLUMN Lifecycle_Status VARCHAR(255)");
                DB::statement("
                    ALTER TABLE assets 
                    ADD CONSTRAINT check_lifecycle_status 
                    CHECK (Lifecycle_Status IN ('Acquired', 'Active', 'For Checking', 'For Repair', 'For Replacement', 'Pullout', 'Disposal'))
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove maintenance_interval column
        if (Schema::hasTable('assets') && Schema::hasColumn('assets', 'maintenance_interval')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropColumn('maintenance_interval');
            });
        }

        // Remove the check constraint if it exists
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement("
                    ALTER TABLE assets 
                    DROP CONSTRAINT IF EXISTS check_lifecycle_status
                ");
            } catch (\Throwable $e) {
                // Ignore
            }
        } elseif (DB::getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE assets DROP CONSTRAINT check_lifecycle_status");
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
