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
        Schema::create('replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->unsignedBigInteger('old_asset_id');
            $table->unsignedBigInteger('new_asset_id')->nullable();
            $table->string('Approve_by')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', [
                'Pending', 'Approved', 'Received'
            ])->default('Pending');
            $table->date('replacement_date')->nullable();
            $table->timestamps();

            $table->foreign('old_asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('new_asset_id')->references('id')->on('assets')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacements');
    }
};
