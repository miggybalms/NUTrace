<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The office keeps an official work email for each employee, and it is needed
 * before the account exists: Employee Management holds the masterlist, while
 * users.email only exists once someone has registered.
 *
 * Nullable on purpose. Existing rows have no email and must not be invalidated
 * by this migration; an unknown email stays empty rather than being invented.
 * Unique because one work address belongs to one person - the database refuses
 * the duplicate instead of leaving two records that look like the same
 * employee. (An empty value is NULL, and SQL allows many NULLs, so the rows
 * that predate this column coexist happily.)
 *
 * This is additive: no existing column, row or foreign key is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_numbers', function (Blueprint $table) {
            $table->string('Email', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('employee_numbers', function (Blueprint $table) {
            // The index is dropped first on purpose: SQLite refuses to drop a
            // column while an index still covers it.
            $table->dropUnique(['Email']);
            $table->dropColumn('Email');
        });
    }
};
