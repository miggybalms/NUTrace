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
        Schema::table('disposals', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->foreignId('request_id')->nullable()->change()->constrained('requests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disposals', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade')->change();
        });
    }
};
