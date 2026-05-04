<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pullouts', function (Blueprint $table) {
            $table->string('destination')->nullable()->after('pullout_date');
            $table->date('expected_return_date')->nullable()->after('destination');
        });
    }

    public function down(): void
    {
        Schema::table('pullouts', function (Blueprint $table) {
            $table->dropColumn(['destination', 'expected_return_date']);
        });
    }
};
