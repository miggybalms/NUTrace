<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The columns an imported fixed-asset registry needs.
 *
 * Three separate identifiers now live on an asset, and they must not be
 * confused with each other:
 *
 *   - `id`                 NU Trace's own row id
 *   - `Asset_code`         the code the office prints on the sticker
 *   - `property_id_no`     the client's PROPERTY ID NO - the identifier the
 *                          Asset Management Office uses to recognise the same
 *                          physical asset in its own registry
 *   - `inventory_id_no`    the client's ID NO., kept only as a secondary check
 *
 * An imported asset can also be accountable to nobody yet: the spreadsheet's
 * ACCOUNTABLE PERSON may have no NU Trace account, and the requirements are
 * explicit that no fake user may be invented to satisfy a NOT NULL column. So
 * `assets.user_id` becomes nullable, and the original name is preserved in
 * `accountable_name` until a real, eligible account can be assigned.
 *
 * `department_id` is the department the asset belongs to, independent of who
 * holds it. Before this, an asset's department was only reachable through its
 * holder (`users.department_id`), which cannot describe an imported asset whose
 * accountable person has not registered yet.
 *
 * Every step is guarded rather than assumed. A managed PostgreSQL (Supabase,
 * through its pooler) does not keep a migration's statements in one
 * transaction, so a failure halfway through leaves the columns, keys and
 * indexes it already created in place while the migration stays unrecorded.
 * Re-running must therefore finish the job instead of failing on "already
 * exists" - which is exactly what happened the first time this was deployed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'property_id_no')) {
                $table->string('property_id_no', 100)->nullable();
            }

            if (! Schema::hasColumn('assets', 'inventory_id_no')) {
                $table->string('inventory_id_no', 100)->nullable();
            }

            if (! Schema::hasColumn('assets', 'po_no')) {
                $table->string('po_no', 100)->nullable();
            }

            if (! Schema::hasColumn('assets', 'accountable_name')) {
                $table->string('accountable_name', 150)->nullable();
            }

            if (! Schema::hasColumn('assets', 'completion_status')) {
                // Complete | Needs completion | Under review. Deliberately kept
                // apart from Lifecycle_Status, which describes where the asset
                // is in its life, not how much of its record is filled in.
                $table->string('completion_status', 30)->default('Complete');
            }
        });

        if (! Schema::hasColumn('assets', 'department_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable();
            });
        }

        if (! $this->hasForeignKey('assets', 'assets_department_id_foreign', ['department_id'])) {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            });
        }

        if (! $this->hasIndex('assets', 'assets_department_id_index')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->index('department_id');
            });
        }

        // An asset held by nobody is a real state now: an imported asset whose
        // accountable person has not registered yet. Skipped when the column is
        // already nullable, so a re-run does not rewrite the table for nothing.
        $userColumn = $this->column('assets', 'user_id');

        if ($userColumn && ! $userColumn['nullable']) {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });
        }

        if (! $this->hasIndex('assets', 'assets_property_id_no_index')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->index('property_id_no');
            });
        }

        // The database, not the importer, is the last word on "this property is
        // one asset". A repeat upload, a resaved workbook or two administrators
        // importing at the same moment all end here. Skipped (not thrown) when
        // existing rows already share a property number, so the migration can
        // never fail on a database that predates it - the importer still flags
        // those rows for review.
        //
        // The grouped column is selected explicitly: PostgreSQL rejects a plain
        // `select *` alongside GROUP BY ("column assets.id must appear in the
        // GROUP BY clause"), even when only the number of groups is wanted.
        if (DB::getDriverName() === 'pgsql') {
            $duplicates = DB::table('assets')
                ->selectRaw('LOWER(property_id_no) AS property_key')
                ->whereNotNull('property_id_no')
                ->where('property_id_no', '<>', '')
                ->groupByRaw('LOWER(property_id_no)')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            if ($duplicates === 0) {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS assets_property_id_no_unique ON assets (LOWER(property_id_no)) WHERE property_id_no IS NOT NULL AND property_id_no <> \'\'');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS assets_property_id_no_unique');
        }

        if ($this->hasIndex('assets', 'assets_property_id_no_index')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropIndex(['property_id_no']);
            });
        }

        // Restoring NOT NULL only when every row still has a holder; a database
        // with imported-but-unassigned assets keeps the nullable column rather
        // than having those rows deleted to fit.
        $userColumn = $this->column('assets', 'user_id');

        if ($userColumn && $userColumn['nullable'] && ! DB::table('assets')->whereNull('user_id')->exists()) {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('assets', 'department_id')) {
            if ($this->hasForeignKey('assets', 'assets_department_id_foreign', ['department_id'])) {
                Schema::table('assets', function (Blueprint $table) {
                    $table->dropForeign(['department_id']);
                });
            }

            if ($this->hasIndex('assets', 'assets_department_id_index')) {
                Schema::table('assets', function (Blueprint $table) {
                    $table->dropIndex(['department_id']);
                });
            }

            Schema::table('assets', function (Blueprint $table) {
                $table->dropColumn('department_id');
            });
        }

        Schema::table('assets', function (Blueprint $table) {
            foreach (['property_id_no', 'inventory_id_no', 'po_no', 'accountable_name', 'completion_status'] as $column) {
                if (Schema::hasColumn('assets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * SQLite reports a table-level foreign key without a name, so the covered
     * columns are matched too - otherwise a guard would add the same key twice.
     */
    private function hasForeignKey(string $table, string $name, array $columns = []): bool
    {
        $wanted = array_map('mb_strtolower', $columns);

        foreach (Schema::getForeignKeys($table) as $key) {
            if (($key['name'] ?? null) === $name) {
                return true;
            }

            if ($wanted !== [] && array_map('mb_strtolower', (array) ($key['columns'] ?? [])) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    private function column(string $table, string $name): ?array
    {
        foreach (Schema::getColumns($table) as $column) {
            if (($column['name'] ?? null) === $name) {
                return $column;
            }
        }

        return null;
    }
};
